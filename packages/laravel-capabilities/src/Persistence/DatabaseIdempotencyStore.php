<?php

namespace Rawphp\Capabilities\Persistence;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;

/**
 * Production-oriented IdempotencyStore via {@see TableGateway} (D-005).
 *
 * Identity: (tenant_id, actor_type, actor_id, capability_name, idempotency_key).
 * Null tenant is stored as empty string for unique-index safety, so a literal
 * '' tenant is rejected rather than merged into the null-tenant row.
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
        [$identity, $row] = $this->rowFor($record);

        return $this->toPublic($this->table->upsert($identity, $row));
    }

    /**
     * Insert-if-absent on the unique identity; an expired holder is taken over
     * with a compare-and-swap on the expiry we read, so one taker wins.
     */
    public function claim(array $record): bool
    {
        [$identity, $row] = $this->rowFor($record);

        if ($this->table->insertIfAbsent($identity, $row) !== null) {
            return true;
        }

        $rows = $this->table->findWhere($identity);
        if ($rows === [] || ! $this->isExpired($rows[0])) {
            return false;
        }

        $swap = $identity + ['expires_at' => $rows[0]['expires_at']];

        return $this->table->updateWhere($swap, $row) !== null;
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
     * @param  array<string, mixed>  $record
     * @return array{0: array{tenant_id: string, actor_type: string, actor_id: string, capability_name: string, idempotency_key: string}, 1: array<string, mixed>}
     */
    private function rowFor(array $record): array
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

        return [$identity, $row];
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
        if ($tenantId === '') {
            throw new \InvalidArgumentException('Idempotency tenant id must be null or non-empty; \'\' would share the null-tenant row (D-005).');
        }

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
