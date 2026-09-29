<?php

// M-301 / D-003 / D-006: the approver's tenant is resolved by the same ScopeResolver that
// stamped the approval row. One rule for accept, reject and resume — an in-tenant approver
// is allowed with the default wiring; a cross-tenant approver is still refused.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
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

// L-401: an approved execution runs in the tenant stamped on the row — the tenant the approver
// was placed in and authorize() was re-checked under — not whatever tenant the requester
// resolves to at accept time. The key row in the row's tenant settles.
it('an approved execution runs in the row tenant even when the requester has since switched tenant', function () {
    $seen = [];
    $h = IdempotencyHelpers::harness([
        'approvalPolicy' => ApprovalPolicy::REQUESTER,
        'run' => function ($in, CapabilityContext $ctx) use (&$seen) {
            $seen[] = $ctx->tenantId();

            return new CreateInvoiceResult(invoice_id: 42);
        },
    ]);
    // Requester now resolves to t2 (tenant switcher moved on after the request).
    $h['registry']->withRequesterResolver(fn (string $type, string $id) => m301Actor(['current_tenant_id' => 't2'], $id));
    $opts = ['caller' => 'http', 'actor' => m301Actor(['current_tenant_id' => 't1']), 'needs_approval' => true, 'idempotency_key' => 'switch-1'];

    $requested = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $id = (string) $requested->approvalId();

    $accepted = $h['registry']->approvals()->accept($id, m301Actor(['current_tenant_id' => 't1']));
    $retryInT1 = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($h['registry']->approvals()->find($id)['tenant_id'])->toBe('t1')
        ->and($accepted->isOk())->toBeTrue()
        ->and($seen)->toBe(['t1'])
        ->and($h['store']->find('t1', 'user', '7', $h['name'], 'switch-1')['status'])->toBe('completed')
        ->and($h['store']->find('t2', 'user', '7', $h['name'], 'switch-1'))->toBeNull()
        ->and($retryInT1->isOk())->toBeTrue()
        ->and($retryInT1->isApprovalRequired())->toBeFalse()
        ->and($seen)->toHaveCount(1);
});

it('resume also runs an approved row in the row tenant, not the requester\'s current one', function () {
    $seen = [];
    $h = IdempotencyHelpers::harness([
        'approvalPolicy' => ApprovalPolicy::REQUESTER,
        'run' => function ($in, CapabilityContext $ctx) use (&$seen) {
            $seen[] = $ctx->tenantId();

            return new CreateInvoiceResult(invoice_id: 42);
        },
    ]);
    $h['registry']->withRequesterResolver(fn (string $type, string $id) => m301Actor(['current_tenant_id' => 't2'], $id));
    $requested = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), ['caller' => 'http', 'actor' => m301Actor(['current_tenant_id' => 't1']), 'needs_approval' => true]);
    $id = (string) $requested->approvalId();
    $h['registry']->approvals()->store()->update($id, ['status' => ApprovalStateMachine::STATUS_APPROVED, 'approved_at' => '2026-01-15T12:00:00+00:00', 'decided_by' => '7']);

    $resumed = $h['registry']->approvals()->resume($id, m301Actor(['current_tenant_id' => 't1']), force: true);

    expect($resumed[0]->isOk())->toBeTrue()
        ->and($seen)->toBe(['t1']);
});

it('an approved row without a tenant keeps resolving scope at execution time', function () {
    $seen = [];
    $h = IdempotencyHelpers::harness([
        'approvalPolicy' => ApprovalPolicy::ANY_STAFF,
        'run' => function ($in, CapabilityContext $ctx) use (&$seen) {
            $seen[] = $ctx->tenantId();

            return new CreateInvoiceResult(invoice_id: 42);
        },
    ]);
    $requested = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), ['caller' => 'job', 'actor' => SystemActor::named('sched'), 'needs_approval' => true, 'global_system' => true]);
    $id = (string) $requested->approvalId();
    $row = $h['registry']->approvals()->find($id);

    $accepted = $h['registry']->approvals()->accept($id, m301Actor(['is_staff' => true]));

    expect($row['tenant_id'] ?? null)->toBeNull()
        ->and($accepted->isOk())->toBeTrue()
        ->and($seen)->toBe([null]);
});
