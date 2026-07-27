<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Stores mutating invoke outcomes by composite key (D-005).
 *
 * Identity: (tenant/scope, actor, capability, idempotency_key).
 *
 * Note: the production scaffold class
 * {@see \Rawphp\Capabilities\Idempotency\IdempotencyStore} is a separate type
 * that will implement this contract when domain drivers land.
 *
 * @phpstan-type IdempotencyRecord array{
 *     tenant_id: string|null,
 *     actor_type: string,
 *     actor_id: string,
 *     capability_name: string,
 *     idempotency_key: string,
 *     request_hash: string|null,
 *     status: string,
 *     result_json: mixed,
 *     approval_id: string|null,
 *     created_at: string,
 *     expires_at: string|null
 * }
 */
interface IdempotencyStore
{
    /**
     * @return IdempotencyRecord|null
     */
    public function find(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
    ): ?array;

    /**
     * Insert or replace the record for the composite identity.
     *
     * @param  array<string, mixed>  $record
     * @return IdempotencyRecord
     */
    public function put(array $record): array;

    /**
     * Merge attributes for an existing identity; null when not found.
     *
     * @param  array<string, mixed>  $attributes
     * @return IdempotencyRecord|null
     */
    public function update(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
        array $attributes,
    ): ?array;
}
