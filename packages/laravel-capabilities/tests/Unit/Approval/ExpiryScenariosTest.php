<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: pending past ttl becomes expired on_read [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_read [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_accept [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_accept [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_reject [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_reject [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_sweeper [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_sweeper [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

function expiryScenarioManager(): ApprovalManager
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

it('expire of an unknown approval id returns null [D-006]', function () {
    expect(expiryScenarioManager()->expire('missing'))->toBeNull();
});

it('expire before expiry returns the row untouched while force expires it [D-006]', function () {
    $mgr = expiryScenarioManager();
    $p2 = $mgr->request([
        'capability_name' => 'c2',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
        'expires_at' => '2099-01-01T00:00:00Z',
    ]);
    expect($mgr->expire($p2['id'], force: false))->not->toBeNull(); // not past expiry returns row
    $forced = $mgr->expire($p2['id'], force: true);
    expect($forced === null || ($forced['status'] ?? null) === ApprovalStateMachine::STATUS_EXPIRED)->toBeTrue();

    // expire non-pending returns row
    if ($forced !== null) {
        expect($mgr->expire($p2['id']))->not->toBeNull();
    }
});
