<?php

namespace Rawphp\Capabilities\Idempotency;

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore as IdempotencyStoreContract;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * In-process / unit-friendly outcome store for mutating invokes (D-005).
 *
 * Production DB drivers implement the same {@see IdempotencyStoreContract}.
 * Identity: (tenant/scope, actor_type, actor_id, capability_name, idempotency_key).
 * Expired rows are treated as missing on {@see find()}.
 */
final class IdempotencyStore implements IdempotencyStoreContract
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function __construct(
        private readonly Clock $clock = new SystemClock,
        private readonly int $ttlHours = IdempotencyConfig::DEFAULT_TTL_HOURS,
    ) {
        if ($this->ttlHours < 1) {
            throw new \InvalidArgumentException('ttlHours must be >= 1.');
        }
    }

    public static function withConfig(Clock $clock, IdempotencyConfig $config): self
    {
        return new self($clock, $config->ttlHours);
    }

    public function ttlHours(): int
    {
        return $this->ttlHours;
    }

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
        $tenantId = array_key_exists('tenant_id', $record)
            ? (is_string($record['tenant_id']) || $record['tenant_id'] === null
                ? $record['tenant_id']
                : (string) $record['tenant_id'])
            : null;
        $actorType = (string) ($record['actor_type'] ?? '');
        $actorId = (string) ($record['actor_id'] ?? '');
        $capabilityName = (string) ($record['capability_name'] ?? '');
        $key = (string) ($record['idempotency_key'] ?? '');

        $now = $this->clock->now();
        $nowAtom = $now->format(DATE_ATOM);

        $expiresAt = $record['expires_at'] ?? null;
        if ($expiresAt === null) {
            $expiresAt = $now->add(new DateInterval('PT'.$this->ttlHours.'H'))->format(DATE_ATOM);
        } elseif ($expiresAt instanceof DateTimeImmutable) {
            $expiresAt = $expiresAt->format(DATE_ATOM);
        }

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
            'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : $nowAtom,
            'expires_at' => is_string($expiresAt) ? $expiresAt : null,
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

        if ($this->isExpired($this->rows[$identity])) {
            unset($this->rows[$identity]);

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
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $live = [];
        foreach ($this->rows as $identity => $row) {
            if ($this->isExpired($row)) {
                unset($this->rows[$identity]);
                continue;
            }
            $live[] = $row;
        }

        return $live;
    }

    public function forgetExpired(): int
    {
        $removed = 0;
        foreach ($this->rows as $identity => $row) {
            if ($this->isExpired($row)) {
                unset($this->rows[$identity]);
                $removed++;
            }
        }

        return $removed;
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
