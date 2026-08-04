<?php

namespace Rawphp\Capabilities\Approval;

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Resume stuck approved approvals (D-006 / P2-004 Shape A).
 *
 * Owns grace/lease gating, lease re-claim, and resume metrics. Public API
 * remains on {@see ApprovalManager}; domain execution still goes through
 * {@see ApprovalExecutor}.
 */
final class ApprovalResumer
{
    public function __construct(
        private ApprovalStore $store,
        private Clock $clock,
        private ApprovalPolicy $policy,
        private ApprovalMetrics $metrics,
        private ApprovalExecutor $executor,
        private ?AuditWriter $audit,
        private int $graceSeconds,
        private int $leaseSeconds,
        private int $stuckAfterSeconds,
        private bool $atomic,
    ) {}

    /**
     * Resume stuck approved rows (Shape A) or a single id.
     *
     * Respects grace + lease unless `$force` (manual repair / artisan).
     *
     * @return list<CapabilityResult>
     */
    public function resume(?string $id = null, ?object $actor = null, bool $force = false): array
    {
        if ($this->atomic && $id === null && ! $force) {
            // Atomic mode: resume job is a no-op when sweeping.
            return [];
        }

        $actor ??= SystemActor::named('approval-resume');
        $results = [];

        if ($id !== null) {
            $row = $this->store->find($id);
            if ($row === null) {
                return [CapabilityResult::failure('not_found', 'Approval not found.')];
            }

            $results[] = $this->resumeOne($row, $actor, force: $force);

            return $results;
        }

        foreach ($this->store->findByStatus(ApprovalStateMachine::STATUS_APPROVED) as $row) {
            $results[] = $this->resumeOne($row, $actor, force: $force);
        }

        $this->sampleStuckMetric();

        return $results;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resumeOne(array $row, object $actor, bool $force): CapabilityResult
    {
        $id = (string) $row['id'];
        $status = (string) $row['status'];

        if ($status === ApprovalStateMachine::STATUS_EXECUTED) {
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'replay']);

            return $this->executor->resultFromRow($row, replay: true);
        }

        if ($status === ApprovalStateMachine::STATUS_PENDING) {
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'skipped_lease']);

            return CapabilityResult::failure('conflict', 'Resume skips pending rows.', ['skipped' => true]);
        }

        if ($status !== ApprovalStateMachine::STATUS_APPROVED) {
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'skipped_lease']);

            return CapabilityResult::failure('conflict', 'Resume only applies to approved rows.', ['skipped' => true]);
        }

        // Tenant guard when actor is a user.
        if (! ($actor instanceof SystemActor)) {
            $tenant = self::tenantOf($actor);
            $rowTenant = $row['tenant_id'] ?? null;
            if ($rowTenant !== null && $tenant !== null && $tenant !== $rowTenant) {
                return CapabilityResult::failure('forbidden', 'Resume actor tenant mismatch.');
            }
            // Random users without role/requester: deny for decision matrix.
            if (! $this->policy->allows($row, $actor, $tenant) && ResolveActor::actorId($actor) !== (string) ($row['requester_actor_id'] ?? '')) {
                // Requester may force-resume as repair; role holders too via policy.
                $isRequester = ResolveActor::actorId($actor) === (string) ($row['requester_actor_id'] ?? '');
                if (! $isRequester) {
                    return CapabilityResult::failure('forbidden', 'Resume actor not allowed.');
                }
            }
        }

        if (! $force && ! $this->isPastGrace($row)) {
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'skipped_lease']);

            return CapabilityResult::failure('conflict', 'Inside grace; live accept may still be in flight.', [
                'skipped' => true,
                'inside_grace' => true,
            ]);
        }

        if (! $force && ! $this->leaseIsFreeOrExpired($row)) {
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'skipped_lease']);

            return CapabilityResult::failure('conflict', 'Execution lease still held.', [
                'skipped' => true,
                'lease_held' => true,
            ]);
        }

        $now = $this->clock->now();
        $leaseUntil = $now->add(new DateInterval('PT'.$this->leaseSeconds.'S'))->format(DATE_ATOM);
        $attempt = ((int) ($row['execution_attempt'] ?? 0)) + 1;

        $claimed = $this->store->claimLease(
            $id,
            ApprovalStateMachine::STATUS_APPROVED,
            $now->format(DATE_ATOM),
            [
                'execution_lease_until' => $leaseUntil,
                'execution_attempt' => $attempt,
            ],
        );

        if ($claimed === null) {
            $fresh = $this->store->find($id);
            if ($fresh !== null && ($fresh['status'] ?? null) === ApprovalStateMachine::STATUS_EXECUTED) {
                return $this->executor->resultFromRow($fresh, replay: true);
            }
            $this->metrics->increment('approvals_resume_total', 1, ['result' => 'skipped_lease']);

            return CapabilityResult::failure('conflict', 'Failed to claim resume lease.', ['skipped' => true]);
        }

        $this->auditWrite('approval.resume', [
            'approval_id' => $id,
            'attempt' => $attempt,
        ]);

        return $this->executor->execute($claimed, $actor, via: 'resume', fromStatus: ApprovalStateMachine::STATUS_APPROVED);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isPastGrace(array $row): bool
    {
        $approvedAt = $row['approved_at'] ?? $row['decided_at'] ?? null;
        if (! is_string($approvedAt) || $approvedAt === '') {
            return true;
        }

        try {
            $at = new DateTimeImmutable($approvedAt);
        } catch (\Exception) {
            return true;
        }

        $graceEnd = $at->add(new DateInterval('PT'.$this->graceSeconds.'S'));

        return $this->clock->now() >= $graceEnd;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function leaseIsFreeOrExpired(array $row): bool
    {
        $lease = $row['execution_lease_until'] ?? null;
        if ($lease === null || $lease === '') {
            return true;
        }

        try {
            $until = new DateTimeImmutable((string) $lease);
        } catch (\Exception) {
            return true;
        }

        return $this->clock->now() >= $until;
    }

    public function sampleStuckMetric(): void
    {
        $stuck = 0;
        $threshold = $this->stuckAfterSeconds;
        foreach ($this->store->findByStatus(ApprovalStateMachine::STATUS_APPROVED) as $row) {
            $approvedAt = $row['approved_at'] ?? $row['decided_at'] ?? null;
            if (! is_string($approvedAt)) {
                continue;
            }
            try {
                $at = new DateTimeImmutable($approvedAt);
            } catch (\Exception) {
                continue;
            }
            $age = $this->clock->now()->getTimestamp() - $at->getTimestamp();
            if ($age >= $threshold) {
                $stuck++;
            }
        }
        $this->metrics->set('approvals_stuck_approved_total', $stuck);
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

    private static function tenantOf(object $actor): ?string
    {
        if (isset($actor->tenant_id)) {
            return is_string($actor->tenant_id) ? $actor->tenant_id : (string) $actor->tenant_id;
        }

        return null;
    }
}
