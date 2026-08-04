<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Finish paths for the invoke pipeline: early exit, failure, approval, replay, wire.
 *
 * Extracted from {@see InvokePipeline} so ordered stage orchestration stays
 * separate from audit/event/idempotency finish policy.
 */
final class InvokeResultFinalizer
{
    public function __construct(
        public InvokeObservation $observation,
        public InvokeAuditStage $auditStage,
        public IdempotencyGuard $idempotencyGuard,
        public bool $eventsEnabled = true,
    ) {}

    public function finishEarly(CapabilityResult $result, ?InvokeState $state): CapabilityResult
    {
        $this->observation->lastStages = $state?->stages ?? [];
        $this->observation->lastState = $state;

        return $result;
    }

    public function finishFailure(
        InvokeState $state,
        CapabilityResult $result,
        bool $afterRun = false,
        bool $auditDeny = false,
        bool $outputInvalid = false,
    ): CapabilityResult {
        // Store idempotency failure when key present and after start of processing.
        if ($state->idempotencyKey && $state->context && $state->hasStage(PipelineStages::IDEMPOTENCY_LOOKUP) && ! $state->idempotentReplay) {
            $this->idempotencyGuard->storeResult(
                $state->definition,
                $state->context,
                $state->idempotencyKey,
                (string) $state->requestHash,
                $result,
            );
            if (! $state->hasStage(PipelineStages::STORE_IDEMPOTENCY)) {
                $state->mark(PipelineStages::STORE_IDEMPOTENCY);
            }
        }

        if ($auditDeny || $afterRun || $outputInvalid) {
            $this->recordAudit($state, success: false, failure: $result);
            $this->emitEvents($state, success: false, failure: $result);
        } elseif ($outputInvalid || $afterRun) {
            // already handled
        } else {
            // Pre-run failures still emit CapabilityFailed for observability on validation etc. optional —
            // StageErrorMapping only requires error envelope; emit for output_invalid and domain always.
            if (in_array($result->errorCode(), ['domain_error', 'output_invalid', 'internal'], true)) {
                $this->emitEvents($state, success: false, failure: $result);
            }
        }

        if ($outputInvalid) {
            // Ensure failure event recorded even if stages above skipped
            if ($this->observation->failedEvents === [] || ! $state->hasStage(PipelineStages::EMIT_EVENTS)) {
                if (! $state->hasStage(PipelineStages::EMIT_EVENTS)) {
                    $this->emitEvents($state, success: false, failure: $result);
                }
            }
        }

        return $this->wireResponse($state, $result->ok
            ? $result
            : CapabilityResult::failure(
                code: (string) $result->errorCode(),
                message: (string) ($result->error['message'] ?? 'failed'),
                extra: array_diff_key($result->error ?? [], array_flip(['code', 'message'])),
                meta: array_merge($result->meta, [
                    'request_id' => $state->requestId,
                    'stages' => $state->stages,
                ]),
            ));
    }

    public function finishApprovalRequired(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        if ($state->idempotencyKey && $state->context) {
            $this->idempotencyGuard->storeResult(
                $state->definition,
                $state->context,
                $state->idempotencyKey,
                (string) $state->requestHash,
                $result,
                $state->approvalId,
            );
            $state->mark(PipelineStages::STORE_IDEMPOTENCY);
        }

        $this->recordAudit($state, success: false, failure: $result);
        $state->mark(PipelineStages::EMIT_EVENTS);

        return $this->wireResponse($state, $result);
    }

    public function finishReplay(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        // Idempotent replay may skip full audit or mark replay (D-010).
        $this->recordAudit($state, success: $result->isOk(), failure: $result->isOk() ? null : $result);
        $state->mark(PipelineStages::EMIT_EVENTS);
        $meta = array_merge($result->meta, [
            'idempotent_replay' => true,
            'stages' => $state->stages,
        ]);

        if ($result->isOk()) {
            $wired = CapabilityResult::success($result->data, $meta);
        } else {
            $wired = CapabilityResult::failure(
                code: (string) $result->errorCode(),
                message: (string) ($result->error['message'] ?? 'failed'),
                extra: array_diff_key($result->error ?? [], array_flip(['code', 'message'])),
                meta: $meta,
            );
        }

        return $this->wireResponse($state, $wired);
    }

    public function wireResponse(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        $state->mark(PipelineStages::WIRE_RESPONSE);
        $state->result = $result;
        $this->observation->lastState = $state;
        $this->observation->lastStages = $state->stages;

        return $result;
    }

    public function emitEvents(InvokeState $state, bool $success, ?CapabilityResult $failure = null): void
    {
        $state->mark(PipelineStages::EMIT_EVENTS);

        if (! $this->eventsEnabled) {
            return;
        }

        // Bus events fire after domain run stage (never before domain commit by default).
        if ($success) {
            $event = new CapabilityInvoked(
                capability: $state->definition->name,
                caller: $state->caller,
                data: $state->output,
                meta: [
                    'request_id' => $state->requestId,
                    'stages' => $state->stages,
                ],
            );
            $this->observation->invokedEvents[] = $event;
        } elseif ($failure !== null) {
            $this->recordFailure(
                $state->definition,
                $failure->error['message'] ?? 'failed',
                $state->caller,
                $failure->errorCode() ?? 'internal',
            );
        }
    }

    public function recordFailure(
        CapabilityDefinition $definition,
        string $message,
        string $caller,
        string $code = 'output_invalid',
    ): void {
        $event = new CapabilityFailed(
            capability: $definition->name,
            code: $code,
            message: $message,
            caller: $caller,
        );
        $this->observation->failedEvents[] = $event;
        $this->observation->logs[] = [
            'level' => 'error',
            'message' => $message,
            'context' => [
                'capability' => $definition->name,
                'code' => $code,
                'caller' => $caller,
            ],
        ];
    }

    private function recordAudit(InvokeState $state, bool $success, ?CapabilityResult $failure = null): ?CapabilityResult
    {
        return $this->auditStage->record($state, $success, $failure);
    }
}
