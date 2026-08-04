<?php

namespace Rawphp\Capabilities\Approval;

use DateInterval;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * High-level approval API: request, accept, reject, expire, resume (D-006 / P2-004).
 *
 * Owns the state machine; channel adapters only notify — never execute.
 * Domain execution after lease claim lives in {@see ApprovalExecutor}.
 * Stuck-row resume lives in {@see ApprovalResumer}.
 * Pending TTL expiry lives in {@see ApprovalExpiry}.
 */
final class ApprovalManager
{
    private Clock $clock;

    /** @var array<string, mixed> */
    private array $config;

    private ApprovalPolicy $policy;

    private ApprovalMetrics $metrics;

    private ApprovalStateMachine $machine;

    private ApprovalExecutor $rowExecutor;

    /** @var list<object> */
    private array $events = [];

    /** @var list<ApprovalNotifier> */
    private array $notifiers = [];

    private ?AuditWriter $audit;

    private ?IdempotencyStore $idempotency;

    /**
     * @param  array<string, mixed>  $config
     * @param  callable(array<string, mixed>, object): mixed|null  $executor
     * @param  callable(array<string, mixed>): mixed|null  $revalidator
     * @param  callable(array<string, mixed>): bool|null  $originalAuthorizer
     */
    public function __construct(
        private ApprovalStore $store,
        ?Clock $clock = null,
        array $config = [],
        ?ApprovalPolicy $policy = null,
        ?callable $executor = null,
        ?callable $revalidator = null,
        ?callable $originalAuthorizer = null,
        ?AuditWriter $audit = null,
        ?IdempotencyStore $idempotency = null,
        ?ApprovalMetrics $metrics = null,
    ) {
        $this->clock = $clock ?? new SystemClock;
        $this->config = self::mergeConfig($config);
        $this->policy = $policy ?? ApprovalPolicy::fromString(
            (string) ($this->config['default_policy'] ?? ApprovalPolicy::REQUESTER_OR_ROLE),
        );
        $this->audit = $audit;
        $this->idempotency = $idempotency;
        $this->metrics = $metrics ?? new ApprovalMetrics;
        $this->machine = new ApprovalStateMachine;
        $this->rowExecutor = new ApprovalExecutor(
            store: $this->store,
            metrics: $this->metrics,
            domainExecutor: $executor,
            revalidator: $revalidator,
            originalAuthorizer: $originalAuthorizer,
            idempotency: $idempotency,
            audit: $audit,
        );
    }

