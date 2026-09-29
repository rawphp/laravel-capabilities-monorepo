<?php

// D-006: role:{name} matches only actors holding that configured role — no hardcoded role aliases.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;

function rolePolicyRow(): array
{
    return [
        'tenant_id' => 't-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
    ];
}

it('fail: policy role:billing-admin denies actor whose roles hold only finance-approver [D-006]', function () {
    $policy = ApprovalPolicy::fromString('role:billing-admin');
    $actor = (object) ['id' => '55', 'roles' => ['finance-approver']];

    expect($policy->allows(rolePolicyRow(), $actor, 't-1'))->toBeFalse();
});

it('fail: policy role:billing-admin denies actor whose scalar role is finance-approver or approver [D-006]', function (string $role) {
    $policy = ApprovalPolicy::fromString('role:billing-admin');
    $actor = (object) ['id' => '55', 'role' => $role];

    expect($policy->allows(rolePolicyRow(), $actor, 't-1'))->toBeFalse();
})->with(['finance-approver', 'approver']);

it('edge: policy role:billing-admin allows actor holding billing-admin [D-006]', function () {
    $policy = ApprovalPolicy::fromString('role:billing-admin');

    expect($policy->allows(rolePolicyRow(), (object) ['id' => '55', 'roles' => ['billing-admin']], 't-1'))->toBeTrue()
        ->and($policy->allows(rolePolicyRow(), (object) ['id' => '56', 'role' => 'billing-admin'], 't-1'))->toBeTrue();
});

it('fail: requester_or_role with custom default role denies non-requester holding only approver [D-006]', function () {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::REQUESTER_OR_ROLE, defaultRole: 'reviewer');

    expect($policy->allows(rolePolicyRow(), (object) ['id' => '55', 'role' => 'approver'], 't-1'))->toBeFalse()
        ->and($policy->allows(rolePolicyRow(), (object) ['id' => '56', 'roles' => ['finance-approver']], 't-1'))->toBeFalse();
});
