<?php

// L-005 / D-006: an accepted or resumed approval runs as the *real* original requester,
// resolved through the same host lookup the accept re-check uses — not a look-alike stub.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

final class ApprovedExecutionUser
{
    public string $tenant_id = 't-1';

    public function __construct(public string $id) {}

    public function can(string $ability): bool
    {
        return $ability === 'create-invoice';
    }
}

function approvedExecutionPending(array $h, string $caller = 'http'): string
{
    $result = $h['registry']->invoke(
        $h['name'],
        PipelineHelpers::validInput(),
        PipelineHelpers::options($caller, ['needs_approval' => true]),
    );
    expect($result->isApprovalRequired())->toBeTrue();

    return (string) $result->approvalId();
}

function approvedExecutionApprover(): object
{
    $approver = PipelineHelpers::userActor(8);
    $approver->tenant_id = 't-1';
    $approver->roles = ['approver'];

    return $approver;
}

it('happy: the approved run authorizes and runs as the resolved requester instance [L-005 / D-006]', function () {
    $seen = [];
    $h = PipelineHelpers::harness([
        'authorize_cb' => function ($in, $ctx) use (&$seen): bool {
            $seen[] = $ctx->actor();

            // Request-time actor is a plain unit principal; the approved run must bring a real user.
            return method_exists($ctx->actor(), 'can') ? $ctx->actor()->can('create-invoice') : true;
        },
        'run' => function ($in, $ctx) use (&$seen) {
            $seen[] = $ctx->actor();

            return new CreateInvoiceResult(invoice_id: 5);
        },
    ]);
    $resolved = [];
    $h['registry']->withRequesterResolver(function (string $type, string $id) use (&$resolved): ?object {
        $resolved[] = [$type, $id];

        return new ApprovedExecutionUser($id);
    });
    $id = approvedExecutionPending($h);

    $result = $h['registry']->approvals()->accept($id, approvedExecutionApprover());

    expect($result->isOk())->toBeTrue()
        ->and($resolved)->toBe([['user', '7']])
        // request-time authorize saw the live user; the approved authorize + run see the same resolved instance
        ->and($seen)->toHaveCount(3)
        ->and($seen[1])->toBeInstanceOf(ApprovedExecutionUser::class)
        ->and($seen[2])->toBe($seen[1])
        ->and($seen[1]->id)->toBe('7');
});

it('fail: an unresolvable requester fails closed and run() is not called [L-005 / D-006]', function () {
    $h = PipelineHelpers::harness();
    $h['registry']->withRequesterResolver(static fn (string $type, string $id): ?object => null);
    $id = approvedExecutionPending($h);

    $result = $h['registry']->approvals()->accept($id, approvedExecutionApprover());

    expect($result->errorCode())->toBe('forbidden')
        ->and($result->error['message'])->toContain('requester')
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['fakes']->approvals->find($id)['result_status'])->toBe('failed');
});

it('edge: a system requester is rebuilt as the same SystemActor without asking the resolver [L-005 / D-002]', function () {
    $actors = [];
    $h = PipelineHelpers::harness(['authorize_cb' => function ($in, $ctx) use (&$actors): bool {
        $actors[] = $ctx->actor();

        return true;
    }]);
    $called = 0;
    $h['registry']->withRequesterResolver(function () use (&$called): ?object {
        $called++;

        return null;
    });
    $id = approvedExecutionPending($h, 'job');

    $result = $h['registry']->approvals()->withPolicy(
        ApprovalPolicy::fromString('any_staff', staffChecker: fn () => true),
    )->accept($id, approvedExecutionApprover());

    expect($result->isOk())->toBeTrue()
        ->and($called)->toBe(0)
        ->and($actors[1])->toBeInstanceOf(SystemActor::class)
        ->and($actors[1]->name)->toBe('billing-worker');
});

it('edge: without a resolver the requester is a plain principal carrying id and tenant (unit default) [L-005]', function () {
    $actors = [];
    $h = PipelineHelpers::harness(['authorize_cb' => function ($in, $ctx) use (&$actors): bool {
        $actors[] = $ctx->actor();

        return true;
    }]);
    $id = approvedExecutionPending($h);

    $result = $h['registry']->approvals()->accept($id, approvedExecutionApprover());

    expect($result->isOk())->toBeTrue()
        ->and((string) $actors[1]->id)->toBe('7')
        ->and($actors[1]->tenant_id)->toBe('t-1');
});
