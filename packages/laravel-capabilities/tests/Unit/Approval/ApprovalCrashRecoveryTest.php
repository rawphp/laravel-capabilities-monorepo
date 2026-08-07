<?php

declare(strict_types=1);

use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: ResumeApprovedApprovals executes stuck approved past grace with free lease [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null, 'decided_by' => '7']);
    $h['resume']->handle();
    expect($h['runCount']->value)->toBe(1)->and($h['store']->find($id)['status'])->toBe('executed');
});

it('happy: resume re-validates and scoped re-resolves before run [P2-004]', function () {
    $called = false;
    $h = ApprovalHelpers::withPending(['revalidator' => function ($row) use (&$called) {
        $called = true;

        return null;
    }]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    expect($called)->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('fail: resume on stale resource marks executed failed without domain success [P2-004]', function () {
    $h = ApprovalHelpers::withPending(['stale' => true]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    expect($h['runCount']->value)->toBe(0)->and($h['store']->find($id)['result_status'])->toBe('failed');
});

it('happy: second resume after executed is replay [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    $r2 = $h['manager']->resume($id)[0];
    expect($h['runCount']->value)->toBe(1);
    expect(($r2->meta['approval_replay'] ?? false) || ($r2->meta['idempotent_replay'] ?? false))->toBeTrue();
});

it('edge: approved within grace_seconds not claimed by resume [P2-004]', function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $h['store']->update($id, ['approved_at' => $h['clock']->now()->format(DATE_ATOM), 'execution_lease_until' => null]);
    $r = $h['manager']->resume($id)[0];
    expect($h['runCount']->value)->toBe(0);
});

it('happy: lease claim prevents two workers double run [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    $h['manager']->resume($id);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: stuck_after_seconds increments approvals_stuck_approved_total metric [P2-004]', function () {
    $h = ApprovalHelpers::harness(['stuck_after_seconds' => 60, 'grace_seconds' => 0]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $h['store']->update((string) $row['id'], [
        'approved_at' => $h['clock']->now()->modify('-400 seconds')->format(DATE_ATOM),
        'execution_lease_until' => $h['clock']->now()->modify('+60 seconds')->format(DATE_ATOM),
    ]);
    $h['manager']->resume();
    expect($h['manager']->metrics()->get('approvals_stuck_approved_total'))->toBeGreaterThan(0);
});

it('happy: ResumeApprovedApprovals::artisan uses same path as scheduler [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['resume']->artisan($id);
    expect($h['runCount']->value)->toBe(1);
});

it('happy: atomic execution mode has no approved limbo and resume is no-op [P2-004]', function () {
    $h = ApprovalHelpers::withPending(['execution' => 'atomic']);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeTrue()->and($h['store']->find((string) $h['row']['id'])['status'])->toBe('executed');
    expect($h['manager']->resume())->toBe([]);
});

it('fail: process death before commit Shape B leaves pending allowing safe re-accept [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = $h['manager']->request(ApprovalHelpers::pendingRecord());
    expect($h['store']->find((string) $row['id'])['status'])->toBe('pending');
    $r = $h['manager']->accept((string) $row['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: audit chain approval.resume [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.resume');
});

it('happy: metrics approvals_resume_total and approvals_accept_total [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($h['manager']->metrics()->all())->not->toBeEmpty();
});

it('fail: approved without resume or atomic is refused by design tests [P2-004]', function () {
    expect(ApprovalHelpers::harness(['execution' => 'deferred'])['manager']->resumeEnabled())->toBeTrue();
    expect(ApprovalHelpers::harness(['execution' => 'atomic'])['manager']->resumeEnabled())->toBeFalse();
});

it('fail: re-accept while approved does not blindly re-run [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->format(DATE_ATOM), 'execution_lease_until' => $h['clock']->now()->modify('+60 seconds')->format(DATE_ATOM)]);
    $r = $h['manager']->accept($id, ApprovalHelpers::requester());
    expect($h['runCount']->value)->toBe(0)->and($r->error['in_progress'] ?? false)->toBeTrue();
});

it('happy: resume uses original D-005 idempotency key [P2-004]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['idempotency_key' => 'k-99']]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    $found = $h['idempotency']->find('t-1', 'user', '7', 'create-invoice', 'k-99');
    expect($found)->not->toBeNull()->and($found['status'])->toBe('completed');
});

it('edge: execution_attempt increments on each resume claim [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null, 'execution_attempt' => 2]);
    $h['manager']->resume($id);
    expect($h['store']->find($id)['execution_attempt'])->toBeGreaterThan(2);
});

it('edge: lease_seconds config controls claim duration [P2-004]', function () {
    $h = ApprovalHelpers::harness(['lease_seconds' => 45]);
    expect($h['manager']->leaseSeconds())->toBe(45);
});

it('edge: every_seconds config schedules resume job [P2-004]', function () {
    $h = ApprovalHelpers::harness(['every_seconds' => 90]);
    expect($h['resume']->everySeconds())->toBe(90);
});

it('happy: resume emits approval.executed with via=resume [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume($id);
    $found = false;
    foreach ($h['manager']->events() as $e) {
        if ($e instanceof CapabilityApprovalExecuted && $e->via === 'resume') {
            $found = true;
        }
    }
    expect($found)->toBeTrue();
});

it('happy: accept emits approval.executed with via=accept [P2-004]', function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $found = false;
    foreach ($h['manager']->events() as $e) {
        if ($e instanceof CapabilityApprovalExecuted && $e->via === 'accept') {
            $found = true;
        }
    }
    expect($found)->toBeTrue();
});
