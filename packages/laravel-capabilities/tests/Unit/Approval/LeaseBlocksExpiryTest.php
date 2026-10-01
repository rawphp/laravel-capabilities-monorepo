<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('fail: an active execution lease keeps a pending approval from expiring [D-006]', function () {
    $h = ApprovalHelpers::withPending([
        'execution' => ApprovalStateMachine::EXECUTION_ATOMIC,
        'ttl_hours' => 1,
    ]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, [
        'execution_lease_until' => $h['clock']->now()->modify('+10 minutes')->format(DATE_ATOM),
        'expires_at' => $h['clock']->now()->modify('-1 minute')->format(DATE_ATOM),
    ]);

    $updated = $h['manager']->expire($id, force: true);

    expect($updated['status'] ?? null)->toBe(ApprovalStateMachine::STATUS_PENDING)
        ->and($h['store']->find($id)['status'] ?? null)->toBe(ApprovalStateMachine::STATUS_PENDING);
});

it('fail: expirePending skips a leased in-flight approval [D-006]', function () {
    $h = ApprovalHelpers::withPending([
        'execution' => ApprovalStateMachine::EXECUTION_ATOMIC,
        'ttl_hours' => 1,
    ]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, [
        'execution_lease_until' => $h['clock']->now()->modify('+10 minutes')->format(DATE_ATOM),
        'expires_at' => $h['clock']->now()->modify('-1 minute')->format(DATE_ATOM),
    ]);

    expect($h['manager']->expirePending())->toBe(0)
        ->and($h['store']->find($id)['status'] ?? null)->toBe(ApprovalStateMachine::STATUS_PENDING);
});

it('fail: a lost execution does not write executed over an expired approval [D-006]', function () {
    $h = ApprovalHelpers::withPending([
        'execution' => ApprovalStateMachine::EXECUTION_ATOMIC,
    ]);
    $id = (string) $h['row']['id'];
    $store = $h['store'];
    $racing = ApprovalHelpers::harness([
        'execution' => ApprovalStateMachine::EXECUTION_ATOMIC,
        'store' => $store,
        'clock' => $h['clock'],
        'executor' => function (array $row) use ($store) {
            $store->update((string) $row['id'], [
                'status' => ApprovalStateMachine::STATUS_EXPIRED,
                'execution_lease_until' => null,
            ]);

            return CapabilityResult::ok(['invoice_id' => 99]);
        },
    ]);

    $result = $racing['manager']->accept($id, ApprovalHelpers::requester());
    $fresh = $store->find($id);

    expect($fresh['status'] ?? null)->toBe(ApprovalStateMachine::STATUS_EXPIRED)
        ->and($fresh['result_status'] ?? null)->not->toBe('ok')
        ->and($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('conflict');
});
