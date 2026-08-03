<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('edge: decision path when shape=deferred status=pending action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=deferred status=pending action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=deferred status=pending action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=deferred status=pending action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=pending action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=deferred status=approved action=resume actor=requester [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=deferred status=approved action=resume actor=approver_role [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=deferred status=approved action=resume actor=system [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=approved action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=rejected action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=expired action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=deferred status=executed action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'deferred' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=atomic status=pending action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=atomic status=pending action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=atomic status=pending action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: decision path when shape=atomic status=pending action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=pending action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    if ('pending' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=atomic status=approved action=resume actor=requester [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=atomic status=approved action=resume actor=approver_role [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('edge: resume path when shape=atomic status=approved action=resume actor=system [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (true) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=approved action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    if ('approved' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=rejected action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    if ('rejected' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=expired action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    if ('expired' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=accept actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=accept actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=accept actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=accept actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=accept actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('accept' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('accept' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=reject actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=reject actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=reject actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=reject actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=reject actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('reject' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('reject' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=resume actor=requester [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('requester', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=resume actor=approver_role [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('approver_role', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=resume actor=random [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('random', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=resume actor=system [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('system', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});

it('fail: invalid or denied when shape=atomic status=executed action=resume actor=other_tenant [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic', 'grace_seconds' => 0, 'lease_seconds' => 1]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    if ('executed' === 'approved') {
        $h['store']->update($id, [
            'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM),
            'execution_lease_until' => null,
        ]);
    }
    $actorObj = ApprovalHelpers::actorFor('other_tenant', $row);
    $before = $h['runCount']->value;
    $tenant = isset($actorObj->tenant_id) ? $actorObj->tenant_id : null;
    if ('resume' === 'accept') {
        $r = $h['manager']->accept($id, $actorObj, ['tenant_id' => $tenant]);
    } elseif ('resume' === 'reject') {
        $r = $h['manager']->reject($id, $actorObj, 'reason', ['tenant_id' => $tenant]);
    } else {
        $r = $h['manager']->resume($id, $actorObj)[0];
    }
    if (false) {
        if ('resume' === 'reject') {
            expect($r->errorCode())->toBe('rejected');
            expect($h['runCount']->value)->toBe($before);
        } else {
            $fresh = $h['store']->find($id);
            $ok = $r->isOk() || ($r->meta['approval_replay'] ?? false) || ($fresh['status'] ?? '') === 'executed' || ($r->errorCode() === 'conflict' && 'atomic' === 'atomic');
            expect($ok)->toBeTrue();
        }
    } else {
        // No second domain apply. Terminal/invalid may replay stored result or error.
        expect($h['runCount']->value)->toBe($before);
        $isReplay = ($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false);
        expect($isReplay || $r->isOk() === false)->toBeTrue();
    }
});
