<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalCallbackVerifier;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\Notifiers\CliApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it("edge: resume skips or waits when lease=free grace=inside_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=free grace=inside_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=free grace=inside_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume claims when lease=free grace=past_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('claims' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('claims' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=free grace=past_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=free grace=past_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('free' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('free' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_valid grace=inside_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=held_valid grace=inside_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_valid grace=inside_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_valid grace=past_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=held_valid grace=past_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_valid grace=past_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_valid' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_valid' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_expired grace=inside_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=held_expired grace=inside_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_expired grace=inside_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'inside_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume claims when lease=held_expired grace=past_grace status=approved [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'approved');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('approved' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('claims' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('claims' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("happy: resume replays when lease=held_expired grace=past_grace status=executed [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('executed' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('replays' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('replays' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

it("edge: resume skips or waits when lease=held_expired grace=past_grace status=pending [P2-004]", function () {
    $h = ApprovalHelpers::harness(['grace_seconds' => 30, 'lease_seconds' => 120]);
    $row = ApprovalHelpers::seedStatus($h['manager'], 'pending');
    $id = (string) $row['id'];
    $now = $h['clock']->now();
    if ('held_expired' === 'free') {
        $h['store']->update($id, ['execution_lease_until' => null]);
    } elseif ('held_expired' === 'held_valid') {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('+60 seconds')->format(DATE_ATOM)]);
    } else {
        $h['store']->update($id, ['execution_lease_until' => $now->modify('-10 seconds')->format(DATE_ATOM)]);
    }
    if ('pending' === 'approved') {
        $approvedAt = 'past_grace' === 'inside_grace' ? $now->format(DATE_ATOM) : $now->modify('-120 seconds')->format(DATE_ATOM);
        $h['store']->update($id, ['approved_at' => $approvedAt, 'decided_at' => $approvedAt]);
    }
    $before = $h['runCount']->value;
    $results = $h['manager']->resume($id);
    $r = $results[0];
    $fresh = $h['store']->find($id);
    if ('skips or waits' === 'claims') {
        expect($h['runCount']->value)->toBe($before + 1)->and($fresh['status'])->toBe('executed');
    } elseif ('skips or waits' === 'replays') {
        expect($h['runCount']->value)->toBe($before);
        expect(($r->meta['approval_replay'] ?? false) || ($r->meta['idempotent_replay'] ?? false) || $r->isOk())->toBeTrue();
    } else {
        expect($h['runCount']->value)->toBe($before);
    }
});

