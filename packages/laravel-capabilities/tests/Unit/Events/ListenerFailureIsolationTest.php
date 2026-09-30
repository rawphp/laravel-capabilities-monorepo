<?php

// L-103 / D-010: once run() has committed, a host listener that throws (or a queued listener
// whose push fails) cannot turn the invoke into `internal`, rewrite the stored idempotency
// row, or mark an executed approval as failed. The failure is reported; the outcome stands.

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\RecordingExceptionHandler;

afterEach(fn () => RecordingExceptionHandler::unbind());

function l103ThrowingDispatcher(string $eventClass): Dispatcher
{
    $dispatcher = new Dispatcher;
    $dispatcher->listen($eventClass, function (): void {
        throw new RuntimeException('queue connection refused');
    });

    return $dispatcher;
}

it('a throwing CapabilityInvoked listener leaves the invoke ok and the key row completed', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = IdempotencyHelpers::harness();
    $h['registry']->withEventDispatcher(l103ThrowingDispatcher(CapabilityInvoked::class));
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'listener-boom']);

    $result = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'listener-boom');
    $retry = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($result->isOk())->toBeTrue()
        ->and($row['status'])->toBe('completed')
        ->and($retry->isOk())->toBeTrue()
        ->and($retry->isReplay())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['registry']->failedEvents())->toBe([])
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->reported[0]->getMessage())->toBe('queue connection refused');
});

it('a throwing CapabilityApprovalExecuted listener leaves the approval executed ok', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = PipelineHelpers::harness();
    $h['registry']->withEventDispatcher(l103ThrowingDispatcher(CapabilityApprovalExecuted::class));

    $pending = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';
    $accepted = $h['registry']->approvals()->accept((string) $pending->approvalId(), $approver);
    $row = $h['fakes']->approvals->find((string) $pending->approvalId());

    expect($accepted->isOk())->toBeTrue()
        ->and($row['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and($row['result_status'])->toBe('ok')
        ->and($handler->reported)->toHaveCount(1);
});

it('a throwing CapabilityApprovalDecided listener does not abort the decision', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = ApprovalHelpers::withPending();
    $manager = $h['manager']->withEventDispatcher(l103ThrowingDispatcher(CapabilityApprovalDecided::class));

    $result = $manager->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'nope');

    expect($result->isOk())->toBeFalse()
        ->and($h['store']->find((string) $h['row']['id'])['status'])->toBe(ApprovalStateMachine::STATUS_REJECTED)
        ->and($handler->reported)->toHaveCount(1);
});
