<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use RuntimeException;

/**
 * Pipeline step: replay stored outcome when Idempotency-Key matches (D-005).
 */
final class IdempotencyGuard
{
    public function __construct(
        private readonly ?IdempotencyStore $store = null,
    ) {}

    /**
     * @return array{action: 'continue'|'replay'|'conflict'|'busy', result?: CapabilityResult, record?: array<string, mixed>}
     */
    public function lookup(
        CapabilityDefinition $definition,
        CapabilityContext $context,
        ?string $key,
        string $requestHash,
    ): array {
        if ($key === null || $key === '' || ! $definition->shouldUseIdempotency() || $this->store === null) {
            return ['action' => 'continue'];
        }

        $actor = $context->actor();
        $actorType = ResolveActor::actorType($actor);
        $actorId = ResolveActor::actorId($actor);

        $existing = $this->store->find(
            $context->tenantId(),
            $actorType,
            $actorId,
            $definition->name,
            $key,
        );

        if ($existing === null) {
            $this->store->put([
                'tenant_id' => $context->tenantId(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'capability_name' => $definition->name,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
            ]);

            return ['action' => 'continue'];
        }

        $existingHash = $existing['request_hash'] ?? null;
        if ($existingHash !== null && $existingHash !== $requestHash) {
            return [
                'action' => 'conflict',
                'result' => CapabilityResult::failure(
                    code: 'conflict',
                    message: 'Idempotency key reused with a different request body (D-005).',
                ),
            ];
        }

        $status = (string) ($existing['status'] ?? '');
        if ($status === 'completed' || $status === 'failed') {
            $stored = $existing['result_json'] ?? null;
            $result = $this->hydrateResult($stored, $status === 'completed');

            return [
                'action' => 'replay',
                'result' => $result,
                'record' => $existing,
            ];
        }

        if ($status === 'processing') {
            return [
                'action' => 'busy',
                'result' => CapabilityResult::failure(
                    code: 'conflict',
                    message: 'Idempotency key is already processing (D-005).',
                    extra: ['retryable' => true],
                ),
            ];
        }

        return ['action' => 'continue', 'record' => $existing];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeResult(
        CapabilityDefinition $definition,
        CapabilityContext $context,
        string $key,
        string $requestHash,
        CapabilityResult $result,
        ?string $approvalId = null,
    ): void {
        if ($this->store === null) {
            return;
        }

        $actor = $context->actor();
        $this->store->put([
            'tenant_id' => $context->tenantId(),
            'actor_type' => ResolveActor::actorType($actor),
            'actor_id' => ResolveActor::actorId($actor),
            'capability_name' => $definition->name,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'status' => $result->isOk() ? 'completed' : ($result->isApprovalRequired() ? 'pending_approval' : 'failed'),
            'result_json' => $result->toArray(),
            'approval_id' => $approvalId,
        ]);
    }

    private function hydrateResult(mixed $stored, bool $okExpected): CapabilityResult
    {
        if (is_array($stored) && array_key_exists('ok', $stored)) {
            if ($stored['ok'] === true) {
                return CapabilityResult::success(
                    $stored['data'] ?? null,
                    array_merge($stored['meta'] ?? [], ['idempotent_replay' => true]),
                );
            }

            $error = $stored['error'] ?? [];
            $code = is_array($error) ? (string) ($error['code'] ?? 'internal') : 'internal';
            $message = is_array($error) ? (string) ($error['message'] ?? 'replayed failure') : 'replayed failure';
            $extra = is_array($error) ? $error : [];
            unset($extra['code'], $extra['message']);

            return CapabilityResult::failure(
                code: $code,
                message: $message,
                extra: $extra,
                meta: array_merge($stored['meta'] ?? [], ['idempotent_replay' => true]),
            );
        }

        if ($okExpected) {
            return CapabilityResult::success($stored, ['idempotent_replay' => true]);
        }

        throw new RuntimeException('Cannot hydrate idempotent replay result.');
    }
}
