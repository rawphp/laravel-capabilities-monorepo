<?php

// L-105 / D-013: the rate limit guards approval *requests*; executing an already-approved
// request is a decision that was made, not a new request, so it never spends or trips
// the requester's buckets.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function l105Approver(): object
{
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';

    return $approver;
}

it('executes a promptly accepted approval on a max=1 capability instead of burning it as rate_limited', function () {
    $h = PipelineHelpers::harness(['rateLimit' => ['max' => 1]]);

    $pending = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $accepted = $h['registry']->approvals()->accept((string) $pending->approvalId(), l105Approver());
    $row = $h['fakes']->approvals->find((string) $pending->approvalId());

    expect($pending->isApprovalRequired())->toBeTrue()
        ->and($accepted->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($row['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and($row['result_status'])->toBe('ok');
});

it('does not spend the requester bucket when an approved request executes', function () {
    $h = PipelineHelpers::harness(['rateLimit' => ['max' => 2]]);

    $pending = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $h['registry']->approvals()->accept((string) $pending->approvalId(), l105Approver());
    // Second real request from the requester: one hit spent so far (the original request), so this is allowed.
    $second = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));

    expect($second->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(2);
});

it('still rate-limits a fresh approval request after the bucket is spent', function () {
    $h = PipelineHelpers::harness(['rateLimit' => ['max' => 1]]);

    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $limited = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));

    expect($limited->errorCode())->toBe('rate_limited')
        ->and($h['fakes']->approvals->findByStatus(ApprovalStateMachine::STATUS_PENDING))->toHaveCount(1);
});
