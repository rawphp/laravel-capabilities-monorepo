<?php

namespace Rawphp\Capabilities\Approval;

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Exactly-once approval domain execution (D-006 / P2-004).
 *
 * Owns re-validation, original-actor re-auth, domain executor call, result
 * persistence, and idempotency completion. {@see ApprovalManager} remains the
 * public API for request / accept / reject / resume and delegates here after
 * lease claims.
 */
final class ApprovalExecutor
{
    public int $runCount = 0;

    /**
     * Events produced by execution (merged into manager events()).
     *
     * @var list<object>
     */
    public array $events = [];

    /**
     * Domain executor: (row, decidedBy) => CapabilityResult|array|mixed
     *
     * @var callable(array<string, mixed>, object): mixed|null
     */
    private $domainExecutor;

    /**
     * Revalidator: (row) => null (ok) | CapabilityResult failure | string error code
     *
     * @var callable(array<string, mixed>): mixed|null
     */
    private $revalidator;

    /**
     * Original-actor authorizer on accept: (row) => bool
     *
     * @var callable(array<string, mixed>): bool|null
     */
    private $originalAuthorizer;

    /**
     * @param  callable(array<string, mixed>, object): mixed|null  $domainExecutor
     * @param  callable(array<string, mixed>): mixed|null  $revalidator
     * @param  callable(array<string, mixed>): bool|null  $originalAuthorizer
     */
    public function __construct(
        private ApprovalStore $store,
        private ApprovalMetrics $metrics,
        ?callable $domainExecutor = null,
        ?callable $revalidator = null,
        ?callable $originalAuthorizer = null,
        private ?IdempotencyStore $idempotency = null,
        private ?AuditWriter $audit = null,
    ) {
        $this->domainExecutor = $domainExecutor;
        $this->revalidator = $revalidator;
        $this->originalAuthorizer = $originalAuthorizer;
    }

    /**
     * @param  callable(array<string, mixed>, object): mixed|null  $domainExecutor
     */
    public function withDomainExecutor(?callable $domainExecutor): self
    {
        $clone = clone $this;
        $clone->domainExecutor = $domainExecutor;
        $clone->runCount = 0;

        return $clone;
    }

    /**
     * @param  callable(array<string, mixed>): mixed|null  $revalidator
     */
    public function withRevalidator(?callable $revalidator): self
    {
        $clone = clone $this;
        $clone->revalidator = $revalidator;

        return $clone;
    }

    /**
     * @param  callable(array<string, mixed>): bool|null  $originalAuthorizer
     */
    public function withOriginalAuthorizer(?callable $originalAuthorizer): self
    {
        $clone = clone $this;
        $clone->originalAuthorizer = $originalAuthorizer;

        return $clone;
    }

    public function withIdempotency(?IdempotencyStore $store): self
    {
        $clone = clone $this;
        $clone->idempotency = $store;

        return $clone;
    }

    public function withAudit(?AuditWriter $audit): self
    {
        $clone = clone $this;
        $clone->audit = $audit;

        return $clone;
    }

    public function withStore(ApprovalStore $store): self
    {
        $clone = clone $this;
        $clone->store = $store;

        return $clone;
    }

    public function withMetrics(ApprovalMetrics $metrics): self
    {
        $clone = clone $this;
        $clone->metrics = $metrics;

        return $clone;
    }

    /**
     * Run domain for a lease-claimed approval row and persist terminal state.
     *
     * @param  array<string, mixed>  $row
     */
    public function execute(
        array $row,
        object $actor,
        string $via,
        string $fromStatus = ApprovalStateMachine::STATUS_APPROVED,
    ): CapabilityResult {
        $id = (string) $row['id'];

        // Re-validation
        $stale = $this->runRevalidation($row);
        if ($stale !== null) {
            $failed = $this->store->compareAndUpdate($id, $fromStatus, [
                'status' => ApprovalStateMachine::STATUS_EXECUTED,
                'result_status' => 'failed',
                'result_json' => $stale->toArray(),
                'execution_lease_until' => null,
            ]);

            // Atomic path may still be pending.
            if ($failed === null && $fromStatus === ApprovalStateMachine::STATUS_PENDING) {
                $failed = $this->store->compareAndUpdate($id, ApprovalStateMachine::STATUS_PENDING, [
                    'status' => ApprovalStateMachine::STATUS_EXECUTED,
                    'result_status' => 'failed',
                    'result_json' => $stale->toArray(),
                    'execution_lease_until' => null,
                ]);
            }

            $this->metrics->increment(
                $via === 'resume' ? 'approvals_resume_total' : 'approvals_accept_total',
                1,
                ['result' => $via === 'resume' ? 'stale' : 'stale'],
            );
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'stale']);

            $this->auditWrite('approval.executed', [
                'approval_id' => $id,
                'result' => $stale->toArray(),
                'replay' => false,
                'via' => $via,
            ]);

