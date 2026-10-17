<?php

namespace Rawphp\Capabilities\Persistence;

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Support\FailureReporter;
use RuntimeException;

/**
 * Production-oriented ApprovalStore using a {@see TableGateway} (D-006).
 *
 * Host apps bind a gateway backed by the capabilities_approvals table (Eloquent/query).
 * Unit tests inject {@see ArrayTableGateway}.
 */
final class DatabaseApprovalStore implements ApprovalStore
{
    public function __construct(
        private readonly TableGateway $table,
        private readonly Clock $clock,
    ) {}

    public function put(array $record): array
    {
        $now = $this->clock->now()->format(DATE_ATOM);
        $id = isset($record['id']) && is_string($record['id']) && $record['id'] !== ''
            ? $record['id']
            : $this->newId();

        $row = [
            'id' => $id,
            'capability_name' => (string) ($record['capability_name'] ?? ''),
            'status' => (string) ($record['status'] ?? 'pending'),
            'tenant_id' => $record['tenant_id'] ?? null,
            'scope' => $record['scope'] ?? ($record['tenant_id'] ?? null),
            'requester_actor_type' => (string) ($record['requester_actor_type'] ?? ''),
            'requester_actor_id' => (string) ($record['requester_actor_id'] ?? ''),
            'original_caller' => (string) ($record['original_caller'] ?? ''),
            'original_surface' => $record['original_surface'] ?? null,
            'input_json' => $record['input_json'] ?? null,
            'input_hash' => $record['input_hash'] ?? null,
            'idempotency_key' => $record['idempotency_key'] ?? null,
            'result_json' => $record['result_json'] ?? null,
            'result_status' => $record['result_status'] ?? null,
            'executor_actor_type' => $record['executor_actor_type'] ?? null,
            'executor_actor_id' => $record['executor_actor_id'] ?? null,
            'approval_policy' => $record['approval_policy'] ?? null,
            'decided_by' => $record['decided_by'] ?? null,
            'decided_at' => $record['decided_at'] ?? null,
            'decision_reason' => $record['decision_reason'] ?? null,
            'expires_at' => $record['expires_at'] ?? null,
            'execution_lease_until' => $record['execution_lease_until'] ?? null,
            'execution_attempt' => (int) ($record['execution_attempt'] ?? 0),
            'approved_at' => $record['approved_at'] ?? null,
            'messaging' => $record['messaging'] ?? null,
            'agent_json' => $record['agent_json'] ?? null,
            'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : $now,
            'updated_at' => isset($record['updated_at']) ? (string) $record['updated_at'] : $now,
        ];

        // agent_json needs the add_agent_json migration. Until it runs, every approval still
        // queues: the row goes in without the agent turn, and the approved run gets agent()
        // null, as before the column existed. An agent turn dropped this way is reported.
        if ($row['agent_json'] !== null && $this->table instanceof QueryTableGateway && ! $this->table->hasColumn('agent_json')) {
            FailureReporter::report(new RuntimeException(
                'capabilities_approvals has no agent_json column, so this approval was queued without its agent turn. '
                .'Publish and run the add_agent_json_to_capabilities_approvals_table migration.'
            ));
            $row['agent_json'] = null;
        }
        if ($row['agent_json'] === null) {
            unset($row['agent_json']);
        }

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
        // Single conditional update: id + status + free/expired lease (no TOCTOU find).
        $attributes['updated_at'] = $this->clock->now()->format(DATE_ATOM);

        return $this->table->updateWhereLeaseFree(
            ['id' => $id, 'status' => $expectedStatus],
            'execution_lease_until',
            $nowIso,
            $attributes,
        );
    }

    public function findByStatus(string $status): array
    {
        return $this->table->findWhere(['status' => $status]);
    }

    /**
     * Unguessable durable id (same shape as {@see QueryTableGateway} newId).
     * Not process-local sequential approval-{n} (L-005).
     */
    private function newId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