    public static function inMemory(?Clock $clock = null): self
    {
        $clock ??= new SystemClock;

        return new self(new InMemoryApprovalStore($clock), $clock);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function mergeConfig(array $config = []): array
    {
        $defaults = [
            'store' => 'database',
            'ttl_hours' => 24,
            'default_policy' => ApprovalPolicy::REQUESTER_OR_ROLE,
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'resume' => [
                'enabled' => true,
                'every_seconds' => 60,
                'grace_seconds' => 30,
                'stuck_after_seconds' => 300,
                'lease_seconds' => 120,
            ],
        ];

        $merged = array_replace_recursive($defaults, $config);
        if (isset($merged['execution'])) {
            $merged['execution'] = ApprovalStateMachine::normalizeExecution((string) $merged['execution']);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function validateConfig(array $config): array
    {
        $merged = self::mergeConfig($config);
        ApprovalStateMachine::normalizeExecution((string) $merged['execution']);

        return $merged;
    }

    public function store(): ApprovalStore
    {
        return $this->store;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function policy(): ApprovalPolicy
    {
        return $this->policy;
    }

    public function withPolicy(ApprovalPolicy $policy): self
    {
        $clone = clone $this;
        $clone->policy = $policy;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): self
    {
        $clone = clone $this;
        $clone->config = self::mergeConfig(array_replace_recursive($this->config, $config));

        return $clone;
    }

    public function withExecutor(?callable $executor): self
    {
        $clone = clone $this;
        $clone->rowExecutor = $this->rowExecutor->withDomainExecutor($executor);

        return $clone;
    }

    public function withRevalidator(?callable $revalidator): self
    {
        $clone = clone $this;
        $clone->rowExecutor = $this->rowExecutor->withRevalidator($revalidator);

        return $clone;
    }

    public function withOriginalAuthorizer(?callable $authorizer): self
    {
        $clone = clone $this;
        $clone->rowExecutor = $this->rowExecutor->withOriginalAuthorizer($authorizer);

        return $clone;
    }

    public function withAudit(?AuditWriter $audit): self
    {
        $clone = clone $this;
        $clone->audit = $audit;
        $clone->rowExecutor = $this->rowExecutor->withAudit($audit);

        return $clone;
    }

    public function withIdempotency(?IdempotencyStore $store): self
    {
        $clone = clone $this;
        $clone->idempotency = $store;
        $clone->rowExecutor = $this->rowExecutor->withIdempotency($store);

        return $clone;
    }

    public function addNotifier(ApprovalNotifier $notifier): self
    {
        $this->notifiers[] = $notifier;

        return $this;
    }

    public function metrics(): ApprovalMetrics
    {
        return $this->metrics;
    }

    public function runCount(): int
    {
        return $this->rowExecutor->runCount;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return array_merge($this->events, $this->rowExecutor->events);
    }

    public function machine(): ApprovalStateMachine
    {
        return $this->machine;
    }

    public function executionMode(): string
    {
        return (string) $this->config['execution'];
    }

    public function isDeferred(): bool
    {
        return $this->executionMode() === ApprovalStateMachine::EXECUTION_DEFERRED;
    }

    public function isAtomic(): bool
    {
        return $this->executionMode() === ApprovalStateMachine::EXECUTION_ATOMIC;
    }

    public function resumeEnabled(): bool
    {
        return (bool) ($this->config['resume']['enabled'] ?? true) && $this->isDeferred();
    }

    public function resumeEverySeconds(): int
    {
        return (int) ($this->config['resume']['every_seconds'] ?? 60);
    }

    public function graceSeconds(): int
    {
        return (int) ($this->config['resume']['grace_seconds'] ?? 30);
    }

    public function stuckAfterSeconds(): int
    {
        return (int) ($this->config['resume']['stuck_after_seconds'] ?? 300);
    }

    public function leaseSeconds(): int
    {
        return (int) ($this->config['resume']['lease_seconds'] ?? 120);
    }

    public function ttlHours(): int
    {
        return (int) ($this->config['ttl_hours'] ?? 24);
    }

    /**
     * Effective TTL hours: min(global, per-capability) when cap set.
     */
    public function effectiveTtlHours(?int $capabilityTtlHours = null): int
    {
        $global = $this->ttlHours();
        if ($capabilityTtlHours === null) {
            return $global;
        }

        return min($global, max(1, $capabilityTtlHours));
    }

    /**
     * Create a pending approval row. Does not call run().
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function request(array $record): array
    {
        $ttl = $this->effectiveTtlHours(
            isset($record['approval_ttl_hours']) ? (int) $record['approval_ttl_hours'] : null,
        );
        $now = $this->clock->now();
        if (! isset($record['expires_at'])) {
            $record['expires_at'] = $now->add(new DateInterval('PT'.($ttl * 3600).'S'))->format(DATE_ATOM);
        }
        $record['status'] = $record['status'] ?? ApprovalStateMachine::STATUS_PENDING;
        if (! array_key_exists('scope', $record)) {
            $record['scope'] = $record['tenant_id'] ?? null;
        }

        $row = $this->store->put($record);

        $this->auditWrite('approval.requested', [
            'approval_id' => $row['id'],
            'requester' => ($row['requester_actor_type'] ?? '').':'.($row['requester_actor_id'] ?? ''),
            'capability' => $row['capability_name'] ?? '',
            'input_redacted' => $this->redactInput($row['input_json'] ?? null),
            'idempotency_key' => $row['idempotency_key'] ?? null,
        ]);

        foreach ($this->notifiers as $notifier) {
            $notifier->notifyPending($row);
        }

        return $row;
    }

    /**
     * Lazy expiry on read + return current row.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $row = $this->store->find($id);
        if ($row === null) {
            return null;
        }

        return $this->expiry()->maybeExpire($row);
    }

    /**
     * Accept a pending (or recover approved) approval — exactly-once execution.
     *
     * @param  array<string, mixed>  $options  tenant_id?, reason?
     */
    public function accept(string $id, object $approver, array $options = []): CapabilityResult
    {
        $this->metrics->increment('approvals_accept_total', 1, ['result' => 'attempt']);

        $row = $this->find($id);
        if ($row === null) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'not_found']);

            return CapabilityResult::failure('not_found', 'Approval not found.');
        }

        $status = (string) $row['status'];

        if ($status === ApprovalStateMachine::STATUS_EXECUTED) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'replay']);
            $this->auditWrite('approval.replayed', [
                'approval_id' => $id,
                'result' => $row['result_json'] ?? null,
            ]);

