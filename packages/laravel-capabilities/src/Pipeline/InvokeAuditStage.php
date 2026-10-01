<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Support\FailureReporter;
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
     *
     * $force bypasses the capability's own audit opt-out (readOnly / audit: false) so a
     * server bug like output_invalid is never silently dropped; a failed write then always
     * lands in the outbox. The global audit.enabled switch still wins.
     */
    public function record(InvokeState $state, bool $success, ?CapabilityResult $failure = null, bool $force = false): ?CapabilityResult
    {
        $state->mark(PipelineStages::RECORD_AUDIT);

        if (! $this->auditEnabled || $this->auditWriter === null || ! $state->definition->shouldAudit($force)) {
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
            $strict = $state->definition->auditMode($this->auditMode) === 'strict' && $success;
            // Never silent (D-010 "log error + metric"): the host handler gets the real
            // exception; the wire never does — a QueryException carries SQL and bound
            // payload_json (L-104).
            FailureReporter::reportAndCount($e, FailureReporter::AUDIT_WRITE_FAILED, ['mode' => $strict ? 'strict' : 'best_effort']);

            if ($strict) {
                $this->observation->log([
                    'level' => 'error',
                    'message' => 'Audit failed in strict mode: '.$e->getMessage(),
                    'context' => ['capability' => $state->definition->name],
                ]);

                // When required, still enqueue for operators even in strict. A held
                // wrap is rolled back next, so there is no success to record.
                if ($this->auditRequired && ! $state->wrapHeld) {
                    $this->ensureOutbox()->enqueue($entry);
                }

                return CapabilityResult::failure(
                    code: 'audit_failed',
                    message: 'Audit failed.',
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

            $this->observation->log([
                'level' => 'warning',
                'message' => 'Audit failed (best_effort): '.$e->getMessage(),
                'context' => ['capability' => $state->definition->name],
            ]);

            // best_effort + required (or forced): never silent drop — durable outbox intent.
            if ($this->auditRequired || $force) {
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