            return $stale;
        }

        // Original actor authorize
        if ($this->originalAuthorizer !== null && ! (bool) ($this->originalAuthorizer)($row)) {
            $fail = CapabilityResult::failure('forbidden', 'Original actor no longer authorized.');
            $this->store->compareAndUpdate($id, $fromStatus, [
                'status' => ApprovalStateMachine::STATUS_EXECUTED,
                'result_status' => 'failed',
                'result_json' => $fail->toArray(),
                'execution_lease_until' => null,
            ]);
            $this->auditWrite('approval.executed', [
                'approval_id' => $id,
                'result' => $fail->toArray(),
                'replay' => false,
                'via' => $via,
            ]);

            return $fail;
        }

        $this->runCount++;
        $raw = null;
        if ($this->domainExecutor !== null) {
            $raw = ($this->domainExecutor)($row, $actor);
        } else {
            $raw = CapabilityResult::ok(['executed' => true, 'approval_id' => $id]);
        }

        $result = $raw instanceof CapabilityResult
            ? $raw
            : CapabilityResult::ok($raw);

        $resultStatus = $result->isOk() ? 'ok' : 'failed';
        $payload = [
            'status' => ApprovalStateMachine::STATUS_EXECUTED,
            'result_status' => $resultStatus,
            'result_json' => $result->toArray(),
            'execution_lease_until' => null,
        ];

        $updated = $this->store->compareAndUpdate($id, $fromStatus, $payload);
        if ($updated === null) {
            // Another worker won — replay stored result.
            $fresh = $this->store->find($id);
            if ($fresh !== null && ($fresh['status'] ?? null) === ApprovalStateMachine::STATUS_EXECUTED) {
                // Roll back our runCount for lost race after execute? Domain may have double-applied
                // if executor is not idempotent — D-005 key should protect. Count as attempted.
                return $this->resultFromRow($fresh, replay: true);
            }

            // Shape B: fromStatus pending already flipped? try approved
            $updated = $this->store->compareAndUpdate($id, ApprovalStateMachine::STATUS_APPROVED, $payload)
                ?? $this->store->update($id, $payload);
        }

        $this->completeIdempotency($row, $result);

        $this->events[] = new CapabilityApprovalExecuted(
            capability: (string) ($row['capability_name'] ?? ''),
            approvalId: $id,
            via: $via,
            replay: false,
            result: $result->toArray(),
        );

        $this->auditWrite('approval.executed', [
            'approval_id' => $id,
            'result' => $result->toArray(),
            'replay' => false,
            'via' => $via,
        ]);

        $metric = $via === 'resume' ? 'approvals_resume_total' : 'approvals_accept_total';
        $this->metrics->increment($metric, 1, [
            'result' => $result->isOk() ? 'executed_ok' : 'executed_failed',
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resultFromRow(array $row, bool $replay): CapabilityResult
    {
        $json = $row['result_json'] ?? null;
        if (is_array($json) && array_key_exists('ok', $json)) {
            if ($json['ok']) {
                return CapabilityResult::ok($json['data'] ?? null, array_merge($json['meta'] ?? [], [
                    'idempotent_replay' => $replay,
                    'approval_replay' => $replay,
                ]));
            }

            $error = $json['error'] ?? ['code' => 'domain_error', 'message' => 'Stored failure'];

            return CapabilityResult::failure(
                (string) ($error['code'] ?? 'domain_error'),
                (string) ($error['message'] ?? 'Stored failure'),
                is_array($error) ? $error : [],
                array_merge($json['meta'] ?? [], ['idempotent_replay' => $replay, 'approval_replay' => $replay]),
            );
        }

        return CapabilityResult::ok($json, ['idempotent_replay' => $replay, 'approval_replay' => $replay]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function runRevalidation(array $row): ?CapabilityResult
    {
        if ($this->revalidator === null) {
            return null;
        }

        $out = ($this->revalidator)($row);
        if ($out === null || $out === true) {
            return null;
        }

        if ($out instanceof CapabilityResult) {
            return $out->isOk() ? null : $out;
        }

        if (is_string($out)) {
            return CapabilityResult::failure($out, 'Re-validation failed: '.$out);
        }

        if ($out === false) {
            return CapabilityResult::failure('failed_stale', 'Re-validation failed; resource stale.');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function completeIdempotency(array $row, CapabilityResult $result): void
    {
        $key = $row['idempotency_key'] ?? null;
        if ($key === null || $key === '' || $this->idempotency === null) {
            return;
        }

        $tenantId = isset($row['tenant_id']) ? (is_string($row['tenant_id']) ? $row['tenant_id'] : (string) $row['tenant_id']) : null;
        $actorType = (string) ($row['requester_actor_type'] ?? 'user');
        $actorId = (string) ($row['requester_actor_id'] ?? '');
        $capability = (string) ($row['capability_name'] ?? '');

        $existing = $this->idempotency->find($tenantId, $actorType, $actorId, $capability, (string) $key);
        if ($existing === null) {
            $this->idempotency->put([
                'tenant_id' => $tenantId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'capability_name' => $capability,
                'idempotency_key' => $key,
                'request_hash' => $row['input_hash'] ?? null,
                'status' => 'completed',
                'result_json' => $result->toArray(),
                'approval_id' => $row['id'] ?? null,
            ]);

            return;
        }

        $this->idempotency->update($tenantId, $actorType, $actorId, $capability, (string) $key, [
            'status' => 'completed',
            'result_json' => $result->toArray(),
            'approval_id' => $row['id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function auditWrite(string $event, array $payload): void
    {
        if ($this->audit === null) {
            return;
        }

        $this->audit->write(array_merge(['event' => $event], $payload));
    }
}
