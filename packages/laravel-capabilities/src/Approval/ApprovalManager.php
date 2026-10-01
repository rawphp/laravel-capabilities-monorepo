<?php

namespace Rawphp\Capabilities\Approval;

use DateInterval;
use Illuminate\Contracts\Events\Dispatcher;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FailureReporter;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\SystemClock;
use Throwable;

/**
 * High-level approval API: request, accept, reject, expire, resume (D-006 / P2-004).
 *
 * Owns the state machine; channel adapters only notify — never execute.
 * Implements {@see ApprovalGateway} so sibling packages type-hint the port, not this class.
 * Domain execution after lease claim lives in {@see ApprovalExecutor}.
 * Stuck-row resume lives in {@see ApprovalResumer}.
 * Pending TTL expiry lives in {@see ApprovalExpiry}.
 */
final class ApprovalManager implements ApprovalGateway
{
    private Clock $clock;

    /** @var array<string, mixed> */
    private array $config;

    private ApprovalPolicy $policy;

    private ApprovalMetrics $metrics;

    private ApprovalStateMachine $machine;

    private ApprovalExecutor $rowExecutor;

    /** Places an approver in a tenant with the same resolver that stamped the row (M-301 / D-003). */
    private ResolveTenantFromCaller $resolveTenant;

    /** @var list<object> */
    private array $events = [];

    /** @var list<ApprovalNotifier> */
    private array $notifiers = [];

    private ?AuditWriter $audit;

