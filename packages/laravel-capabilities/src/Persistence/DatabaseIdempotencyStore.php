<?php

namespace Rawphp\Capabilities\Persistence;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;

/**
 * Production-oriented IdempotencyStore via {@see TableGateway} (D-005).
 *
 * Identity: (tenant_id, actor_type, actor_id, capability_name, idempotency_key).
 * Null tenant is stored as empty string for unique-index safety.
 */
final class DatabaseIdempotencyStore implements IdempotencyStore
{
    public function __construct(
        private readonly TableGateway $table,
        private readonly Clock $clock,
    ) {}

    public function find(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
    ): ?array {
        $identity = $this->identityMap($tenantId, $actorType, $actorId, $capabilityName, $key);
        $rows = $this->table->findWhere($identity);
        $row = $rows[0] ?? null;
        if ($row === null) {
            return null;
        }
        if ($this->isExpired($row)) {
            return null;
        }

        return $this->toPublic($row);
    }

    public function put(array $record): array
    {
        $tenantId = array_key_exists('tenant_id', $record)
            ? (is_string($record['tenant_id']) || $record['tenant_id'] === null
                ? $record['tenant_id']
                : (string) $record['tenant_id'])
            : null;
        $actorType = (string) ($record['actor_type'] ?? '');
        $actorId = (string) ($record['actor_id'] ?? '');
        $capabilityName = (string) ($record['capability_name'] ?? '');
        $key = (string) ($record['idempotency_key'] ?? '');

        $now = $this->clock->now()->format(DATE_ATOM);
        $identity = $this->identityMap($tenantId, $actorType, $actorId, $capabilityName, $key);

        $row = [
            'tenant_id' => $identity['tenant_id'],
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'capability_name' => $capabilityName,
            'idempotency_key' => $key,
            'request_hash' => $record['request_hash'] ?? null,
            'status' => (string) ($record['status'] ?? 'processing'),
            'result_json' => $record['result_json'] ?? null,
            'approval_id' => $record['approval_id'] ?? null,
            'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : $now,
            'expires_at' => $record['expires_at'] ?? null,
        ];

        $stored = $this->table->upsert($identity, $row);

        return $this->toPublic($stored);
    }

    public function update(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
        array $attributes,
    ): ?array {
        $identity = $this->identityMap($tenantId, $actorType, $actorId, $capabilityName, $key);
        $updated = $this->table->updateWhere($identity, $attributes);
        if ($updated === null) {
            return null;
        }

        return $this->toPublic($updated);
    }

    /**
     * @return array{tenant_id: string, actor_type: string, actor_id: string, capability_name: string, idempotency_key: string}
     */
    private function identityMap(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
    ): array {
        return [
            'tenant_id' => $tenantId ?? '',
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'capability_name' => $capabilityName,
            'idempotency_key' => $key,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function toPublic(array $row): array
    {
        $tenant = $row['tenant_id'] ?? '';
        $row['tenant_id'] = $tenant === '' ? null : $tenant;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isExpired(array $row): bool
    {
        $expires = $row['expires_at'] ?? null;
        if (! is_string($expires) || $expires === '') {
            return false;
        }
        try {
            return $this->clock->now() >= new DateTimeImmutable($expires);
        } catch (\Exception) {
            return false;
        }
    }
}
