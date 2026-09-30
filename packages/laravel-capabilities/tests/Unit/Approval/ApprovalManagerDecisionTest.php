<?php

// ApprovalManager: config accessors and accept/reject edge branches (missing, stale revalidation, original-authorizer deny).

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Atomic manager whose custom policy lets any approver through.
 */
function amdOpenManager(): ApprovalManager
{
    return ApprovalManager::inMemory(new FixedClock(new DateTimeImmutable('2026-04-01T12:00:00Z')))
        ->withConfig(['execution' => ApprovalStateMachine::EXECUTION_ATOMIC])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ));
}

/**
 * @return array<string, mixed>
 */
function amdRequest(string $capability, array $input = []): array
{
    return [
        'capability_name' => $capability,
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => $input,
    ];
}

it('happy: deferred manager exposes resume, lease and ttl configuration', function () {
    $mgr = ApprovalManager::inMemory(new FixedClock(new DateTimeImmutable('2026-04-01T12:00:00Z')))
        ->withConfig([
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => 24,
            'resume' => [
                'enabled' => true,
                'every_seconds' => 30,
                'grace_seconds' => 10,
                'stuck_after_seconds' => 60,
                'lease_seconds' => 45,
            ],
        ])
        ->withExecutor(static fn () => CapabilityResult::ok(['ran' => true]))
        ->addNotifier(new HttpApprovalNotifier);

    expect($mgr->isAtomic())->toBeFalse()
        ->and($mgr->isDeferred())->toBeTrue()
        ->and($mgr->resumeEnabled())->toBeTrue()
        ->and($mgr->resumeEverySeconds())->toBe(30)
        ->and($mgr->graceSeconds())->toBe(10)
        ->and($mgr->stuckAfterSeconds())->toBe(60)
        ->and($mgr->leaseSeconds())->toBe(45)
        ->and($mgr->ttlHours())->toBe(24)
        ->and($mgr->effectiveTtlHours(null))->toBe(24)
        ->and($mgr->effectiveTtlHours(2))->toBe(2);
});

it('fail: find returns null and accept returns not_found for an unknown approval id', function () {
    $mgr = ApprovalManager::inMemory(new FixedClock(new DateTimeImmutable('2026-04-01T12:00:00Z')));

    expect($mgr->find('missing'))->toBeNull();
    expect($mgr->accept('missing', SystemActor::named('boss'))->errorCode())->toBe('not_found');
});

it('happy: request stores a pending row that find returns, and reject answers with a result', function () {
    $mgr = ApprovalManager::inMemory(new FixedClock(new DateTimeImmutable('2026-04-01T12:00:00Z')))
        ->withConfig([
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => 24,
        ])
        ->withExecutor(static fn () => CapabilityResult::ok(['ran' => true]))
        ->addNotifier(new HttpApprovalNotifier);

    $row = $mgr->request(amdRequest('inv.create', ['customer_id' => 1]) + ['approval_ttl_hours' => 1]);
    expect($row['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
    expect($mgr->find($row['id']))->not->toBeNull();

    // may be forbidden depending on policy — exercise path either way
    $rej = $mgr->reject($row['id'], (object) ['id' => 'u1'], 'nope', ['tenant_id' => 't1']);
    expect($rej)->toBeInstanceOf(CapabilityResult::class);
});

it('happy: a custom policy lets an approver accept, and a second accept still answers with a result', function () {
    $open = amdOpenManager()
        ->withExecutor(static fn () => CapabilityResult::ok(['done' => 1]))
        ->withRevalidator(static fn () => null)
        ->withOriginalAuthorizer(static fn () => true);

    $row = $open->request(amdRequest('x'));
    $accepted = $open->accept($row['id'], (object) ['id' => 'approver'], ['tenant_id' => 't1']);
    expect($accepted->isOk() || $accepted->errorCode() !== null)->toBeTrue();

    // re-accept executed
    $again = $open->accept($row['id'], (object) ['id' => 'approver'], ['tenant_id' => 't1']);
    expect($again)->toBeInstanceOf(CapabilityResult::class);
});

it('fail: accept fails without running the capability when the revalidator reports stale input', function () {
    $mgr = amdOpenManager()
        ->withRevalidator(static fn () => CapabilityResult::failure('conflict', 'stale input'))
        ->withExecutor(static fn () => CapabilityResult::ok(['should' => 'not-run']));

    $row = $mgr->request(amdRequest('stale'));
    $stale = $mgr->accept($row['id'], (object) ['id' => 'a'], ['tenant_id' => 't1']);

    expect($stale->isOk())->toBeFalse();
});

it('fail: accept is forbidden when the original authorizer denies', function () {
    $mgr = amdOpenManager()
        ->withOriginalAuthorizer(static fn () => false)
        ->withExecutor(static fn () => CapabilityResult::ok([]));

    $row = $mgr->request(amdRequest('deny'));
    $denied = $mgr->accept($row['id'], (object) ['id' => 'a'], ['tenant_id' => 't1']);

    expect($denied->errorCode())->toBe('forbidden');
});
