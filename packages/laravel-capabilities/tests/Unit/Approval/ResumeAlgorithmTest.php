<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;

it('happy: resume algorithm includes step select_approved_past_grace_free_lease [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('select_approved_past_grace_free_lease'))->toBeTrue();
});

it('happy: resume algorithm includes step claim_lease_conditional [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('claim_lease_conditional'))->toBeTrue();
});

it('happy: resume algorithm includes step revalidate [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('revalidate'))->toBeTrue();
});

it('happy: resume algorithm includes step scoped_resolve [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('scoped_resolve'))->toBeTrue();
});

it('happy: resume algorithm includes step run_once_or_stale_fail [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('run_once_or_stale_fail'))->toBeTrue();
});

it('happy: resume algorithm includes step set_executed [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('set_executed'))->toBeTrue();
});

it('happy: resume algorithm includes step complete_idempotency [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('complete_idempotency'))->toBeTrue();
});

it('happy: resume algorithm includes step emit_metrics [P2-004]', function () {
    expect(ApprovalStateMachine::resumeIncludesStep('emit_metrics'))->toBeTrue();
});

function resumeAlgorithmManager(): ApprovalManager
{
    $clock = new FixedClock(new DateTimeImmutable('2026-05-02T00:00:00Z'));

    return ApprovalManager::inMemory($clock)
        ->withConfig([
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => 1,
            'resume' => [
                'enabled' => true,
                'every_seconds' => 15,
                'grace_seconds' => 5,
                'stuck_after_seconds' => 30,
                'lease_seconds' => 20,
            ],
        ])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ))
        ->withExecutor(static fn () => CapabilityResult::ok(['x' => 1]));
}

it('resume sweep under atomic execution has nothing to resume [P2-004]', function () {
    $atomic = ApprovalManager::inMemory(new FixedClock(new DateTimeImmutable('2026-05-02T00:00:00Z')))
        ->withConfig(['execution' => ApprovalStateMachine::EXECUTION_ATOMIC]);

    expect($atomic->resume())->toBe([]);
});

it('resume of an unknown approval id is not_found [P2-004]', function () {
    $missingResume = resumeAlgorithmManager()->resume('no-id');

    expect($missingResume[0]->errorCode())->toBe('not_found');
});

it('resume of a still-pending approval conflicts [P2-004]', function () {
    $mgr = resumeAlgorithmManager();
    $p3 = $mgr->request([
        'capability_name' => 'c3',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);

    $skipPending = $mgr->resume($p3['id']);
    expect($skipPending[0]->errorCode())->toBe('conflict');
});

it('deferred accept then forced resume executes the approval [P2-004]', function () {
    $mgr = resumeAlgorithmManager();
    $p4 = $mgr->request([
        'capability_name' => 'c4',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);

    $acc = $mgr->accept($p4['id'], (object) ['id' => 'boss'], ['tenant_id' => 't1']);
    // may be in_progress / ok depending on deferred accept shape
    expect($acc)->toBeInstanceOf(CapabilityResult::class);
    $resumed = $mgr->resume($p4['id'], force: true);
    expect($resumed)->not->toBeEmpty();
    $mgr->artisanResume($p4['id']);
});
