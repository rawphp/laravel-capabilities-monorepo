<?php

// L-201 / M-201 / D-006: once the pending approval row is saved, a notifier that throws
// (chat API outage, half-configured channel) or a throwing CapabilityApprovalRequested
// listener cannot turn the invoke into `internal`. The caller gets approval_required,
// the keyed idempotency row stays pending_approval, the remaining notifiers still run,
// and the failure is reported. Same side-channel rule as L-103 for bus listeners.

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Support\FailureReporter;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;
use Rawphp\Capabilities\Tests\Fixtures\RecordingExceptionHandler;

afterEach(fn () => RecordingExceptionHandler::unbind());

function l201ThrowingNotifier(): ApprovalNotifier
{
    return new class implements ApprovalNotifier
    {
        public function notifyPending(array $approval): void
        {
            throw new RuntimeException('telegram 502');
        }
    };
}

function l201RecordingNotifier(): ApprovalNotifier
{
    return new class implements ApprovalNotifier
    {
        /** @var list<string> */
        public array $notified = [];

        public function notifyPending(array $approval): void
        {
            $this->notified[] = (string) $approval['id'];
        }
    };
}

it('a throwing notifier leaves the invoke approval_required, the key row pending_approval, and later notifiers notified', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = IdempotencyHelpers::harness();
    $throwing = l201ThrowingNotifier();
    $recording = l201RecordingNotifier();
    $h['registry']->approvals()->addNotifier($throwing)->addNotifier($recording);
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'notify-boom', 'needs_approval' => true]);

    $result = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'notify-boom');
    $retry = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($row['status'])->toBe('pending_approval')
        ->and($row['approval_id'])->toBe($result->approvalId())
        ->and($recording->notified)->toBe([$result->approvalId()])
        ->and($retry->isApprovalRequired())->toBeTrue()
        ->and($retry->approvalId())->toBe($result->approvalId())
        ->and($h['fakes']->approvals->findByStatus(ApprovalStateMachine::STATUS_PENDING))->toHaveCount(1)
        ->and($h['runCount']->value)->toBe(0)
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->reported[0]->getMessage())->toBe('telegram 502')
        ->and($handler->metrics->get(FailureReporter::APPROVAL_NOTIFY_FAILED, ['notifier' => $throwing::class]))->toBe(1);
});

it('a throwing CapabilityApprovalRequested listener leaves the invoke approval_required and the key row pending_approval', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = IdempotencyHelpers::harness();
    $dispatcher = new Dispatcher;
    $dispatcher->listen(CapabilityApprovalRequested::class, function (): void {
        throw new RuntimeException('queue connection refused');
    });
    $h['registry']->withEventDispatcher($dispatcher);
    $opts = IdempotencyHelpers::options('http', ['idempotency_key' => 'requested-boom', 'needs_approval' => true]);

    $result = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
    $row = $h['store']->find('tenant-1', 'user', '7', $h['name'], 'requested-boom');

    expect($result->isApprovalRequired())->toBeTrue()
        ->and($row['status'])->toBe('pending_approval')
        ->and($row['approval_id'])->toBe($result->approvalId())
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->reported[0]->getMessage())->toBe('queue connection refused')
        ->and($handler->metrics->get(FailureReporter::LISTENER_FAILED, ['event' => CapabilityApprovalRequested::class]))->toBe(1);
});
