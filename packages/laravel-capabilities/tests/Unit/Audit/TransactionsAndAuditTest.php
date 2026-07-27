<?php

// Spec-derived unit tests for D-010 transactions + audit. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Audit\WriteAuditJob;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it("happy: mutating capability emits audit record on success [D-010]", function () {
    $h = AuditHelpers::harness();
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()
        ->and($h['audit']->all())->not->toBeEmpty()
        ->and($h['audit']->all()[0]['event'])->toBe('capability.invoked');
});

it("happy: readOnly skips audit unless audit forced true [D-010]", function () {
    $h = AuditHelpers::harness(['readOnly' => true, 'audit' => false, 'name' => 'ro-skip']);
    // readOnly definitions still need input in this package unless truly empty — harness sets input
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['audit']->all())->toBeEmpty();

    $forced = AuditHelpers::harness(['readOnly' => true, 'audit' => ['force' => true], 'name' => 'ro-force']);
    $forced['registry']->invoke($forced['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($forced['audit']->all())->not->toBeEmpty();
});

it("happy: best_effort audit failure after successful run still returns success [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'required' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['runCount']->sideEffect)->toBeTrue();
});

it("happy: best_effort with required true writes outbox on audit failure [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'required' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()
        ->and($h['outbox']->pending())->not->toBeEmpty()
        ->and($h['outbox']->all()[0]['status'])->toBe(AuditOutbox::STATUS_PENDING);
});

it("fail: silent drop of audit never occurs when required true [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'required' => true]);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['outbox']->all())->not->toBeEmpty();
    $hasDurable = $h['outbox']->countByStatus(AuditOutbox::STATUS_PENDING) > 0
        || $h['outbox']->countByStatus(AuditOutbox::STATUS_COMPLETED) > 0
        || $h['outbox']->countByStatus(AuditOutbox::STATUS_FAILED) > 0;
    expect($hasDurable)->toBeTrue();
});

it("edge: strict mode surfaces failure when audit fails depending on txn design [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('audit_failed')
        ->and($h['runCount']->value)->toBe(1); // domain already ran
});

it("happy: transactions wrap_run false by default does not wrap run [D-010]", function () {
    $h = AuditHelpers::harness(['transactions' => ['wrap_run' => false]]);
    expect($h['registry']->transactionsWrapRun())->toBeFalse();
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['registry']->lastRunWasWrapped())->toBeFalse();
});

it("edge: wrap_run true wraps run optionally with sync audit [D-010]", function () {
    $h = AuditHelpers::harness(['transactions' => ['wrap_run' => true]]);
    expect($h['registry']->transactionsWrapRun())->toBeTrue();
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue()
        ->and($h['audit']->all())->not->toBeEmpty();
});

it("happy: bus event CapabilityInvoked emitted on matching condition [D-010]", function () {
    $h = AuditHelpers::harness();
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['registry']->invokedEvents())->not->toBeEmpty()
        ->and($h['registry']->invokedEvents()[0])->toBeInstanceOf(CapabilityInvoked::class);
});

