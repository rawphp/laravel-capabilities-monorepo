<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: shape deferred accept from pending executes state machine [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('pending' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('pending' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('pending' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred accept from approved does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('approved' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('approved' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('approved' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred accept from rejected does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('rejected' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('rejected' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('rejected' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred accept from expired does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('expired' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('expired' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('expired' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('happy: shape deferred accept from executed replays [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('executed' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('executed' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('executed' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('happy: shape deferred reject from pending never runs [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('pending' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('pending' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('pending' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred reject from approved does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('approved' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('approved' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('approved' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred reject from rejected does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('rejected' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('rejected' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('rejected' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred reject from expired does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('expired' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('expired' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('expired' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape deferred reject from executed does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('executed' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('executed' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('executed' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('happy: shape atomic accept from pending executes state machine [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('pending' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('pending' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('pending' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic accept from approved does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('approved' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('approved' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('approved' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic accept from rejected does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('rejected' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('rejected' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('rejected' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic accept from expired does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('expired' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('expired' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('expired' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('happy: shape atomic accept from executed replays [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('accept' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('executed' === 'pending' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('executed' === 'pending' && 'accept' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('executed' === 'executed' && 'accept' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('happy: shape atomic reject from pending never runs [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('pending' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('pending' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('pending' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic reject from approved does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('approved' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('approved' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('approved' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic reject from rejected does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'rejected');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('rejected' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('rejected' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('rejected' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic reject from expired does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'expired');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('expired' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('expired' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('expired' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('fail: shape atomic reject from executed does not re-run domain [D-006]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $before = $h['runCount']->value;
    $actor = ApprovalHelpers::requester();
    if ('reject' === 'accept') {
        $r = $h['manager']->accept($id, $actor);
    } else {
        $r = $h['manager']->reject($id, $actor, 'nope');
    }
    if ('executed' === 'pending' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before + 1);
        expect($h['store']->find($id)['status'])->toBe('executed');
    } elseif ('executed' === 'pending' && 'reject' === 'reject') {
        expect($h['runCount']->value)->toBe($before);
        expect($h['store']->find($id)['status'])->toBe('rejected');
    } elseif ('executed' === 'executed' && 'reject' === 'accept') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false))->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
        expect($r->isOk())->toBeFalse();
    }
});

it('edge: policy requester allows authorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester']);
    $actor = ApprovalHelpers::actorFor('requester', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (true) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('fail: policy requester forbids unauthorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester']);
    $actor = ApprovalHelpers::actorFor('random_user', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (false) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('edge: policy requester_or_role allows authorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester_or_role']);
    $actor = ApprovalHelpers::actorFor('requester', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (true) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('fail: policy requester_or_role forbids unauthorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'requester_or_role']);
    $actor = ApprovalHelpers::actorFor('random_user', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (false) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('edge: policy role:finance-approver allows authorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'role:finance-approver']);
    $actor = ApprovalHelpers::actorFor('role_holder', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (true) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('fail: policy role:finance-approver forbids unauthorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'role:finance-approver']);
    $actor = ApprovalHelpers::actorFor('requester', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (false) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('edge: policy any_staff allows authorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'any_staff']);
    $actor = ApprovalHelpers::actorFor('requester', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (true) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('fail: policy any_staff forbids unauthorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'any_staff']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (false) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('edge: policy custom allows authorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'custom']);
    $actor = ApprovalHelpers::actorFor('requester', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (true) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});

it('fail: policy custom forbids unauthorized decision [D-006]', function () {
    $h = ApprovalHelpers::withPending(['policy' => 'custom']);
    $actor = ApprovalHelpers::actorFor('other_tenant_user', $h['row']);
    $r = $h['manager']->accept((string) $h['row']['id'], $actor, ['tenant_id' => $actor->tenant_id ?? null]);
    if (false) {
        expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    } else {
        expect($r->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
    }
});
