<?php

// REQ-070 / L-013: any_staff without staffChecker / is_staff fails closed.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Support\SystemActor;

it('REQ-070: any_staff without staffChecker denies ordinary user without is_staff', function () {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::ANY_STAFF);
    $row = [
        'tenant_id' => 't-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ];
    $ordinary = (object) ['id' => '55', 'tenant_id' => 't-1'];

    expect($policy->allows($row, $ordinary, 't-1'))->toBeFalse();
});

it('REQ-070: any_staff without staffChecker still honors explicit is_staff true', function () {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::ANY_STAFF);
    $row = [
        'tenant_id' => 't-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ];
    $staff = (object) ['id' => '99', 'tenant_id' => 't-1', 'is_staff' => true];

    expect($policy->allows($row, $staff, 't-1'))->toBeTrue();
});

it('REQ-070: any_staff with staffChecker uses checker result', function () {
    $allow = ApprovalPolicy::fromString(
        ApprovalPolicy::ANY_STAFF,
        staffChecker: static fn (object $actor): bool => ($actor->id ?? null) === 'ok',
    );
    $deny = ApprovalPolicy::fromString(
        ApprovalPolicy::ANY_STAFF,
        staffChecker: static fn (object $actor): bool => false,
    );
    $row = [
        'tenant_id' => 't-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ];

    expect($allow->allows($row, (object) ['id' => 'ok'], 't-1'))->toBeTrue()
        ->and($deny->allows($row, (object) ['id' => 'ok'], 't-1'))->toBeFalse()
        ->and($deny->allows($row, SystemActor::named('s'), 't-1'))->toBeFalse();
});

it('REQ-070: custom policy without checker fails closed for ordinary user', function () {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::CUSTOM);
    $row = [
        'tenant_id' => 't-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ];
    $ordinary = (object) ['id' => '55'];

    expect($policy->allows($row, $ordinary, 't-1'))->toBeFalse();
});
