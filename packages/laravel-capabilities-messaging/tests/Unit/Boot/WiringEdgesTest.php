<?php

declare(strict_types=1);

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LaravelUpdateQueue;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdateJob;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Production wiring edges: the laravel queue driver hands the bus a queued job (L-004),
 * unknown drivers fail loudly, and malformed transports / callbacks fail closed.
 */
/**
 * Stand-in for the Laravel bus: records dispatched commands (duck-typed so it fits every
 * supported illuminate/bus Dispatcher version).
 */
function recordingBus(): object
{
    return new class
    {
        /** @var list<object> */
        public array $dispatched = [];

        public function dispatch(object $command): mixed
        {
            $this->dispatched[] = $command;

            return null;
        }
    };
}

it('happy: laravel queue driver dispatches a queued ProcessTelegramUpdateJob through the bus [L-004]', function () {
    $app = H::container(['queue_driver' => 'laravel']);
    $bus = recordingBus();
    $app->instance(BusDispatcher::class, $bus);

    $queue = $app->make(UpdateQueue::class);
    $queue->push(ProcessTelegramUpdate::class, ['update' => H::telegramUpdate(updateId: 5)]);

    expect($queue)->toBeInstanceOf(LaravelUpdateQueue::class)
        ->and($bus->dispatched)->toHaveCount(1)
        ->and($bus->dispatched[0])->toBeInstanceOf(ProcessTelegramUpdateJob::class)
        ->and($bus->dispatched[0])->toBeInstanceOf(ShouldQueue::class)
        ->and($bus->dispatched[0]->update['update_id'])->toBe(5);
});

it('fail: laravel queue driver without a bus or without an update array refuses to push [L-004]', function () {
    $app = H::container(['queue_driver' => 'laravel']);
    $queue = $app->make(UpdateQueue::class);

    expect(fn () => $queue->push(ProcessTelegramUpdate::class, ['update' => H::telegramUpdate()]))
        ->toThrow(RuntimeException::class, 'requires Illuminate')
        ->and(fn () => $queue->push(ProcessTelegramUpdate::class, ['update' => 'nope']))
        ->toThrow(RuntimeException::class, 'must include an update array');
});

it('fail: unknown queue or bot drivers fail loudly [L-004]', function () {
    expect(fn () => MessagingBindings::resolveDrivers(['queue_driver' => 'sqs']))->toThrow(RuntimeException::class, 'queue_driver')
        ->and(fn () => MessagingBindings::resolveDrivers(['bot_driver' => 'grpc']))->toThrow(RuntimeException::class, 'bot_driver')
        ->and(fn () => MessagingBindings::makeBot(H::config(), 'grpc'))->toThrow(RuntimeException::class)
        ->and(fn () => MessagingBindings::makeQueue('sqs'))->toThrow(RuntimeException::class);
});

it('fail: a laravel queue built without a dispatcher refuses to push [L-004]', function () {
    expect(fn () => MessagingBindings::makeQueue('laravel')->push('Job', []))
        ->toThrow(RuntimeException::class, 'injected dispatcher');
});

it('fail: HttpTelegramBotClient rejects a non-array transport response [D-018]', function () {
    $bot = new HttpTelegramBotClient(MessagingConfig::fromArray(['telegram' => ['bot_token' => 't']]), static fn () => 'oops');

    expect(fn () => $bot->sendMessage('1', 'hi'))->toThrow(RuntimeException::class, 'non-array');
});

it('fail: CallbackHandler refuses a tampered hinted payload, an unsigned action, and a non-pending row [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-e', 'capability_name' => 'x', 'requester_actor_type' => 'user',
        'requester_actor_id' => 'u9', 'original_caller' => 'http', 'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $s = H::signer();
    $handler = new CallbackHandler($s, $identity, $approvals);

    $tampered = $s->sign('ap-e', 'accept', 'u1');
    $tampered['approver_hint'] = 'u2';
    $bogusAction = ['approval_id' => 'ap-e', 'action' => 'delete', 'exp' => time() + 60, 'sig' => 'AAAAAAAAAAAAAAAA'];

    expect($handler->handle($tampered, ['id' => '42'])['message'])->toBe('invalid_signature_or_expired')
        ->and($handler->handle($bogusAction, ['id' => '42'])['message'])->toBe('unsupported_action');

    $approvals->store()->update('ap-e', ['status' => 'executing']);
    expect($handler->handle($s->sign('ap-e', 'accept'), ['id' => '42'])['status'])->toBe('already_handled');
});
