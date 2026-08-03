<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\ResumeApprovedApprovals;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for Approval/* unit tests (D-006 / P2-004).
 */
final class ApprovalHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     manager: ApprovalManager,
     *     store: InMemoryApprovalStore,
     *     clock: FixedClock,
     *     audit: InMemoryAuditWriter,
     *     idempotency: InMemoryIdempotencyStore,
     *     runCount: stdClass,
     *     resume: ResumeApprovedApprovals,
     *     fakes: SharedFakes
     * }
     */
    public static function harness(array $opts = []): array
    {
        $now = $opts['now'] ?? new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $clock = $opts['clock'] ?? new FixedClock($now instanceof DateTimeImmutable ? $now : new DateTimeImmutable((string) $now));
        $store = $opts['store'] ?? new InMemoryApprovalStore($clock);
        $audit = $opts['audit'] ?? new InMemoryAuditWriter($clock);
        $idempotency = $opts['idempotency'] ?? new InMemoryIdempotencyStore($clock);

        $runCount = new stdClass;
        $runCount->value = 0;
        $runCount->sideEffect = false;
        $runCount->inputs = [];

        $stale = $opts['stale'] ?? false;
        $authFail = $opts['auth_fail'] ?? false;
        $domainFail = $opts['domain_fail'] ?? false;
        $revalidateFailStep = $opts['revalidate_fail'] ?? null;

        $executor = $opts['executor'] ?? function (array $row, object $actor) use ($runCount, $domainFail, $opts) {
            $runCount->value++;
            $runCount->sideEffect = true;
            $runCount->inputs[] = $row;
            if ($domainFail) {
                return CapabilityResult::failure('domain_error', 'Domain failed.');
            }
            if (isset($opts['run_output'])) {
                return $opts['run_output'];
            }

            return CapabilityResult::ok([
                'invoice_id' => 42,
                'approval_id' => $row['id'] ?? null,
                'original_caller' => $row['original_caller'] ?? null,
                'tenant_id' => $row['tenant_id'] ?? null,
            ]);
        };

        $revalidator = $opts['revalidator'] ?? function (array $row) use ($stale, $revalidateFailStep) {
            if ($stale) {
                return CapabilityResult::failure('failed_stale', 'Resource stale.');
            }
            if ($revalidateFailStep !== null) {
                return CapabilityResult::failure('validation_failed', 'Revalidation failed at '.$revalidateFailStep, [
                    'step' => $revalidateFailStep,
                ]);
            }
            $input = $row['input_json'] ?? null;
            if (is_array($input) && ($input['__stale'] ?? false) === true) {
                return CapabilityResult::failure('failed_stale', (string) ($input['__reason'] ?? 'stale'));
            }

            return null;
        };

        $originalAuthorizer = $opts['original_authorizer'] ?? function (array $row) use ($authFail) {
            if ($authFail) {
                return false;
            }
            $input = $row['input_json'] ?? null;
            if (is_array($input) && ($input['__auth_fail'] ?? false) === true) {
                return false;
            }

            return true;
        };

        $policy = $opts['policy'] ?? null;
        if (is_string($policy)) {
            $policy = ApprovalPolicy::fromString(
                $policy,
                customChecker: $opts['custom_checker'] ?? null,
                roleChecker: $opts['role_checker'] ?? null,
                staffChecker: $opts['staff_checker'] ?? null,
            );
        }
        if (! $policy instanceof ApprovalPolicy) {
            $policy = ApprovalPolicy::fromString(
                ApprovalPolicy::REQUESTER_OR_ROLE,
                roleChecker: $opts['role_checker'] ?? null,
            );
        }

        $config = array_merge([
            'execution' => $opts['execution'] ?? ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => $opts['ttl_hours'] ?? 24,
            'default_policy' => $policy->policy(),
            'resume' => array_merge([
                'enabled' => $opts['resume_enabled'] ?? true,
                'every_seconds' => $opts['every_seconds'] ?? 60,
                'grace_seconds' => $opts['grace_seconds'] ?? 30,
                'stuck_after_seconds' => $opts['stuck_after_seconds'] ?? 300,
                'lease_seconds' => $opts['lease_seconds'] ?? 120,
            ], $opts['resume'] ?? []),
        ], $opts['config'] ?? []);

        $manager = new ApprovalManager(
            store: $store,
            clock: $clock,
            config: $config,
            policy: $policy,
            executor: $executor,
            revalidator: $revalidator,
            originalAuthorizer: $originalAuthorizer,
            audit: $audit,
            idempotency: $idempotency,
        );

        $fakes = SharedFakes::create(clock: $clock);

        return [
            'manager' => $manager,
            'store' => $store,
            'clock' => $clock,
            'audit' => $audit,
            'idempotency' => $idempotency,
            'runCount' => $runCount,
            'resume' => new ResumeApprovedApprovals($manager),
            'fakes' => $fakes,
            'policy' => $policy,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function pendingRecord(array $extra = []): array
    {
        return array_merge([
            'capability_name' => 'create-invoice',
            'status' => ApprovalStateMachine::STATUS_PENDING,
            'tenant_id' => 't-1',
            'scope' => 't-1',
            'requester_actor_type' => 'user',
            'requester_actor_id' => '7',
            'original_caller' => 'http',
            'input_json' => PipelineHelpers::validInput(),
            'input_hash' => 'hash-1',
            'idempotency_key' => 'idem-1',
            'messaging' => null,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{manager: ApprovalManager, row: array<string, mixed>, runCount: stdClass, store: InMemoryApprovalStore, clock: FixedClock, audit: InMemoryAuditWriter, idempotency: InMemoryIdempotencyStore, resume: ResumeApprovedApprovals}
     */
    public static function withPending(array $opts = []): array
    {
        $h = self::harness($opts);
        $record = self::pendingRecord($opts['record'] ?? []);
        if (isset($opts['approval_ttl_hours'])) {
            $record['approval_ttl_hours'] = $opts['approval_ttl_hours'];
        }
        $row = $h['manager']->request($record);

        return array_merge($h, ['row' => $row]);
    }

    public static function requester(int|string $id = 7, string $tenant = 't-1'): object
    {
        $u = new stdClass;
        $u->id = $id;
        $u->tenant_id = $tenant;
        $u->name = 'requester';
        $u->is_staff = true;

        return $u;
    }

    public static function roleHolder(string $role = 'finance-approver', string $tenant = 't-1', int|string $id = 99): object
    {
        $u = new stdClass;
        $u->id = $id;
        $u->tenant_id = $tenant;
        $u->role = $role;
        $u->roles = [$role, 'approver'];
        $u->is_staff = true;
        $u->name = 'role-holder';

        return $u;
    }

    public static function randomUser(string $tenant = 't-1', int|string $id = 55): object
    {
        $u = new stdClass;
        $u->id = $id;
        $u->tenant_id = $tenant;
        $u->is_staff = true;
        $u->name = 'random';

        return $u;
    }

    public static function otherTenantUser(int|string $id = 88): object
    {
        return self::randomUser('t-other', $id);
    }

    public static function system(string $name = 'billing-worker'): SystemActor
    {
        return SystemActor::named($name);
    }

    public static function actorFor(string $kind, array $row = []): object
    {
        return match ($kind) {
            'requester', 'requester_actor' => self::requester((string) ($row['requester_actor_id'] ?? '7'), (string) ($row['tenant_id'] ?? 't-1')),
            'approver_role', 'role_holder' => self::roleHolder(),
            'random', 'random_user' => self::randomUser(),
            'system', 'system_actor' => self::system(),
            'other_tenant', 'other_tenant_user' => self::otherTenantUser(),
            default => self::randomUser(),
        };
    }

    public static function advance(FixedClock $clock, int $seconds): void
    {
        $clock->advance(new DateInterval('PT'.$seconds.'S'));
    }

    public static function advanceHours(FixedClock $clock, int $hours): void
    {
        $clock->advance(new DateInterval('PT'.($hours * 3600).'S'));
    }

    /**
     * Seed a row directly in a given status (for matrix tests).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function seedStatus(ApprovalManager $manager, string $status, array $extra = []): array
    {
        $record = self::pendingRecord($extra);
        $row = $manager->request($record);
        if ($status === ApprovalStateMachine::STATUS_PENDING) {
            return $row;
        }

        $attrs = ['status' => $status];
        if ($status === ApprovalStateMachine::STATUS_APPROVED) {
            $attrs['approved_at'] = $manager->clock()->now()->format(DATE_ATOM);
            $attrs['decided_at'] = $attrs['approved_at'];
            $attrs['decided_by'] = '7';
        }
        if ($status === ApprovalStateMachine::STATUS_EXECUTED) {
            $attrs['result_status'] = 'ok';
            $attrs['result_json'] = CapabilityResult::ok(['invoice_id' => 1])->toArray();
            $attrs['decided_by'] = '7';
            $attrs['decided_at'] = $manager->clock()->now()->format(DATE_ATOM);
        }
        if ($status === ApprovalStateMachine::STATUS_REJECTED) {
            $attrs['decided_by'] = '7';
            $attrs['decided_at'] = $manager->clock()->now()->format(DATE_ATOM);
            $attrs['decision_reason'] = 'nope';
        }

        return $manager->store()->update((string) $row['id'], $attrs) ?? $row;
    }
}
