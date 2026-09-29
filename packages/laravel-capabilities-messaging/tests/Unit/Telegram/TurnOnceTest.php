<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\RetryableUpdateFailure;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\RecordingLogger;

/**
 * D-005 / M-205: once an update's agent turn has started, a redelivery (worker timeout or crash,
 * Telegram resending the webhook) never starts another. A per-update marker in the host cache
 * is claimed before the turn; only a kept pending reply is ever re-sent.
 */
final class TurnOnceScenario
{
    public int $agentCalls = 0;

    public bool $interruptFollowUp = false;

    public FakeCapabilityBus $bus;

    public RecordingLogger $logger;

    public ProcessTelegramUpdate $processor;

    public function __construct(public ?Repository $cache = new Repository(new ArrayStore))
    {
        $this->bus = new FakeCapabilityBus;
        $this->logger = new RecordingLogger;
        $identity = H::identity();
        $identity->link('42', 'u1');
        $bot = H::bot();
        $this->processor = new ProcessTelegramUpdate(
            config: H::config(),
            identity: $identity,
            threads: H::threads(),
            adapter: new TelegramAdapter($bot, function (array $m): array {
                $this->agentCalls++;
                if (isset($m['tool_results']) && $this->interruptFollowUp) {
                    // Stands in for a worker timeout / crash mid-turn: the attempt ends unfinished.
                    throw new RetryableUpdateFailure('worker killed');
                }

                return isset($m['tool_results'])
                    ? ['text' => 'refunded']
                    : ['text' => 'on it', 'tool_calls' => [['name' => 'billing.refund', 'input' => ['amount' => 5]]]];
            }),
            registry: $this->bus,
            bot: $bot,
            profileResolver: static fn (): array => ['billing.refund'],
            logger: $this->logger,
            pendingReplies: $cache,
        );
    }
}

it('fail: a redelivery after an interrupted turn never calls the agent or its tools again [M-205]', function () {
    $s = new TurnOnceScenario;
    $s->interruptFollowUp = true;
    $update = H::telegramUpdate(userId: 42, updateId: 90);

    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    $retry = $s->processor->handle($update);

    expect($retry['ok'])->toBeFalse()
        ->and($retry['error'])->toBe('turn_already_started')
        ->and($s->agentCalls)->toBe(2)
        ->and($s->bus->invokeCount())->toBe(1)
        ->and(end($s->logger->records)['level'])->toBe('warning');
});

it('fail: a second delivery of a completed update runs nothing and sends nothing [M-205]', function () {
    $s = new TurnOnceScenario;
    $update = H::telegramUpdate(userId: 42, updateId: 91);

    $first = $s->processor->handle($update);
    $second = $s->processor->handle($update);

    expect($first['ok'])->toBeTrue()
        ->and($second['error'])->toBe('turn_already_started')
        ->and($s->agentCalls)->toBe(2)
        ->and($s->bus->invokeCount())->toBe(1);
});

it('happy: the next update from the same chat is a new turn [M-205]', function () {
    $s = new TurnOnceScenario;

    $s->processor->handle(H::telegramUpdate(userId: 42, updateId: 92));
    $next = $s->processor->handle(H::telegramUpdate(userId: 42, updateId: 93));

    expect($next['ok'])->toBeTrue()
        ->and($s->agentCalls)->toBe(4);
});

it('edge: without a cache store a redelivery runs the turn again; tool calls replay by idempotency key [M-205 / D-005]', function () {
    $s = new TurnOnceScenario(cache: null);
    $update = H::telegramUpdate(userId: 42, updateId: 94);

    $s->processor->handle($update);
    $s->processor->handle($update);

    $keys = array_map(fn (array $i) => $i['options']['idempotency_key'], $s->bus->invocations());
    expect($s->agentCalls)->toBe(4)
        ->and($keys)->toBe(['telegram:100:94:0', 'telegram:100:94:0']);
});
