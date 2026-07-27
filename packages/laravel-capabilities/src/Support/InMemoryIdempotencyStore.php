<?php

namespace Rawphp\Capabilities\Support;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;

/**
 * Array-backed idempotency outcome store for unit tests (D-005).
 *
 * Requires an explicit {@see Clock} — missing constructor args fail loudly.
 * Expired rows (expires_at <= now) are treated as missing on {@see find()}.
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function find(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
    ): ?array {
        $identity = $this->identity($tenantId, $actorType, $actorId, $capabilityName, $key);
        $row = $this->rows[$identity] ?? null;

        if ($row === null) {
            return null;
        }

        if ($this->isExpired($row)) {
            unset($this->rows[$identity]);

            return null;
        }

        return $row;
    }

    public function put(array $record): array
    {
        $tenantId = isset($record['tenant_id']) ? (is_string($record['tenant_id']) || is_null($record['tenant_id']) ? $record['tenant_id'] : (string) $record['tenant_id']) : null;
        $actorType = (string) ($record['actor_type'] ?? '');
        $actorId = (string) ($record['actor_id'] ?? '');
        $capabilityName = (string) ($record['capability_name'] ?? '');
        $key = (string) ($record['idempotency_key'] ?? '');

        $now = $this->clock->now()->format(DATE_ATOM);

        $row = [
            'tenant_id' => $tenantId,
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

        $this->rows[$this->identity($tenantId, $actorType, $actorId, $capabilityName, $key)] = $row;

        return $row;
    }

    public function update(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
        array $attributes,
    ): ?array {
        $identity = $this->identity($tenantId, $actorType, $actorId, $capabilityName, $key);

        if (! isset($this->rows[$identity])) {
            return null;
        }

        $row = array_merge($this->rows[$identity], $attributes);
        $row['tenant_id'] = $tenantId;
        $row['actor_type'] = $actorType;
        $row['actor_id'] = $actorId;
        $row['capability_name'] = $capabilityName;
        $row['idempotency_key'] = $key;
        $this->rows[$identity] = $row;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isExpired(array $row): bool
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

    private function identity(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capabilityName,
        string $key,
    ): string {
        return implode("\0", [
            $tenantId ?? '',
            $actorType,
            $actorId,
            $capabilityName,
            $key,
        ]);
    }
}
