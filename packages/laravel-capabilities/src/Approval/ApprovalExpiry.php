<?php

namespace Rawphp\Capabilities\Approval;

use DateTimeImmutable;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Clock;

/**
 * Pending-approval TTL expiry (D-006).
 *
 * Owns single-row expire, scheduled sweep, and lazy expiry on read.
 * Public API remains on {@see ApprovalManager}.
 */
final class ApprovalExpiry
{
    public function __construct(
        private ApprovalStore $store,
        private Clock $clock,
        private ?AuditWriter $audit,
    ) {}

    /**
     * Expire a single pending row past TTL (or force).
     *
     * @return array<string, mixed>|null
     */
    public function expire(string $id, bool $force = false): ?array
    {
        $row = $this->store->find($id);
        if ($row === null) {
            return null;
        }

        if ((string) $row['status'] !== ApprovalStateMachine::STATUS_PENDING) {
            return $row;
        }

        if (! $force && ! $this->isPastExpiry($row)) {
            return $row;
        }

        $updated = $this->store->compareAndUpdate($id, ApprovalStateMachine::STATUS_PENDING, [
            'status' => ApprovalStateMachine::STATUS_EXPIRED,
        ]);

        if ($updated !== null) {
            $this->auditWrite('approval.expired', ['approval_id' => $id]);
        }

        return $updated ?? $this->store->find($id);
    }

    /**
     * Scheduled sweeper: expire all pending past TTL.
     */
    public function expirePending(): int
    {
        $count = 0;
        foreach ($this->store->findByStatus(ApprovalStateMachine::STATUS_PENDING) as $row) {
            if ($this->isPastExpiry($row)) {
                $updated = $this->expire((string) $row['id'], force: true);
                if ($updated !== null && ($updated['status'] ?? null) === ApprovalStateMachine::STATUS_EXPIRED) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Lazy expiry on read.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function maybeExpire(array $row): array
    {
        if ((string) ($row['status'] ?? '') !== ApprovalStateMachine::STATUS_PENDING) {
            return $row;
        }

        if (! $this->isPastExpiry($row)) {
            return $row;
        }

        return $this->expire((string) $row['id'], force: true) ?? $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function isPastExpiry(array $row): bool
    {
        $expires = $row['expires_at'] ?? null;
        if (! is_string($expires) || $expires === '') {
            return false;
        }

        try {
            $exp = new DateTimeImmutable($expires);
        } catch (\Exception) {
            return false;
        }

        return $this->clock->now() >= $exp;
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
