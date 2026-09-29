<?php

// D-006 / D-003: a tenant-scoped approval row is never decided or resumed by an
// actor whose tenant cannot be resolved. Unknown tenant fails closed.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

$tenantRow = [
    'tenant_id' => 't-1',
    'requester_actor_type' => 'user',
    'requester_actor_id' => '7',
];

it('fail: policy denies a role holder with unresolved tenant on a tenant-scoped row', function () use ($tenantRow) {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::REQUESTER_OR_ROLE);
    $roleHolder = (object) ['id' => '99', 'roles' => ['approver']];

    expect($policy->allows($tenantRow, $roleHolder, null))->toBeFalse()
        ->and($policy->allows($tenantRow, $roleHolder, 't-1'))->toBeTrue();
});

it('fail: policy denies a requester-id match with unresolved tenant on a tenant-scoped row', function () use ($tenantRow) {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::REQUESTER);
    $sameId = (object) ['id' => '7'];

    expect($policy->allows($tenantRow, $sameId, null))->toBeFalse();
});

it('edge: policy still allows an unresolved tenant when the row has no tenant', function () {
    $policy = ApprovalPolicy::fromString(ApprovalPolicy::REQUESTER_OR_ROLE);
    $roleHolder = (object) ['id' => '99', 'roles' => ['approver']];

    expect($policy->allows(['requester_actor_id' => '7'], $roleHolder, null))->toBeTrue()
        ->and($policy->allows(['tenant_id' => '', 'requester_actor_id' => '7'], $roleHolder, null))->toBeTrue();
});

it('fail: accept and reject deny an approver without tenant_id on a tenant-scoped row', function () {
    $h = ApprovalHelpers::withPending(['policy' => ApprovalPolicy::REQUESTER_OR_ROLE]);
    $id = (string) $h['row']['id'];
    $noTenant = (object) ['id' => '99', 'roles' => ['approver']];

    $accept = $h['manager']->accept($id, $noTenant);
    $reject = $h['manager']->reject($id, $noTenant, 'nope');

    expect($accept->isFailed())->toBeTrue()
        ->and($accept->errorCode())->toBe('forbidden')
        ->and($reject->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['store']->find($id)['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
});

it('fail: resume denies a requester-id match without tenant_id on a tenant-scoped row', function () {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], ApprovalStateMachine::STATUS_APPROVED);
    $sameIdNoTenant = (object) ['id' => '7'];

    $results = $h['manager']->resume((string) $row['id'], $sameIdNoTenant, force: true);

    expect($results)->toHaveCount(1)
        ->and($results[0]->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});