            return $this->resultFromRow($row, replay: true);
        }

        if ($status === ApprovalStateMachine::STATUS_REJECTED) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'conflict']);

            return CapabilityResult::failure('conflict', 'Approval already rejected.');
        }

        if ($status === ApprovalStateMachine::STATUS_EXPIRED) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'expired']);

            return CapabilityResult::failure('expired', 'Approval has expired.', ['http_status' => 410]);
        }

        if ($status === ApprovalStateMachine::STATUS_APPROVED) {
            // Shape A: do not re-run; in-progress or resume owns stuck rows.
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'in_progress']);

            return CapabilityResult::failure(
                'conflict',
                'Approval already approved; execution in progress or awaiting resume.',
                ['in_progress' => true, 'approval_id' => $id],
            );
        }

        if ($status !== ApprovalStateMachine::STATUS_PENDING) {
            return CapabilityResult::failure('conflict', 'Approval is not pending.');
        }

        if (! $this->policy->allows($row, $approver, $options['tenant_id'] ?? $this->tenantOf($approver))) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'forbidden']);

            return CapabilityResult::failure('forbidden', 'Approver is not authorized for this approval.');
        }

        $now = $this->clock->now();
        $decidedBy = ResolveActor::actorId($approver);
        $leaseUntil = $now->add(new DateInterval('PT'.$this->leaseSeconds().'S'))->format(DATE_ATOM);
        $attempt = ((int) ($row['execution_attempt'] ?? 0)) + 1;

        if ($this->isDeferred()) {
            $updated = $this->store->claimLease(
                $id,
                ApprovalStateMachine::STATUS_PENDING,
                $now->format(DATE_ATOM),
                [
                    'status' => ApprovalStateMachine::STATUS_APPROVED,
                    'decided_by' => $decidedBy,
                    'decided_at' => $now->format(DATE_ATOM),
                    'approved_at' => $now->format(DATE_ATOM),
                    'decision_reason' => $options['reason'] ?? null,
                    'execution_lease_until' => $leaseUntil,
                    'execution_attempt' => $attempt,
                ],
            );

            if ($updated === null) {
                // Lost race — re-read and handle terminal/in-progress.
                return $this->accept($id, $approver, $options);
            }

            $this->emitDecided($updated, 'approved', $decidedBy, $options['reason'] ?? null);

            return $this->executeRow($updated, $approver, via: 'accept');
        }

        // Shape B — claim lease while status stays pending; flip to executed only after run.
        $locked = $this->store->claimLease(
            $id,
            ApprovalStateMachine::STATUS_PENDING,
            $now->format(DATE_ATOM),
            [
                'decided_by' => $decidedBy,
                'decided_at' => $now->format(DATE_ATOM),
                'decision_reason' => $options['reason'] ?? null,
                'execution_lease_until' => $leaseUntil,
                'execution_attempt' => $attempt,
                'approved_at' => $now->format(DATE_ATOM),
            ],
        );

        if ($locked === null) {
            return $this->accept($id, $approver, $options);
        }

        $this->emitDecided($locked, 'approved', $decidedBy, $options['reason'] ?? null);

        return $this->executeRow($locked, $approver, via: 'accept', fromStatus: ApprovalStateMachine::STATUS_PENDING);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function reject(string $id, object $approver, ?string $reason = null, array $options = []): CapabilityResult
    {
        $row = $this->find($id);
        if ($row === null) {
            return CapabilityResult::failure('not_found', 'Approval not found.');
        }

        $status = (string) $row['status'];

        if ($status === ApprovalStateMachine::STATUS_EXECUTED) {
            return CapabilityResult::failure('conflict', 'Approval already executed.');
        }

        if ($status === ApprovalStateMachine::STATUS_EXPIRED) {
            return CapabilityResult::failure('expired', 'Approval has expired.', ['http_status' => 410]);
        }

        if ($status === ApprovalStateMachine::STATUS_REJECTED) {
            // Terminal no-op — already rejected.
            return CapabilityResult::failure('conflict', 'Approval already rejected.', ['noop' => true]);
        }

        if ($status === ApprovalStateMachine::STATUS_APPROVED) {
            return CapabilityResult::failure('conflict', 'Approval already approved; cannot reject.');
        }

        if ($status !== ApprovalStateMachine::STATUS_PENDING) {
            return CapabilityResult::failure('conflict', 'Approval is not pending.');
        }

        if (! $this->policy->allows($row, $approver, $options['tenant_id'] ?? $this->tenantOf($approver))) {
            return CapabilityResult::failure('forbidden', 'Approver is not authorized for this approval.');
        }

        $now = $this->clock->now()->format(DATE_ATOM);
        $decidedBy = ResolveActor::actorId($approver);
        $updated = $this->store->compareAndUpdate($id, ApprovalStateMachine::STATUS_PENDING, [
            'status' => ApprovalStateMachine::STATUS_REJECTED,
            'decided_by' => $decidedBy,
            'decided_at' => $now,
            'decision_reason' => $reason,
        ]);

        if ($updated === null) {
            return $this->reject($id, $approver, $reason, $options);
        }

        $this->emitDecided($updated, 'rejected', $decidedBy, $reason);

        return CapabilityResult::failure(
            'rejected',
            'Approval rejected.',
            ['approval_id' => $id, 'decision_reason' => $reason],
        );
    }

    /**
     * Expire a single pending row past TTL (or force).
     *
     * @return array<string, mixed>|null
     */
    public function expire(string $id, bool $force = false): ?array
    {
        return $this->expiry()->expire($id, $force);
    }

    /**
     * Scheduled sweeper: expire all pending past TTL.
     */
    public function expirePending(): int
    {
        return $this->expiry()->expirePending();
    }

    /**
     * Resume stuck approved rows (Shape A) or a single id.
     *
     * Respects grace + lease unless `$force` (manual repair / artisan).
     *
     * @return list<CapabilityResult>
     */
    public function resume(?string $id = null, ?object $actor = null, bool $force = false): array
    {
        return $this->resumer()->resume($id, $actor, $force);
    }

    /**
     * Same code path as scheduler (artisan capabilities:approvals-resume).
     * Forces past grace for the targeted id (operator repair).
     *
     * @return list<CapabilityResult>
     */
    public function artisanResume(?string $id = null): array
    {
        return $this->resume($id, SystemActor::named('approval-resume'), force: true);
    }

    /**
     * Transition helper for pure SM tests.
     */
    public function assertCanTransition(string $from, string $to): bool
    {
        return ApprovalStateMachine::canTransition($from, $to);
    }

    /**
     * Collaborator for resume / grace / lease (built per call so with* stays consistent).
     */
    private function resumer(): ApprovalResumer
    {
        return new ApprovalResumer(
            store: $this->store,
            clock: $this->clock,
            policy: $this->policy,
            metrics: $this->metrics,
            executor: $this->rowExecutor,
            audit: $this->audit,
            graceSeconds: $this->graceSeconds(),
            leaseSeconds: $this->leaseSeconds(),
            stuckAfterSeconds: $this->stuckAfterSeconds(),
            atomic: $this->isAtomic(),
        );
    }

    /**
     * Collaborator for pending TTL expiry (built per call so with* stays consistent).
     */
    private function expiry(): ApprovalExpiry
    {
        return new ApprovalExpiry(
            store: $this->store,
            clock: $this->clock,
            audit: $this->audit,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function executeRow(
        array $row,
        object $actor,
        string $via,
        string $fromStatus = ApprovalStateMachine::STATUS_APPROVED,
    ): CapabilityResult {
        return $this->rowExecutor->execute($row, $actor, $via, $fromStatus);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resultFromRow(array $row, bool $replay): CapabilityResult
    {
        return $this->rowExecutor->resultFromRow($row, $replay);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function emitDecided(array $row, string $decision, string $decidedBy, ?string $reason): void
    {
        $this->events[] = new CapabilityApprovalDecided(
            capability: (string) ($row['capability_name'] ?? ''),
            approvalId: (string) $row['id'],
            decision: $decision,
            decidedBy: $decidedBy,
            reason: $reason,
        );
        $this->auditWrite('approval.decided', [
            'approval_id' => $row['id'],
            'decided_by' => $decidedBy,
            'decision' => $decision,
            'reason' => $reason,
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

    private function redactInput(mixed $input): mixed
    {
        if (! is_array($input)) {
            return $input;
        }

        $copy = $input;
        foreach (['password', 'secret', 'token', 'card_number'] as $k) {
            if (array_key_exists($k, $copy)) {
                $copy[$k] = '[redacted]';
            }
        }

        return $copy;
    }

    private function tenantOf(object $actor): ?string
    {
        if (isset($actor->tenant_id)) {
            return is_string($actor->tenant_id) ? $actor->tenant_id : (string) $actor->tenant_id;
        }

        return null;
    }
}
