<?php

namespace Rawphp\Capabilities\Approval;

use Illuminate\Contracts\Events\Dispatcher;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FailureReporter;
use Throwable;

/**
 * Exactly-once approval domain execution (D-006 / P2-004).
 *
 * Owns re-validation, original-actor re-auth, domain executor call and result
 * persistence. {@see ApprovalManager} remains the public API for request / accept /
 * reject / resume and delegates here after lease claims. The request's idempotency
 * key is settled by the pipeline the domain executor runs through (L-102 / L-202),
 * never written a second time here.
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

    private ?Dispatcher $dispatcher = null;

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

    public function withAudit(?AuditWriter $audit): self
    {
        $clone = clone $this;
        $clone->audit = $audit;

        return $clone;
    }

    public function withEventDispatcher(?Dispatcher $dispatcher): self
    {
        $clone = clone $this;
        $clone->dispatcher = $dispatcher;

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
        // Who ran the domain (D-002): approver on accept, SystemActor on resume.
        $executor = [
            'executor_actor_type' => ResolveActor::actorType($actor),
            'executor_actor_id' => ResolveActor::actorId($actor),
        ];

        // Re-validation
        $stale = $this->runRevalidation($row);
        if ($stale !== null) {
            $this->store->compareAndUpdate($id, $fromStatus, [
                'status' => ApprovalStateMachine::STATUS_EXECUTED,
                'result_status' => 'failed',
                'result_json' => $stale->toArray(),
                'execution_lease_until' => null,
                ...$executor,
            ]);

            $this->metrics->increment(
                $via === 'resume' ? 'approvals_resume_total' : 'approvals_accept_total',
                1,
                ['result' => 'stale'],
            );

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
                ...$executor,
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
        // No executor bound → fail closed; never report a run that did not happen.
        $raw = $this->domainExecutor !== null
            ? ($this->domainExecutor)($row, $actor)
            : CapabilityResult::failure('not_configured', 'No approval executor bound; capability was not run.');

        $result = $raw instanceof CapabilityResult
            ? $raw
            : CapabilityResult::ok($raw);

        $resultStatus = $result->isOk() ? 'ok' : 'failed';
        $payload = [
            'status' => ApprovalStateMachine::STATUS_EXECUTED,
            'result_status' => $resultStatus,
            'result_json' => $result->toArray(),
            'execution_lease_until' => null,
            ...$executor,
        ];

        $updated = $this->store->compareAndUpdate($id, $fromStatus, $payload);
        if ($updated === null && $fromStatus !== ApprovalStateMachine::STATUS_APPROVED) {
            $updated = $this->store->compareAndUpdate($id, ApprovalStateMachine::STATUS_APPROVED, $payload);
        }
        if ($updated === null) {
            $fresh = $this->store->find($id);
            if ($fresh !== null && ($fresh['status'] ?? null) === ApprovalStateMachine::STATUS_EXECUTED) {
                return $this->resultFromRow($fresh, replay: true);
            }

            // Expired and rejected are terminal. Do not force executed over them.
            return CapabilityResult::failure(
                'conflict',
                'Approval execution lost the race and was not stored.',
                ['approval_id' => $id, 'status' => $fresh['status'] ?? null],
            );
        }

        $executed = new CapabilityApprovalExecuted(
            capability: (string) ($row['capability_name'] ?? ''),
            approvalId: $id,
            via: $via,
            replay: false,
            result: $result->toArray(),
        );
        $this->events[] = $executed;
        if ($this->dispatcher !== null) {
            try {
                $this->dispatcher->dispatch($executed);
            } catch (Throwable $e) {
                // The domain ran and the row says so; a listener cannot undo that (L-103).
                FailureReporter::reportAndCount($e, FailureReporter::LISTENER_FAILED, ['event' => $executed::class]);
            }
        }

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
     * @param  array<string, mixed>  $payload
     */
    private function auditWrite(string $event, array $payload): void
    {
        if ($this->audit === null) {
            return;
        }

        // run() has committed and the row is terminal; audit failure is reported, not thrown (L-104).
        try {
            $this->audit->write(array_merge(['event' => $event], $payload));
        } catch (Throwable $e) {
            FailureReporter::reportAndCount($e, FailureReporter::AUDIT_WRITE_FAILED, ['mode' => 'approval']);
        }
    }
}