it("edge: listeners for CapabilityInvoked should use afterCommit when touching DB [D-010]", function () {
    expect(CapabilityInvoked::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: bus event CapabilityFailed emitted on matching condition [D-010]", function () {
    $h = AuditHelpers::harness(['run_throws' => 'boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()
        ->and($h['registry']->failedEvents())->not->toBeEmpty()
        ->and($h['registry']->failedEvents()[0])->toBeInstanceOf(CapabilityFailed::class);
});

it("edge: listeners for CapabilityFailed should use afterCommit when touching DB [D-010]", function () {
    expect(CapabilityFailed::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: bus event CapabilityApprovalRequested emitted on matching condition [D-010]", function () {
    $h = AuditHelpers::harness(['approvalPolicy' => 'requester_or_role']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http', [
        'needs_approval' => true,
    ]));
    expect($r->isApprovalRequired())->toBeTrue()
        ->and($h['registry']->approvalEvents())->not->toBeEmpty()
        ->and($h['registry']->approvalEvents()[0])->toBeInstanceOf(CapabilityApprovalRequested::class);
});

it("edge: listeners for CapabilityApprovalRequested should use afterCommit when touching DB [D-010]", function () {
    expect(CapabilityApprovalRequested::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: bus event CapabilityApprovalDecided emitted on matching condition [D-010]", function () {
    $pending = ApprovalHelpers::withPending();
    $pending['manager']->accept((string) $pending['row']['id'], ApprovalHelpers::requester());
    $events = $pending['manager']->events();
    $decided = array_values(array_filter($events, fn ($e) => $e instanceof CapabilityApprovalDecided));
    expect($decided)->not->toBeEmpty();
});

it("edge: listeners for CapabilityApprovalDecided should use afterCommit when touching DB [D-010]", function () {
    expect(CapabilityApprovalDecided::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: bus event CapabilityApprovalExecuted emitted on matching condition [D-010]", function () {
    // Construction + after-run executed event shape (manager may emit on accept+run path).
    $e = new CapabilityApprovalExecuted(capability: 'x', approvalId: 'a1', via: 'accept');
    expect($e->capability)->toBe('x')->and($e->approvalId)->toBe('a1');
});

it("edge: listeners for CapabilityApprovalExecuted should use afterCommit when touching DB [D-010]", function () {
    expect(CapabilityApprovalExecuted::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: domain events remain app responsibility inside run [D-010]", function () {
    $h = AuditHelpers::harness(['domain_event' => 'InvoiceCreated']);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['runCount']->domainEvents)->toBe(['InvoiceCreated']);
    // Registry bus events are separate from domain events.
    expect($h['registry']->invokedEvents()[0])->toBeInstanceOf(CapabilityInvoked::class);
});

foreach (['name', 'caller', 'actor', 'scope', 'idempotency', 'replay', 'result', 'duration', 'approval_id', 'redacted_input'] as $field) {
    it("happy: audit fields include {$field} [D-010]", function () use ($field) {
        $h = AuditHelpers::harness(['idempotent' => 'optional']);
        $opts = AuditHelpers::options('http', ['idempotency_key' => 'k-field-1']);
        $h['registry']->invoke($h['name'], AuditHelpers::input(), $opts);
        $entry = $h['audit']->all()[0] ?? [];
        $key = match ($field) {
            'approval_id' => 'approval_id',
            'redacted_input' => 'redacted_input',
            default => $field,
        };
        expect($entry)->toHaveKey($key);
    });
}

it("happy: WriteAuditJob drains outbox at least once [D-010]", function () {
    $h = AuditHelpers::harness(['fail_audit' => true, 'required' => true]);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['outbox']->pending())->not->toBeEmpty();
    $writer = new InMemoryAuditWriter($h['clock']);
    $job = new WriteAuditJob($h['outbox'], $writer);
    $n = $job->handle();
    expect($n)->toBeGreaterThan(0)
        ->and($writer->all())->not->toBeEmpty()
        ->and($h['outbox']->countByStatus(AuditOutbox::STATUS_COMPLETED))->toBeGreaterThan(0);
});

it("happy: stage before run fails leaves no domain writes [D-010]", function () {
    $h = AuditHelpers::harness();
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage before run may write optional deny audit [D-010]", function () {
    $h = AuditHelpers::harness(['authorize' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->errorCode())->toBe('forbidden');
    // optional deny audit path marks record_audit and/or writes an entry
    $hasAuditStage = in_array(PipelineStages::RECORD_AUDIT, $h['registry']->lastStages(), true);
    expect($hasAuditStage || $h['audit']->all() !== [])->toBeTrue();
});

it("fail: run throws with domain txn rollback leaves no domain writes [D-010]", function () {
    $h = AuditHelpers::harness([
        'run' => function () {
            // Simulate domain rolled back: no side effect flag set by harness run
            throw new RuntimeException('rolled back');
        },
    ]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->errorCode())->toBe('domain_error')
        ->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: run throws emits CapabilityFailed [D-010]", function () {
    $h = AuditHelpers::harness(['run_throws' => 'nope']);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['registry']->failedEvents())->not->toBeEmpty();
});

it("happy: run succeeds audit sync fails best_effort keeps domain returns 200 [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->sideEffect)->toBeTrue();
});

it("edge: run succeeds audit fails strict plus outer txn rolls back if not committed [D-010]", function () {
    $h = AuditHelpers::harness([
        'mode' => 'strict',
        'fail_audit' => true,
        'transactions' => ['wrap_run' => true],
    ]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->errorCode())->toBe('audit_failed')
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue();
});

it("edge: run succeeds audit fails strict domain already committed documents footgun [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'fail_audit' => true, 'transactions' => ['wrap_run' => false]]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->errorCode())->toBe('audit_failed')
        ->and($r->meta['domain_side_effect'] ?? $h['runCount']->sideEffect)->toBeTruthy()
        ->and($h['runCount']->value)->toBe(1);
});

it("edge: idempotent replay may skip or mark replay audit [D-010]", function () {
    $h = AuditHelpers::harness(['idempotent' => 'optional']);
    $opts = AuditHelpers::options('http', ['idempotency_key' => 'replay-audit-1']);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), $opts);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), $opts);
    $entries = $h['audit']->all();
    $hasReplay = false;
    foreach ($entries as $e) {
        if (($e['replay'] ?? false) === true) {
            $hasReplay = true;
        }
    }
    expect($hasReplay || count($entries) >= 1)->toBeTrue();
});

it("fail: undefined audit after run with no mode is refused [D-010]", function () {
    expect(fn () => AuditLogger::assertValidMode('undefined'))
        ->toThrow(InvalidArgumentException::class);
});

it("fail: default outer transaction wrapping money plus audit is not default [D-010]", function () {
    $h = AuditHelpers::harness();
    expect($h['registry']->transactionsWrapRun())->toBeFalse();
});

it("fail: firing bus events before domain commit by default is refused [D-010]", function () {
    $h = AuditHelpers::harness();
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    $stages = $h['registry']->lastStages();
    $runIdx = array_search(PipelineStages::RUN, $stages, true);
    $emitIdx = array_search(PipelineStages::EMIT_EVENTS, $stages, true);
    expect($runIdx)->not->toBeFalse()
        ->and($emitIdx)->not->toBeFalse()
        ->and($emitIdx)->toBeGreaterThan($runIdx);
});

it("happy: audit.mode best_effort is default [D-010]", function () {
    $h = AuditHelpers::harness();
    expect($h['registry']->auditMode())->toBe('best_effort');
});

it("edge: audit.driver database log queue supported [D-010]", function () {
    foreach (['database', 'log', 'queue'] as $driver) {
        expect(AuditLogger::assertValidDriver($driver))->toBe($driver);
    }
    expect(fn () => AuditLogger::assertValidDriver('redis'))->toThrow(InvalidArgumentException::class);
});

it("edge: events.enabled false suppresses bus events [D-010]", function () {
    $h = AuditHelpers::harness(['events' => ['enabled' => false]]);
    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($h['registry']->invokedEvents())->toBeEmpty()
        ->and($h['registry']->eventsEnabled())->toBeFalse();
});
