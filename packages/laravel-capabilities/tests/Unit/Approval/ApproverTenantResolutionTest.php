<?php

// M-301 / D-003 / D-006: the approver's tenant is resolved by the same ScopeResolver that
// stamped the approval row. One rule for accept, reject and resume — an in-tenant approver
// is allowed with the default wiring; a cross-tenant approver is still refused.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

/**
 * @param  array<string, mixed>  $attrs
 */
function m301Actor(array $attrs = [], int|string $id = 7): object
{
    $actor = new stdClass;
    $actor->id = $id;
    foreach ($attrs as $key => $value) {
        $actor->{$key} = $value;
    }

    return $actor;
}

/**
 * Default registry wiring, requester policy, no tenant option on invoke or accept.
 *
 * @return array{registry: CapabilityRegistry, id: string, row: array<string, mixed>, runCount: stdClass}
 */
function m301Pending(object $requester, array $registryOpts = []): array
{
    $h = IdempotencyHelpers::harness(['approvalPolicy' => ApprovalPolicy::REQUESTER] + $registryOpts);
    if (isset($registryOpts['scope_resolver'])) {
        $h['registry']->withScopeResolver($registryOpts['scope_resolver']);
    }

    $result = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), [
        'caller' => 'http',
        'actor' => $requester,
        'needs_approval' => true,
    ]);
    $id = (string) $result->approvalId();

    return ['registry' => $h['registry'], 'id' => $id, 'row' => $h['registry']->approvals()->find($id), 'runCount' => $h['runCount']];
}

it('the requester accepts their own approval with default wiring, whatever tenant attributes they carry', function (array $attrs, string $rowTenant) {
    $h = m301Pending(m301Actor($attrs));

    $accepted = $h['registry']->approvals()->accept($h['id'], m301Actor($attrs));

    expect($h['row']['tenant_id'])->toBe($rowTenant)
        ->and($accepted->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['registry']->approvals()->find($h['id'])['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED);
})->with([
    'plain user' => [[], 'default-tenant'],
    'tenant_id only' => [['tenant_id' => 't1'], 't1'],
    'current_tenant_id only' => [['current_tenant_id' => 't1'], 't1'],
    'LinkedUser-like actor (tenantId)' => [['tenantId' => 't1'], 't1'],
    'both attributes' => [['tenant_id' => 't1', 'current_tenant_id' => 't1'], 't1'],
]);

it('the requester rejects their own approval with default wiring and no tenant attributes', function () {
    $h = m301Pending(m301Actor());

    $rejected = $h['registry']->approvals()->reject($h['id'], m301Actor(), 'changed my mind');

    expect($rejected->errorCode())->toBe('rejected')
        ->and($h['registry']->approvals()->find($h['id'])['status'])->toBe(ApprovalStateMachine::STATUS_REJECTED)
        ->and($h['runCount']->value)->toBe(0);
});

it('a same-id approver resolved to another tenant is refused on accept and reject', function (array $approverAttrs) {
    $h = m301Pending(m301Actor(['current_tenant_id' => 't1']));

    $accepted = $h['registry']->approvals()->accept($h['id'], m301Actor($approverAttrs));
    $rejected = $h['registry']->approvals()->reject($h['id'], m301Actor($approverAttrs), 'no');

    expect($h['row']['tenant_id'])->toBe('t1')
        ->and($accepted->errorCode())->toBe('forbidden')
        ->and($rejected->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['registry']->approvals()->find($h['id'])['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
})->with([
    'current_tenant_id t2' => [['current_tenant_id' => 't2']],
    'tenant_id t2' => [['tenant_id' => 't2']],
    'no tenant (default-tenant)' => [[]],
]);

it('a trusted tenant_id option fills in only when the approver has no membership tenant', function () {
    $h = m301Pending(m301Actor(['current_tenant_id' => 't1']));

    $noMembership = $h['registry']->approvals()->accept($h['id'], m301Actor(), ['tenant_id' => 't1']);

    expect($noMembership->isOk())->toBeTrue();

    $h = m301Pending(m301Actor(['current_tenant_id' => 't1']));

    $membershipWins = $h['registry']->approvals()->accept($h['id'], m301Actor(['current_tenant_id' => 't2']), ['tenant_id' => 't1']);

    expect($membershipWins->errorCode())->toBe('forbidden')
        ->and($h['registry']->approvals()->find($h['id'])['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
});

it('accept uses the ScopeResolver the registry stamped the row with', function () {
    $resolver = new DefaultScopeResolver(['user_tenants' => ['7' => 'acme', '8' => 'globex']]);
    $h = m301Pending(m301Actor(), ['scope_resolver' => $resolver]);

    $sameTenant = $h['registry']->approvals()->accept($h['id'], m301Actor());

    expect($h['row']['tenant_id'])->toBe('acme')
        ->and($sameTenant->isOk())->toBeTrue();

    $h = m301Pending(m301Actor(), ['scope_resolver' => $resolver]);
    $otherTenant = $h['registry']->approvals()->accept($h['id'], m301Actor(id: 8));

    expect($otherTenant->errorCode())->toBe('forbidden');
});

it('a resolver that cannot place the approver fails closed as forbidden, not as an exception', function () {
    $h = ApprovalHelpers::withPending(['policy' => ApprovalPolicy::REQUESTER]);
    $manager = $h['manager']->withScopeResolver(new DefaultScopeResolver(['tenancy_required' => true]));

    $accepted = $manager->accept((string) $h['row']['id'], (object) ['id' => '7']);

    expect($accepted->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('resume resolves the acting user through the same resolver: same tenant runs, another tenant is refused', function () {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], ApprovalStateMachine::STATUS_APPROVED);

    $refused = $h['manager']->resume((string) $row['id'], m301Actor(['current_tenant_id' => 't-2']), force: true);
    $resumed = $h['manager']->resume((string) $row['id'], m301Actor(['current_tenant_id' => 't-1']), force: true);

    expect($refused[0]->errorCode())->toBe('forbidden')
        ->and($resumed[0]->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1);
});
