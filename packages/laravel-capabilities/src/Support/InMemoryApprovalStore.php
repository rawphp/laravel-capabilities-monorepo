<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Array-backed approval store for unit tests (no DB / Redis).
 *
 * Requires an explicit {@see Clock} — missing constructor args fail loudly
 * (ArgumentCountError) rather than silently using a null clock.
 */
final class InMemoryApprovalStore implements ApprovalStore
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    private int $sequence = 0;

    public function __construct(
        private readonly Clock $clock,
    ) {}

    public function put(array $record): array
    {
        $now = $this->clock->now()->format(DATE_ATOM);
        $id = isset($record['id']) && is_string($record['id']) && $record['id'] !== ''
            ? $record['id']
            : $this->nextId();

        $row = [
            'id' => $id,
            'capability_name' => (string) ($record['capability_name'] ?? ''),
            'status' => (string) ($record['status'] ?? 'pending'),
            'tenant_id' => $record['tenant_id'] ?? null,
            'requester_actor_type' => (string) ($record['requester_actor_type'] ?? ''),
            'requester_actor_id' => (string) ($record['requester_actor_id'] ?? ''),
            'original_caller' => (string) ($record['original_caller'] ?? ''),
            'input_json' => $record['input_json'] ?? null,
            'input_hash' => $record['input_hash'] ?? null,
            'idempotency_key' => $record['idempotency_key'] ?? null,
            'result_json' => $record['result_json'] ?? null,
            'decided_by' => $record['decided_by'] ?? null,
            'decided_at' => $record['decided_at'] ?? null,
            'decision_reason' => $record['decision_reason'] ?? null,
            'expires_at' => $record['expires_at'] ?? null,
            'execution_lease_until' => $record['execution_lease_until'] ?? null,
            'execution_attempt' => (int) ($record['execution_attempt'] ?? 0),
            'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : $now,
            'updated_at' => isset($record['updated_at']) ? (string) $record['updated_at'] : $now,
        ];

        $this->rows[$id] = $row;

        return $row;
    }

    public function find(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function update(string $id, array $attributes): ?array
    {
        if (! isset($this->rows[$id])) {
            return null;
        }

        $row = array_merge($this->rows[$id], $attributes);
        $row['id'] = $id;
        $row['updated_at'] = $this->clock->now()->format(DATE_ATOM);
        $this->rows[$id] = $row;

        return $row;
    }

    public function findByStatus(string $status): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => ($row['status'] ?? null) === $status,
        ));
    }

    private function nextId(): string
    {
        $this->sequence++;

        return 'approval-'.$this->sequence;
    }
}
