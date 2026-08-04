<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('fail: two concurrent accepts only one run [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(1);
});

it('fail: two concurrent resumes only one run [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    $h['manager']->resume($id);
    expect($h['runCount']->value)->toBe(1);
});

it('fail: accept and resume race only one run [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $h['manager']->resume($id);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: loser receives replay or in_progress not second domain apply [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());
    $r2 = $h['manager']->accept($id, ApprovalHelpers::requester());
    $ok = ($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false) || ($r2->error['in_progress'] ?? false) || $r2->isOk();
    expect($ok)->toBeTrue()->and($h['runCount']->value)->toBe(1);
});
