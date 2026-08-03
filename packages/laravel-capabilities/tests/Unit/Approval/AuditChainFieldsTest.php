<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: audit event approval.requested emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();

    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.requested');
});

it('edge: audit event approval.requested includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.requested' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.requested' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.requested') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('edge: audit event approval.requested includes requester [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.requested' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.requested' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.requested') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('requester');
});

it('edge: audit event approval.requested includes capability [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.requested' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.requested' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.requested') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('capability');
});

it('edge: audit event approval.requested includes input_redacted [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.requested' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.requested' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.requested') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('input_redacted');
});

it('edge: audit event approval.requested includes idempotency_key [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.requested' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.requested' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.requested' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.requested') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('idempotency_key');
});

it('happy: audit event approval.decided emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.decided');
});

it('edge: audit event approval.decided includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.decided' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.decided' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.decided') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('edge: audit event approval.decided includes decided_by [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.decided' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.decided' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.decided') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('decided_by');
});

it('edge: audit event approval.decided includes decision [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.decided' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.decided' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.decided') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('decision');
});

it('edge: audit event approval.decided includes reason [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.decided' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.decided' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.decided' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.decided') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('reason');
});

it('happy: audit event approval.executed emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.executed');
});

it('edge: audit event approval.executed includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.executed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.executed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.executed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('edge: audit event approval.executed includes result [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.executed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.executed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.executed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('result');
});

it('edge: audit event approval.executed includes replay [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.executed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.executed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.executed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('replay');
});

it('edge: audit event approval.executed includes via [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.executed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.executed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.executed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.executed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('via');
});

it('happy: audit event approval.replayed emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.replayed');
});

it('edge: audit event approval.replayed includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.replayed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.replayed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.replayed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.replayed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.replayed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('edge: audit event approval.replayed includes result [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.replayed' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.replayed' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.replayed' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.replayed' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.replayed') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('result');
});

it('happy: audit event approval.expired emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    ApprovalHelpers::advanceHours($h['clock'], 25);
    $h['manager']->find((string) $h['row']['id']);
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.expired');
});

it('edge: audit event approval.expired includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.expired' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.expired' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.expired' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.expired' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.expired') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('happy: audit event approval.resume emitted on condition [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
    $h['manager']->resume((string) $h['row']['id']);
    expect(array_column($h['audit']->all(), 'event'))->toContain('approval.resume');
});

it('edge: audit event approval.resume includes approval_id [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.resume' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.resume' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.resume' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.resume' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.resume') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('approval_id');
});

it('edge: audit event approval.resume includes attempt [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    if ('approval.resume' === 'approval.expired') {
        ApprovalHelpers::advanceHours($h['clock'], 25);
        $h['manager']->find((string) $h['row']['id']);
    } elseif ('approval.resume' === 'approval.resume') {
        $h['store']->update((string) $h['row']['id'], ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);
        $h['manager']->resume((string) $h['row']['id']);
    } elseif ('approval.resume' === 'approval.replayed') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    } elseif ('approval.resume' !== 'approval.requested') {
        $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    }
    $entry = null;
    foreach ($h['audit']->all() as $e) {
        if (($e['event'] ?? null) === 'approval.resume') {
            $entry = $e;
            break;
        }
    }
    expect($entry)->not->toBeNull()->and($entry)->toHaveKey('attempt');
});
