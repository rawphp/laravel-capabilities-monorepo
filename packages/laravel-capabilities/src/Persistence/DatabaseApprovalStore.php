<?php

namespace Rawphp\Capabilities\Persistence;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Production-oriented ApprovalStore using a {@see TableGateway} (D-006).
 *
 * Host apps bind a gateway backed by the capabilities_approvals table (Eloquent/query).
 * Unit tests inject {@see ArrayTableGateway}.
 */
final class DatabaseApprovalStore implements ApprovalStore
{
    private int $sequence = 0;

    public function __construct(
        private readonly TableGateway $table,
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
            'scope' => $record['scope'] ?? ($record['tenant_id'] ?? null),
            'requester_actor_type' => (string) ($record['requester_actor_type'] ?? ''),
            'requester_actor_id' => (string) ($record['requester_actor_id'] ?? ''),
            'original_caller' => (string) ($record['original_caller'] ?? ''),
            'input_json' => $record['input_json'] ?? null,
            'input_hash' => $record['input_hash'] ?? null,
            'idempotency_key' => $record['idempotency_key'] ?? null,
            'result_json' => $record['result_json'] ?? null,
            'result_status' => $record['result_status'] ?? null,
            'decided_by' => $record['decided_by'] ?? null,
            'decided_at' => $record['decided_at'] ?? null,
            'decision_reason' => $record['decision_reason'] ?? null,
            'expires_at' => $record['expires_at'] ?? null,
            'execution_lease_until' => $record['execution_lease_until'] ?? null,
            'execution_attempt' => (int) ($record['execution_attempt'] ?? 0),
            'approved_at' => $record['approved_at'] ?? null,
            'messaging' => $record['messaging'] ?? null,
            'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : $now,
            'updated_at' => isset($record['updated_at']) ? (string) $record['updated_at'] : $now,
        ];

        return $this->table->insert($row);
    }

    public function find(string $id): ?array
    {
        return $this->table->find($id);
    }

    public function update(string $id, array $attributes): ?array
    {
        $existing = $this->table->find($id);
        if ($existing === null) {
            return null;
        }

        $row = array_merge($existing, $attributes);
        $row['id'] = $id;
        $row['updated_at'] = $this->clock->now()->format(DATE_ATOM);

        return $this->table->replace($id, $row);
    }

    public function compareAndUpdate(string $id, string $expectedStatus, array $attributes): ?array
    {
        $attributes['updated_at'] = $this->clock->now()->format(DATE_ATOM);

        return $this->table->updateWhere(
            ['id' => $id, 'status' => $expectedStatus],
            $attributes,
        );
    }

    public function claimLease(
        string $id,
        string $expectedStatus,
        string $nowIso,
        array $attributes,
    ): ?array {
        $row = $this->table->find($id);
        if ($row === null) {
            return null;
        }
        if (($row['status'] ?? null) !== $expectedStatus) {
            return null;
        }

        $lease = $row['execution_lease_until'] ?? null;
        if (is_string($lease) && $lease !== '') {
            try {
                $until = new DateTimeImmutable($lease);
                $now = new DateTimeImmutable($nowIso);
                if ($now < $until) {
                    return null;
                }
            } catch (\Exception) {
                // unparseable lease = free
            }
        }

        $attributes['updated_at'] = $this->clock->now()->format(DATE_ATOM);

        return $this->table->updateWhere(
            ['id' => $id, 'status' => $expectedStatus],
            $attributes,
        );
    }

    public function findByStatus(string $status): array
    {
        return $this->table->findWhere(['status' => $status]);
    }

    private function nextId(): string
    {
        $this->sequence++;

        return 'approval-'.$this->sequence;
    }
}