    private ?Dispatcher $dispatcher = null;

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
        ?ApprovalMetrics $metrics = null,
        ?ScopeResolver $scopeResolver = null,
    ) {
        $this->clock = $clock ?? new SystemClock;
        $this->resolveTenant = new ResolveTenantFromCaller($scopeResolver);
        $this->config = self::mergeConfig($config);
        $this->policy = $policy ?? ApprovalPolicy::fromString(
            (string) ($this->config['default_policy'] ?? ApprovalPolicy::REQUESTER_OR_ROLE),
        );
        $this->audit = $audit;
        $this->metrics = $metrics ?? new ApprovalMetrics;
        $this->machine = new ApprovalStateMachine;
        $this->rowExecutor = new ApprovalExecutor(
            store: $this->store,
            metrics: $this->metrics,
            domainExecutor: $executor,
            revalidator: $revalidator,
            originalAuthorizer: $originalAuthorizer,
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
        // A misspelt global policy must fail boot, not deny (or allow) every approver at runtime (L-106).
        ApprovalPolicy::assertKnown((string) $merged['default_policy'], 'approval.default_policy');

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

    /**
     * Resolve approvers with the host's ScopeResolver — the one the registry stamps rows with
     * (D-003 / M-301). Null means the package default resolver.
     */
    public function withScopeResolver(?ScopeResolver $resolver): self
    {
        $clone = clone $this;
        $clone->resolveTenant = new ResolveTenantFromCaller($resolver);

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

    /**
     * Host event dispatcher for CapabilityApprovalDecided / CapabilityApprovalExecuted
     * (D-010 §5 / L-007). Null keeps events in the in-memory {@see events()} list only.
     */
    public function withEventDispatcher(?Dispatcher $dispatcher): self
    {
        $clone = clone $this;
        $clone->dispatcher = $dispatcher;
        $clone->rowExecutor = $this->rowExecutor->withEventDispatcher($dispatcher);

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

        // The row is saved: a notifier is a side channel and cannot change the outcome.
        // Report its failure and keep notifying the rest (L-201 / L-103).
        foreach ($this->notifiers as $notifier) {
            try {
                $notifier->notifyPending($row);
            } catch (Throwable $e) {
                FailureReporter::reportAndCount($e, FailureReporter::APPROVAL_NOTIFY_FAILED, ['notifier' => $notifier::class]);
            }
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
     * The approver's tenant comes from the ScopeResolver, like the row's (D-003); a trusted
     * `tenant_id` option only fills in when the approver has no membership tenant.
     *
     * @param  array<string, mixed>  $options  tenant_id?, reason?, decided_via?
     */
    public function accept(string $id, object $approver, array $options = []): CapabilityResult
    {
        $this->metrics->increment('approvals_accept_total', 1, ['result' => 'attempt']);

        $row = $this->find($id);
        if ($row === null) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'not_found']);

            return CapabilityResult::failure('not_found', 'Approval not found.');
        }

        // Scope before status: a replay or terminal status must not leak to an out-of-policy caller.
        // The row carries the capability's declared policy (D-006); the manager's is the fallback.
        if (! $this->policy->forRow($row)->allows($row, $approver, $this->approverTenant($approver, $options))) {
            $this->metrics->increment('approvals_accept_total', 1, ['result' => 'forbidden']);

            return CapabilityResult::failure('forbidden', 'Approver is not authorized for this approval.');
        }

        $blocked = $this->notAcceptable($row);
        if ($blocked !== null) {
            return $blocked;
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
                return $this->lostAcceptRace($id);
            }

            $this->emitDecided($updated, 'approved', $decidedBy, $options['reason'] ?? null, $options);

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
            return $this->lostAcceptRace($id);
        }

        $this->emitDecided($locked, 'approved', $decidedBy, $options['reason'] ?? null, $options);

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

        // Scope before status, same as accept: a terminal row must not leak to an out-of-policy caller.
        if (! $this->policy->forRow($row)->allows($row, $approver, $this->approverTenant($approver, $options))) {
            return CapabilityResult::failure('forbidden', 'Approver is not authorized for this approval.');
        }

        $blocked = $this->notRejectable($row);
        if ($blocked !== null) {
            return $blocked;
        }

        if ($this->leaseHeld($row)) {
            return $this->inProgress($id, 'Approval execution is in progress; it can no longer be rejected.');
        }

        $now = $this->clock->now()->format(DATE_ATOM);
        $decidedBy = ResolveActor::actorId($approver);
        // Lease-aware: a racing accept that just claimed the row (Shape B) keeps status pending
        // while run() executes — the conditional update must not flip it to rejected.
        $updated = $this->store->claimLease($id, ApprovalStateMachine::STATUS_PENDING, $now, [
            'status' => ApprovalStateMachine::STATUS_REJECTED,
            'decided_by' => $decidedBy,
            'decided_at' => $now,
            'decision_reason' => $reason,
        ]);

        if ($updated === null) {
            $fresh = $this->find($id);
            if ($fresh === null) {
                return CapabilityResult::failure('not_found', 'Approval not found.');
            }

            return $this->notRejectable($fresh)
                ?? $this->inProgress($id, 'Approval execution is in progress; it can no longer be rejected.');
        }

        $this->emitDecided($updated, 'rejected', $decidedBy, $reason, $options);

        return CapabilityResult::failure(
            'rejected',
            'Approval rejected.',
            ['approval_id' => $id, 'decision_reason' => $reason],
        );
    }

    /**
     * Terminal / in-progress outcomes for accept; null only when the row is pending with a
     * free lease. A pending row with a live lease is a Shape B run in flight (D-006) —
     * report in_progress, never re-enter accept.
     *
     * @param  array<string, mixed>  $row
     */
    private function notAcceptable(array $row): ?CapabilityResult
    {
        $id = (string) $row['id'];
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
            return $this->inProgress($id, 'Approval already approved; execution in progress or awaiting resume.');
        }

        if ($status !== ApprovalStateMachine::STATUS_PENDING) {
            return CapabilityResult::failure('conflict', 'Approval is not pending.');
        }

        if ($this->leaseHeld($row)) {
            return $this->inProgress($id, 'Approval execution is in progress.');
        }

        return null;
    }

    /**
     * Terminal outcomes for reject; null when the row is still pending.
     *
     * @param  array<string, mixed>  $row
     */
    private function notRejectable(array $row): ?CapabilityResult
    {
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

        return null;
    }

    /**
     * The conditional lease claim lost to a concurrent accept/reject/resume: settle from
     * one re-read (terminal → that outcome; still pending → in progress). No recursion.
     */
    private function lostAcceptRace(string $id): CapabilityResult
    {
        $fresh = $this->find($id);
        if ($fresh === null) {
            return CapabilityResult::failure('not_found', 'Approval not found.');
        }

        return $this->notAcceptable($fresh) ?? $this->inProgress($id, 'Approval execution is in progress.');
    }

    private function inProgress(string $id, string $message): CapabilityResult
    {
        $this->metrics->increment('approvals_accept_total', 1, ['result' => 'in_progress']);

        return CapabilityResult::failure('conflict', $message, ['in_progress' => true, 'approval_id' => $id]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function leaseHeld(array $row): bool
    {
        $lease = $row['execution_lease_until'] ?? null;
        if (! is_string($lease) || $lease === '') {
            return false;
        }

        try {
            return $this->clock->now() < new \DateTimeImmutable($lease);
        } catch (\Exception) {
            return false;
        }
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
     * Same force path as scheduled resume / {@see ResumeApprovedApprovals::artisan()}.
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
            resolveTenant: $this->resolveTenant,
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
     * @param  array<string, mixed>  $options
     */
    private function emitDecided(array $row, string $decision, string $decidedBy, ?string $reason, array $options): void
    {
        $decided = new CapabilityApprovalDecided(
            capability: (string) ($row['capability_name'] ?? ''),
            approvalId: (string) $row['id'],
            decision: $decision,
            decidedBy: $decidedBy,
            reason: $reason,
        );
        $this->events[] = $decided;
        $this->dispatch($decided);
        $this->auditWrite('approval.decided', [
            'approval_id' => $row['id'],
            'decided_by' => $decidedBy,
            'decision' => $decision,
            'reason' => $reason,
        ] + $this->decidedVia($options));
    }

    /**
     * Surface-supplied channel identity of the approver (e.g. the Telegram user
     * that tapped Accept). Only string channel fields are kept; set by server-side
     * adapters, never forwarded from client input.
     *
     * @param  array<string, mixed>  $options
     * @return array{decided_via?: array{channel: string, channel_user_id?: string}}
     */
    private function decidedVia(array $options): array
    {
        $via = $options['decided_via'] ?? null;
        if (! is_array($via) || ! is_string($via['channel'] ?? null) || $via['channel'] === '') {
            return [];
        }

        $clean = ['channel' => $via['channel']];
        if (is_string($via['channel_user_id'] ?? null) && $via['channel_user_id'] !== '') {
            $clean['channel_user_id'] = $via['channel_user_id'];
        }

        return ['decided_via' => $clean];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function auditWrite(string $event, array $payload): void
    {
        if ($this->audit === null) {
            return;
        }

        // The row has already changed state; a failed audit insert is reported, never
        // thrown out of request()/accept()/reject() (L-104 / D-010 best_effort).
        try {
            $this->audit->write(array_merge(['event' => $event], $payload));
        } catch (Throwable $e) {
            FailureReporter::reportAndCount($e, FailureReporter::AUDIT_WRITE_FAILED, ['mode' => 'approval']);
        }
    }

    private function dispatch(object $event): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        try {
            $this->dispatcher->dispatch($event);
        } catch (Throwable $e) {
            // Listener failures never abort a decision already persisted (L-103).
            FailureReporter::reportAndCount($e, FailureReporter::LISTENER_FAILED, ['event' => $event::class]);
        }
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

    /**
     * @param  array<string, mixed>  $options
     */
    private function approverTenant(object $approver, array $options): ?string
    {
        $trusted = $options['tenant_id'] ?? null;
        // A chat-decided approval reaches the bus the way messaging invokes do (caller `agent`).
        $caller = isset($options['decided_via']['channel']) ? 'agent' : 'http';

        return $this->resolveTenant->tenantOfPrincipal(
            $approver,
            is_string($trusted) || is_int($trusted) ? (string) $trusted : null,
            $caller,
        );
    }
}
