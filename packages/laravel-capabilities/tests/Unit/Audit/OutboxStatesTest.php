<?php

// Spec-derived unit tests for D-010 outbox states. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Audit\WriteAuditJob;
use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;

it('edge: outbox row status pending handled [D-010]', function () {
    $o = new AuditOutbox(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $id = $o->enqueue(['event' => 'capability.invoked']);
    expect($o->find($id)['status'])->toBe(AuditOutbox::STATUS_PENDING);
});

it('edge: outbox row status processing handled [D-010]', function () {
    $o = new AuditOutbox(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $id = $o->enqueue(['event' => 'capability.invoked']);
    $o->markProcessing($id);
    expect($o->find($id)['status'])->toBe(AuditOutbox::STATUS_PROCESSING);
});

it('edge: outbox row status completed handled [D-010]', function () {
    $o = new AuditOutbox(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $id = $o->enqueue(['event' => 'capability.invoked']);
    $o->markProcessing($id);
    $o->markCompleted($id);
    expect($o->find($id)['status'])->toBe(AuditOutbox::STATUS_COMPLETED);
});

it('edge: outbox row status failed handled [D-010]', function () {
    $o = new AuditOutbox(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $id = $o->enqueue(['event' => 'capability.invoked']);
    $o->markProcessing($id);
    $o->markFailed($id, 'disk full');
    expect($o->find($id)['status'])->toBe(AuditOutbox::STATUS_FAILED)
        ->and($o->find($id)['error'])->toBe('disk full');
});

it('happy: WriteAuditJob transitions pending to completed [D-010]', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    $o = new AuditOutbox($clock);
    $o->enqueue(['event' => 'capability.invoked', 'name' => 'x']);
    $writer = new InMemoryAuditWriter($clock);
    $n = (new WriteAuditJob($o, $writer))->handle();
    expect($n)->toBe(1)
        ->and($o->countByStatus(AuditOutbox::STATUS_COMPLETED))->toBe(1)
        ->and($writer->all())->toHaveCount(1);
});

it('fail: required true never leaves permanent silent drop [D-010]', function () {
    $h = AuditHelpers::harness(['required' => true, 'fail_audit' => true]);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    // even if job fails, intent remains in outbox (pending or failed after drain attempt)
    $job = new WriteAuditJob($h['outbox'], new FailingAuditWriter('still broken'));
    $job->handle();
    $statuses = array_column($h['outbox']->all(), 'status');
    expect($statuses)->not->toBeEmpty();
    expect(in_array(AuditOutbox::STATUS_FAILED, $statuses, true) || in_array(AuditOutbox::STATUS_PENDING, $statuses, true))->toBeTrue();
});
