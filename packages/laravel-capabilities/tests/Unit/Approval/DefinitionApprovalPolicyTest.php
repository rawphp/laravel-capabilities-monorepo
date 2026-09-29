<?php

// L-002 / D-006: approval is part of the definition. needsApproval lives on the capability,
// and the capability's approvalPolicy / approvalTtlHours travel on the approval row so
// accept / reject enforce the declared policy, not only the global default.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function defApprovalHarness(array $opts = []): array
{
    $h = PipelineHelpers::harness();
    $runs = new stdClass;
    $runs->value = 0;
    $builder = Capability::define('finance-cap')
        ->description('needs finance sign-off')
        ->surfaces(['http', 'cli', 'agent'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->allowSystemCallers(true)
        ->approvalPolicy($opts['policy'] ?? 'role:finance-approver')
        ->approvalTtlHours($opts['ttl'] ?? 2)
        ->needsApproval($opts['needsApproval'] ?? fn (CreateInvoiceInput $in, $ctx): bool => $ctx->caller() === 'http')
        ->run(function () use ($runs) {
            $runs->value++;

            return new CreateInvoiceResult(invoice_id: 11);
        });
    $builder->register($h['registry']);
    $h['runs'] = $runs;

    return $h;
}

function defApprovalUser(int $id, array $roles = []): object
{
    $u = PipelineHelpers::userActor($id);
    $u->tenant_id = 't-1';
    $u->roles = $roles;

    return $u;
}

it('edge: fluent needsApproval(callable) gates the approval stage without any invoke option [L-002 / D-006]', function () {
    $h = defApprovalHarness();

    $viaHttp = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $viaCli = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('cli'));

    expect($viaHttp->isApprovalRequired())->toBeTrue()
        ->and($viaCli->isOk())->toBeTrue()
        ->and($h['runs']->value)->toBe(1);
});

it('edge: needsApproval declaring only the input is called with the input alone [D-003 arity]', function () {
    $seen = null;
    $h = defApprovalHarness(['needsApproval' => function (CreateInvoiceInput $in) use (&$seen): bool {
        $seen = $in;

        return $in->amount_cents >= 100;
    }]);

    $result = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('cli'));

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($seen)->toBeInstanceOf(CreateInvoiceInput::class);
});

it('happy: the approval row carries the capability approvalPolicy and honours approvalTtlHours [L-002]', function () {
    $h = defApprovalHarness(['ttl' => 2]);

    $result = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $row = $h['fakes']->approvals->find((string) $result->approvalId());

    $expires = new DateTimeImmutable((string) $row['expires_at']);
    $hours = ($expires->getTimestamp() - time()) / 3600;
    expect($row['approval_policy'])->toBe('role:finance-approver')
        ->and($hours)->toBeGreaterThan(1.9)
        ->and($hours)->toBeLessThan(2.1);
});

it('fail: role:… on the capability blocks the requester from self-approving under the global default [L-002 / D-006]', function () {
    $h = defApprovalHarness();
    $result = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $id = (string) $result->approvalId();

    $selfAccept = $h['registry']->approvals()->accept($id, defApprovalUser(7));

    expect($selfAccept->errorCode())->toBe('forbidden')
        ->and($h['runs']->value)->toBe(0)
        ->and($h['fakes']->approvals->find($id)['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
});

it('happy: a holder of the capability role accepts and the capability runs once [L-002 / D-006]', function () {
    $h = defApprovalHarness();
    $result = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $id = (string) $result->approvalId();

    $accepted = $h['registry']->approvals()->accept($id, defApprovalUser(42, ['finance-approver']));

    expect($accepted->isOk())->toBeTrue()
        ->and($h['runs']->value)->toBe(1);
});

it('fail: reject enforces the row policy too [L-002 / D-006]', function () {
    $h = defApprovalHarness();
    $result = $h['registry']->invoke('finance-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $id = (string) $result->approvalId();

    $selfReject = $h['registry']->approvals()->reject($id, defApprovalUser(7), 'nope');
    $roleReject = $h['registry']->approvals()->reject($id, defApprovalUser(42, ['finance-approver']), 'nope');

    expect($selfReject->errorCode())->toBe('forbidden')
        ->and($roleReject->errorCode())->toBe('rejected');
});

it('edge: a row without approval_policy keeps the manager default policy [L-002 back-compat]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester']);

    $result = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());

    expect($result->isOk())->toBeTrue()
        ->and($h['row'])->toHaveKey('approval_policy')
        ->and($h['row']['approval_policy'])->toBeNull();
});

it('edge: row policy overrides the manager default but keeps its role checker [L-002]', function () {
    $checked = [];
    $h = ApprovalHelpers::withPending([
        'policy' => 'requester',
        'role_checker' => function (object $actor, string $role) use (&$checked): bool {
            $checked[] = $role;

            return $actor->id === 99;
        },
        'record' => ['approval_policy' => 'role:finance-approver'],
    ]);

    $requester = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $holder = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::roleHolder());

    expect($requester->errorCode())->toBe('forbidden')
        ->and($holder->isOk())->toBeTrue()
        ->and($checked)->toContain('finance-approver');
});

it('edge: forced resume by a user honours the row policy [L-002 / P2-004]', function () {
    $h = ApprovalHelpers::withPending([
        'policy' => 'any_staff',
        'staff_checker' => fn () => true,
        'record' => ['approval_policy' => 'role:finance-approver'],
    ]);
    $id = (string) $h['row']['id'];
    $h['store']->compareAndUpdate($id, ApprovalStateMachine::STATUS_PENDING, [
        'status' => ApprovalStateMachine::STATUS_APPROVED,
        'approved_at' => '2000-01-01T00:00:00+00:00',
    ]);

    $random = $h['manager']->resume($id, ApprovalHelpers::randomUser(), force: true);
    $holder = $h['manager']->resume($id, ApprovalHelpers::roleHolder(), force: true);

    expect($random[0]->errorCode())->toBe('forbidden')
        ->and($holder[0]->isOk())->toBeTrue();
});

it('happy: ApprovalPolicy::forRow returns the same instance when the row has no policy [L-002]', function () {
    $policy = ApprovalPolicy::fromString('requester');

    expect($policy->forRow(['approval_policy' => null]))->toBe($policy)
        ->and($policy->forRow([]))->toBe($policy)
        ->and($policy->forRow(['approval_policy' => '']))->toBe($policy)
        ->and($policy->forRow(['approval_policy' => 'any_staff'])->policy())->toBe('any_staff')
        ->and($policy->forRow(['approval_policy' => 'x'])->allows(['requester_actor_id' => '1'], SystemActor::named('s')))->toBeFalse();
});

it('happy: in-memory and database approval stores persist approval_policy [L-002]', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $record = ApprovalHelpers::pendingRecord(['approval_policy' => 'role:finance-approver']);

    $memory = (new InMemoryApprovalStore($clock))->put($record);
    $database = (new DatabaseApprovalStore(new ArrayTableGateway, $clock))->put($record);

    expect($memory['approval_policy'])->toBe('role:finance-approver')
        ->and($database['approval_policy'])->toBe('role:finance-approver')
        ->and(MigrationCatalog::columns(MigrationCatalog::TABLE_APPROVALS))->toContain('approval_policy');
});
