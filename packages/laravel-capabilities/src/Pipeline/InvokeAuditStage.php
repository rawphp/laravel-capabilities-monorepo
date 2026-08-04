<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Throwable;

/**
 * Pipeline stage: record audit (D-010 best_effort vs strict + outbox).
 *
 * Extracted from {@see InvokePipeline} so audit policy stays cohesive without
 * growing the ordered stage orchestrator.
 */
final class InvokeAuditStage
{
    public function __construct(
        public InvokeObservation $observation,
        public ?AuditWriter $auditWriter = null,
        public string $auditMode = 'best_effort',
        public bool $auditEnabled = true,
        public bool $auditRequired = false,
        public string $auditDriver = 'database',
        public ?AuditOutbox $auditOutbox = null,
        public bool $throwOnAuditFailure = false,
    ) {}

    /**
     * Write audit entry. On success-path audit failure:
     * - best_effort: log (+ outbox when required); return null so client still succeeds
     * - strict: return failure envelope; domain run is NOT rolled back by the registry
     */
    public function record(InvokeState $state, bool $success, ?CapabilityResult $failure = null): ?CapabilityResult
    {
        $state->mark(PipelineStages::RECORD_AUDIT);

        if (! $this->auditEnabled || $this->auditWriter === null || ! $state->definition->shouldAudit()) {
            return null;
        }

        $durationMs = $this->observation->invokeStartedAt !== null
            ? (microtime(true) - $this->observation->invokeStartedAt) * 1000
            : 0.0;

        $entry = AuditLogger::entry($state, $success, $failure, $durationMs);

        try {
            if ($this->throwOnAuditFailure) {
                throw new \RuntimeException('Audit writer failed.');
            }

            $this->auditWriter->write($entry);
        } catch (Throwable $e) {
            if ($this->auditMode === 'strict' && $success) {
                $this->observation->logs[] = [
                    'level' => 'error',
                    'message' => 'Audit failed in strict mode: '.$e->getMessage(),
                    'context' => ['capability' => $state->definition->name],
                ];

                // When required, still enqueue for operators even in strict.
                if ($this->auditRequired) {
                    $this->ensureOutbox()->enqueue($entry);
                }

                return CapabilityResult::failure(
                    code: 'audit_failed',
                    message: 'Audit failed in strict mode: '.$e->getMessage(),
                    extra: array_merge(ErrorCodeMap::wireFields('audit_failed'), [
                        'retryable' => true,
                        'domain_committed' => $state->domainSideEffect,
                    ]),
                    meta: [
                        'request_id' => $state->requestId,
                        'stages' => $state->stages,
                        'domain_side_effect' => $state->domainSideEffect,
                    ],
                );
            }

            $this->observation->logs[] = [
                'level' => 'warning',
                'message' => 'Audit failed (best_effort): '.$e->getMessage(),
                'context' => ['capability' => $state->definition->name],
            ];

            // best_effort + required: never silent drop — durable outbox intent.
            if ($this->auditRequired) {
                $this->ensureOutbox()->enqueue($entry);
            } elseif ($this->auditRequired === false) {
                // optional retry path may still enqueue when outbox is bound
                $this->auditOutbox?->enqueue($entry);
            }
        }

        return null;
    }

    public function ensureOutbox(): AuditOutbox
    {
        return $this->auditOutbox ??= new AuditOutbox;
    }
}
