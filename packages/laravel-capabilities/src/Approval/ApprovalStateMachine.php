<?php

namespace Rawphp\Capabilities\Approval;

use InvalidArgumentException;

/**
 * Approval status transitions and algorithm step lists (D-006 / P2-004).
 *
 * Shape A (deferred): pending → approved → executed
 * Shape B (atomic):   pending → executed (under lock)
 */
final class ApprovalStateMachine
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXECUTED = 'executed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_EXECUTED,
    ];

    public const EXECUTION_DEFERRED = 'deferred';

    public const EXECUTION_ATOMIC = 'atomic';

    /**
     * Exactly-once accept algorithm steps (normative order from D-006).
     *
     * @var list<string>
     */
    public const ACCEPT_STEPS = [
        'begin_transaction',
        'lock_approval_row',
        'if_executed_replay',
        'if_rejected_conflict',
        'if_expired_gone',
        'if_approved_join_or_in_progress',
        'if_pending_shape_a_set_approved',
        'if_pending_shape_b_run_under_lock',
        'revalidate',
        'authorize_original',
        'run_once',
        'set_executed_result',
        'commit',
        'complete_idempotency',
    ];

    /**
     * Resume algorithm steps (P2-004).
     *
     * @var list<string>
     */
    public const RESUME_STEPS = [
        'select_approved_past_grace_free_lease',
        'claim_lease_conditional',
        'revalidate',
        'scoped_resolve',
        'run_once_or_stale_fail',
        'set_executed',
        'complete_idempotency',
        'emit_metrics',
    ];

    /**
     * Re-validation steps on accept (D-006).
     *
     * @var list<string>
     */
    public const REVALIDATION_STEPS = [
        'json_schema',
        'server_only_rules',
        'scoped_resolve_each_resource',
        'authorize_original_actor',
    ];

    /**
     * Allowed transitions keyed by from → list of to.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        self::STATUS_PENDING => [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_EXECUTED, // Shape B
        ],
        self::STATUS_APPROVED => [
            self::STATUS_EXECUTED, // Shape A + resume
        ],
        self::STATUS_REJECTED => [],
        self::STATUS_EXPIRED => [],
        self::STATUS_EXECUTED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if (! in_array($from, self::STATUSES, true) || ! in_array($to, self::STATUSES, true)) {
            return false;
        }

        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                'Forbidden approval transition %s → %s (D-006).',
                $from,
                $to,
            ));
        }
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, [
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_EXECUTED,
        ], true);
    }

    public static function acceptIncludesStep(string $step): bool
    {
        return in_array($step, self::ACCEPT_STEPS, true);
    }

    public static function resumeIncludesStep(string $step): bool
    {
        return in_array($step, self::RESUME_STEPS, true);
    }

    public static function revalidationIncludesStep(string $step): bool
    {
        return in_array($step, self::REVALIDATION_STEPS, true);
    }

    /**
     * @return list<string>
     */
    public static function acceptSteps(): array
    {
        return self::ACCEPT_STEPS;
    }

    /**
     * @return list<string>
     */
    public static function resumeSteps(): array
    {
        return self::RESUME_STEPS;
    }

    /**
     * @return list<string>
     */
    public static function revalidationSteps(): array
    {
        return self::REVALIDATION_STEPS;
    }

    public static function normalizeExecution(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (! in_array($mode, [self::EXECUTION_DEFERRED, self::EXECUTION_ATOMIC], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid approval.execution "%s"; expected deferred|atomic.',
                $mode,
            ));
        }

        return $mode;
    }
}
