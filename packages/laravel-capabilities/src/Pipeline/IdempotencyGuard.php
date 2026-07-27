<?php

namespace Rawphp\Capabilities\Pipeline;

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\MissingKeyWarner;
use Rawphp\Capabilities\Idempotency\RequestHash;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemClock;
use RuntimeException;

/**
 * Pipeline step: store outcomes and replay when Idempotency-Key matches (D-005).
 *
 * Behaviours:
 * - no key / flag none / readOnly → continue (non-idempotent path)
 * - first key → insert processing, continue
 * - completed + same hash → replay
 * - completed + different hash → conflict
 * - processing → busy (409/425)
 * - failed → replay failure by default (including different hash)
 * - expired → treat as new key
 */
final class IdempotencyGuard
{
    private Clock $clock;

    private IdempotencyConfig $config;

    private MissingKeyWarner $warner;

    public function __construct(
        private readonly ?IdempotencyStore $store = null,
        ?Clock $clock = null,
        ?IdempotencyConfig $config = null,
        ?MissingKeyWarner $warner = null,
    ) {
        $this->clock = $clock ?? new SystemClock;
        $this->config = $config ?? IdempotencyConfig::defaults();
        $this->warner = $warner ?? new MissingKeyWarner($this->config->warnMissingKey);
    }

    public function config(): IdempotencyConfig
    {
        return $this->config;
    }

    public function warner(): MissingKeyWarner
    {
        return $this->warner;
    }

    public function store(): ?IdempotencyStore
    {
        return $this->store;
    }

    /**
     * Hash canonical input for conflict detection.
     *
     * @param  array<string, mixed>|mixed  $input
     */
    public function hashInput(mixed $input): string
    {
        return RequestHash::of($input);
    }

    /**
     * Validate policy for a capability + key before lookup.
     *
     * @return CapabilityResult|null failure when policy rejects; null when OK
     */
    public function assertKeyPolicy(
        CapabilityDefinition $definition,
        ?string $key,
        string $caller = 'http',
    ): ?CapabilityResult {
        if (! $definition->shouldUseIdempotency()) {
            return null;
        }

        if ($definition->idempotent === CapabilityDefinition::IDEMPOTENT_REQUIRED
            && ($key === null || $key === '')) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Idempotency key is required for this capability (D-005).',
            );
        }

        if ($key === null || $key === '') {
            $this->warner->maybeWarn(
                $definition->name,
                $caller,
                $definition->isMutating(),
                $key,
            );

            return null;
        }

        if (! IdempotencyKey::isValid($key)) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Invalid idempotency key format (D-005).',
            );
        }

        return null;
    }

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

        if (! IdempotencyKey::isValid($key)) {
            return [
                'action' => 'conflict',
                'result' => CapabilityResult::failure(
                    code: 'validation_failed',
                    message: 'Invalid idempotency key format (D-005).',
                ),
            ];
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

        if ($existing !== null && $this->isExpired($existing)) {
            // Treat as missing — allow re-insert under same key after TTL.
            $existing = null;
        }

        if ($existing === null) {
            $now = $this->clock->now();
            $this->store->put([
                'tenant_id' => $context->tenantId(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'capability_name' => $definition->name,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'created_at' => $now->format(DATE_ATOM),
                'expires_at' => $now->add(new DateInterval('PT'.$this->config->ttlHours.'H'))->format(DATE_ATOM),
            ]);

            return ['action' => 'continue'];
        }

        $status = (string) ($existing['status'] ?? '');
        $existingHash = $existing['request_hash'] ?? null;

        // In-flight: busy regardless of hash (D-005 processing row).
        if ($status === 'processing') {
            return [
                'action' => 'busy',
                'result' => CapabilityResult::failure(
                    code: 'conflict',
                    message: 'Idempotency key is already processing (D-005).',
                    extra: ['retryable' => true, 'http_status' => 409],
                ),
                'record' => $existing,
            ];
        }

        // Failed: default replay failure for TTL (even if hash differs).
        if ($status === 'failed') {
            $stored = $existing['result_json'] ?? null;
            $result = $this->hydrateResult($stored, false);

            return [
                'action' => 'replay',
                'result' => $result,
                'record' => $existing,
            ];
        }

        // Completed: same hash replays; different hash conflicts.
        if ($status === 'completed') {
            if ($existingHash !== null && $existingHash !== $requestHash) {
                return [
                    'action' => 'conflict',
                    'result' => CapabilityResult::failure(
                        code: 'conflict',
                        message: 'Idempotency key reused with a different request body (D-005).',
                        extra: ['http_status' => 409],
                    ),
                    'record' => $existing,
                ];
            }

            $stored = $existing['result_json'] ?? null;
            $result = $this->hydrateResult($stored, true);

            return [
                'action' => 'replay',
                'result' => $result,
                'record' => $existing,
            ];
        }

        // pending_approval / unknown: conflict on different hash; else continue cautiously.
        if ($existingHash !== null && $existingHash !== $requestHash) {
            return [
                'action' => 'conflict',
                'result' => CapabilityResult::failure(
                    code: 'conflict',
                    message: 'Idempotency key reused with a different request body (D-005).',
                    extra: ['http_status' => 409],
                ),
                'record' => $existing,
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
        if ($this->store === null || ! $definition->shouldUseIdempotency()) {
            return;
        }

        // Never persist under an illegal key — policy should have rejected earlier.
        if (! IdempotencyKey::isValid($key)) {
            return;
        }

        $actor = $context->actor();
        $now = $this->clock->now();

        $status = $result->isOk()
            ? 'completed'
            : ($result->isApprovalRequired() ? 'pending_approval' : 'failed');

        $existing = $this->store->find(
            $context->tenantId(),
            ResolveActor::actorType($actor),
            ResolveActor::actorId($actor),
            $definition->name,
            $key,
        );

        $createdAt = is_array($existing) && isset($existing['created_at'])
            ? (string) $existing['created_at']
            : $now->format(DATE_ATOM);
        $expiresAt = is_array($existing) && isset($existing['expires_at'])
            ? $existing['expires_at']
            : $now->add(new DateInterval('PT'.$this->config->ttlHours.'H'))->format(DATE_ATOM);

        $this->store->put([
            'tenant_id' => $context->tenantId(),
            'actor_type' => ResolveActor::actorType($actor),
            'actor_id' => ResolveActor::actorId($actor),
            'capability_name' => $definition->name,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'status' => $status,
            'result_json' => $result->toArray(),
            'approval_id' => $approvalId ?? ($result->approvalId()),
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isExpired(array $row): bool
    {
        $expiresAt = $row['expires_at'] ?? null;
        if (! is_string($expiresAt) || $expiresAt === '') {
            return false;
        }

        try {
            $exp = new DateTimeImmutable($expiresAt);
        } catch (\Exception) {
            return false;
        }

        return $this->clock->now() >= $exp;
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
