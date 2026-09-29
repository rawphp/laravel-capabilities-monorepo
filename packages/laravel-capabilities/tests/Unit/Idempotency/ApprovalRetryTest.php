<?php

// L-102 / D-005 principle 11: retrying an approval-gated invoke under the same
// Idempotency-Key replays the one pending approval; the approved execution runs
// under the original key so later retries replay the executed outcome.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function l102Approver(): object
{
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 'tenant-1';

    return $approver;
}

it('replays the pending approval instead of opening a second one on retry with the same key', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'gated-retry', 'needs_approval' => true]);

    $first = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $second = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($first->isApprovalRequired())->toBeTrue()
        ->and($second->isApprovalRequired())->toBeTrue()
        ->and($second->isReplay())->toBeTrue()
        ->and($second->approvalId())->toBe($first->approvalId())
        ->and($h['fakes']->approvals->findByStatus(ApprovalStateMachine::STATUS_PENDING))->toHaveCount(1)
        ->and($h['runCount']->value)->toBe(0);
});

it('runs the approved request under the original key and replays the executed outcome afterwards', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'gated-exec', 'needs_approval' => true]);

    $pending = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $accepted = $h['registry']->approvals()->accept((string) $pending->approvalId(), l102Approver());
    $retry = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'gated-exec');

    expect($accepted->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($row['status'])->toBe('completed')
        ->and($retry->isOk())->toBeTrue()
        ->and($retry->isReplay())->toBeTrue()
        ->and($retry->data)->toBe($accepted->data)
        ->and($h['fakes']->approvals->findByStatus(ApprovalStateMachine::STATUS_PENDING))->toHaveCount(0)
        ->and($h['fakes']->approvals->find((string) $pending->approvalId())['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED);
});

it('stores a failed approved execution under the key so retries replay the failure', function () {
    $h = IdempotencyHelpers::harness(['run_fails' => 'ledger closed']);
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'gated-fail', 'needs_approval' => true]);

    $pending = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $accepted = $h['registry']->approvals()->accept((string) $pending->approvalId(), l102Approver());
    $retry = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($accepted->isOk())->toBeFalse()
        ->and($h['store']->find('tenant-1', 'user', '7', $h['name'], 'gated-fail')['status'])->toBe('failed')
        ->and($retry->isReplay())->toBeTrue()
        ->and($retry->errorCode())->toBe('domain_error')
        ->and($h['runCount']->value)->toBe(1);
});

it('a retry with the same key but a different body still conflicts while the approval is pending', function () {
    $h = IdempotencyHelpers::harness();
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'gated-body', 'needs_approval' => true]);

    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $other = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputB(), $opts);

    expect($other->errorCode())->toBe('conflict')
        ->and($h['fakes']->approvals->findByStatus(ApprovalStateMachine::STATUS_PENDING))->toHaveCount(1);
});

it('guard: a pending_approval row replays the stored approval_required unless the executing approval owns it', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $guard->lookup($def, $ctx, 'pending-k', $hash);
    $guard->storeResult($def, $ctx, 'pending-k', $hash, CapabilityResult::approvalRequired('ap-7', 'wait'), 'ap-7');

    $replay = $guard->lookup($def, $ctx, 'pending-k', $hash);
    $owner = $guard->lookup($def, $ctx, 'pending-k', $hash, executingApprovalId: 'ap-7');
    $stranger = $guard->lookup($def, $ctx, 'pending-k', $hash, executingApprovalId: 'ap-8');

    expect($replay['action'])->toBe('replay')
        ->and($replay['result']->isApprovalRequired())->toBeTrue()
        ->and($replay['result']->approvalId())->toBe('ap-7')
        ->and($replay['result']->isReplay())->toBeTrue()
        ->and($owner['action'])->toBe('continue')
        ->and($stranger['action'])->toBe('replay');
});
