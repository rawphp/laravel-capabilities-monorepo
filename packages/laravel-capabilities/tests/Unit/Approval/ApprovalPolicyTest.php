<?php

// ApprovalPolicy: default multi-tenant safety and role / staff / requester decisions.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Support\SystemActor;

function approvalPolicyRow(): array
{
    return [
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
    ];
}

it('happy: the default policy is multi-tenant safe and exposes its policy string', function () {
    $p = new ApprovalPolicy;

    expect($p->isDefaultMultiTenantSafe())->toBeTrue()
        ->and($p->policy())->toBeString();
});

it('happy: a role policy exposes its role name and is multi-tenant safe', function () {
    $rolePol = new ApprovalPolicy(policy: 'role:finance-approver');

    expect($rolePol->roleName())->toBe('finance-approver')
        ->and($rolePol->isDefaultMultiTenantSafe())->toBeTrue();
});

it('happy: the default policy allows the requester in the same tenant only', function () {
    $p = new ApprovalPolicy;
    $requester = (object) ['id' => 'u1'];

    expect($p->allows(approvalPolicyRow(), $requester, 't1'))->toBeTrue()
        ->and($p->allows(approvalPolicyRow(), $requester, 't2'))->toBeFalse()
        ->and($p->allows(approvalPolicyRow(), SystemActor::named('s'), 't1'))->toBeFalse();
});

it('happy: a role policy allows an actor holding the configured role', function () {
    $rolePol = new ApprovalPolicy(policy: 'role:finance-approver');
    $roleActor = (object) ['id' => 'u2', 'roles' => ['finance-approver']];

    expect($rolePol->allows(approvalPolicyRow(), $roleActor, 't1'))->toBeTrue();
});

it('happy: requester-or-role allows a non-requester with the scalar approver role', function () {
    $ror = new ApprovalPolicy(policy: ApprovalPolicy::REQUESTER_OR_ROLE);
    $roleActor = (object) ['id' => 'u3', 'role' => 'approver'];

    expect($ror->allows(approvalPolicyRow(), $roleActor, 't1'))->toBeTrue();
});

it('happy: any-staff policy allows an actor flagged is_staff', function () {
    $staff = new ApprovalPolicy(policy: ApprovalPolicy::ANY_STAFF);

    expect($staff->allows(approvalPolicyRow(), (object) ['id' => 's1', 'is_staff' => true], 't1'))->toBeTrue();
});

it('happy: a custom roleChecker grants requester-or-role even when the staffChecker denies', function () {
    $withChecker = new ApprovalPolicy(
        policy: ApprovalPolicy::REQUESTER_OR_ROLE,
        roleChecker: static fn ($a, $r) => true,
        staffChecker: static fn ($a) => false,
    );

    expect($withChecker->allows(approvalPolicyRow(), (object) ['id' => 'x'], 't1'))->toBeTrue();
});
