<?php

// D-006: an accepted or resumed approval runs the stored capability through the registry
// pipeline (re-validate, original-actor authorize, run once, output contract) — never a
// fabricated success.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\ResumeApprovedApprovals;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\SystemClock;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function approvalRunsRequest(array $h, array $extra = []): string
{
    $result = $h['registry']->invoke(
        $h['name'],
        PipelineHelpers::validInput(),
        PipelineHelpers::options('http', array_merge(['needs_approval' => true], $extra)),
    );
    expect($result->isApprovalRequired())->toBeTrue();

    return (string) $result->approvalId();
}

it('happy: registry approvals()->accept runs the capability once with stored input [D-006]', function () {
    $seen = [];
    $h = PipelineHelpers::harness(['run' => function ($in) use (&$seen) {
        $seen[] = $in->toArray();

        return new CreateInvoiceResult(invoice_id: 99);
    }]);
    $id = approvalRunsRequest($h);

    $result = $h['registry']->approvals()->accept($id, PipelineHelpers::userActor(7));

    expect($result->isOk())->toBeTrue()
        ->and($result->data->invoice_id)->toBe(99)
        ->and($seen)->toHaveCount(1)
        ->and($seen[0]['customer_id'])->toBe(1)
        ->and($h['fakes']->approvals->find($id)['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and($h['fakes']->approvals->find($id)['result_status'])->toBe('ok');
});

it('happy: SystemActor resume of a stuck approved row runs the capability [D-006 / P2-004]', function () {
    $h = PipelineHelpers::harness();
    $id = approvalRunsRequest($h);
    $h['fakes']->approvals->compareAndUpdate($id, ApprovalStateMachine::STATUS_PENDING, [
        'status' => ApprovalStateMachine::STATUS_APPROVED,
        'decided_by' => '7',
        'approved_at' => '2000-01-01T00:00:00+00:00',
        'decided_at' => '2000-01-01T00:00:00+00:00',
    ]);

    $results = (new ResumeApprovedApprovals($h['registry']->approvals()))->handle();

    expect($results)->toHaveCount(1)
        ->and($results[0]->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['fakes']->approvals->find($id)['result_status'])->toBe('ok');
});

it('fail: approved run that breaks the output contract is recorded as failed [D-006]', function () {
    $h = PipelineHelpers::harness(['run_output' => ['wrong' => true]]);
    $id = approvalRunsRequest($h);

    $result = $h['registry']->approvals()->accept($id, PipelineHelpers::userActor(7));

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('output_invalid')
        ->and($h['fakes']->approvals->find($id)['result_status'])->toBe('failed');
});

it('fail: original actor no longer authorized means run() is not called [D-006]', function () {
    $allow = true;
    $h = PipelineHelpers::harness(['authorize_cb' => function () use (&$allow) {
        return $allow;
    }]);
    $id = approvalRunsRequest($h);
    $allow = false;

    $result = $h['registry']->approvals()->accept($id, PipelineHelpers::userActor(7));

    expect($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['fakes']->approvals->find($id)['result_status'])->toBe('failed');
});

it('edge: approved run authorizes as the original requester, not the approver [D-006]', function () {
    $actors = [];
    $h = PipelineHelpers::harness(['authorize_cb' => function ($in, $ctx) use (&$actors) {
        $actors[] = $ctx->actor();

        return true;
    }]);
    $id = approvalRunsRequest($h);
    $approver = PipelineHelpers::userActor(8);

    $h['registry']->approvals()->withPolicy(
        ApprovalPolicy::fromString('any_staff', staffChecker: fn () => true),
    )->accept($id, $approver);

    expect($actors)->toHaveCount(2)
        ->and($actors[1])->not->toBe($approver)
        ->and((string) $actors[1]->id)->toBe('7')
        ->and($actors[1]->tenant_id)->toBe('t-1');
});

it('edge: system requester is re-run as the same SystemActor [D-002 / D-006]', function () {
    $actors = [];
    $h = PipelineHelpers::harness(['authorize_cb' => function ($in, $ctx) use (&$actors) {
        $actors[] = $ctx->actor();

        return true;
    }]);
    $result = $h['registry']->invoke(
        $h['name'],
        PipelineHelpers::validInput(),
        PipelineHelpers::options('job', ['needs_approval' => true]),
    );

    $out = $h['registry']->approvals()->withPolicy(
        ApprovalPolicy::fromString('any_staff', staffChecker: fn () => true),
    )->accept((string) $result->approvalId(), PipelineHelpers::userActor(8));

    expect($out->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($actors[1])->toBeInstanceOf(SystemActor::class)
        ->and($actors[1]->name)->toBe('billing-worker');
});

it('fail: withApprovalStore keeps approvals wired to the registry run path [D-006]', function () {
    $h = PipelineHelpers::harness();
    $store = new InMemoryApprovalStore(new SystemClock);
    $h['registry']->withApprovalStore($store);
    $id = approvalRunsRequest($h);

    $result = $h['registry']->approvals()->accept($id, PipelineHelpers::userActor(7));

    expect($result->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($store->find($id)['result_status'])->toBe('ok');
});

it('fail: manager with no executor fails closed instead of fabricating success [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $bare = new ApprovalManager($h['store'], $h['clock']);

    $result = $bare->accept((string) $h['row']['id'], ApprovalHelpers::requester());

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('not_configured')
        ->and($h['store']->find((string) $h['row']['id'])['result_status'])->toBe('failed');
});
