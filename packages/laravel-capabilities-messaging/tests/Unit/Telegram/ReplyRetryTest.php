<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\RetryableUpdateFailure;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\InMemoryUserModel;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * A failed reply send retries only the send (M-103): the agent turn and its tool invokes run at
 * most once per update. The pending reply is kept in the host cache between queue attempts.
 */
final class FlakyBot implements TelegramBotClient
{
    /** @var list<array{chat_id: string, text: string, payload: array<string, mixed>}> */
    public array $sent = [];

    /** @param  list<int>  $failures  Bot API error codes for the next sends, in order */
    public function __construct(public array $failures = []) {}

    public function sendMessage(string $chatId, string $text, array $payload = []): array
    {
        $code = array_shift($this->failures);
        if ($code !== null) {
            throw TelegramBotApiException::fromResponse('sendMessage', ['ok' => false, 'error_code' => $code]);
        }
        $this->sent[] = ['chat_id' => $chatId, 'text' => $text, 'payload' => $payload];

        return ['ok' => true];
    }

    public function editMessageText(string $chatId, string|int $messageId, string $text, array $payload = []): array
    {
        return ['ok' => true];
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', array $payload = []): array
    {
        return ['ok' => true];
    }
}

final class ReplyRetryScenario
{
    public int $agentCalls = 0;

    public FakeCapabilityBus $bus;

    public ProcessTelegramUpdate $processor;

    public function __construct(public FlakyBot $bot, public ?Repository $cache = new Repository(new ArrayStore))
    {
        $this->bus = new FakeCapabilityBus;
        $identity = H::identity();
        $identity->link('42', 'u1');
        $this->processor = H::processor([
            'identity' => $identity,
            'registry' => $this->bus,
            'adapter' => new TelegramAdapter($bot, function (array $m): array {
                $this->agentCalls++;

                return isset($m['tool_results'])
                    ? ['text' => 'closed ticket']
                    : ['text' => 'on it', 'tool_calls' => [['name' => 'support.ping', 'input' => ['id' => 1]]]];
            }),
            'pending_replies' => $cache,
        ]);
    }
}

it('fail: a retry after a transient reply failure re-sends the reply without a new agent turn [D-005]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([429]));
    $update = H::telegramUpdate(userId: 42, topicId: 3, updateId: 77);

    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class, 'reply_send_fail');
    $retry = $s->processor->handle($update);

    expect($retry['ok'])->toBeTrue()
        ->and($s->agentCalls)->toBe(2)
        ->and($s->bus->invokeCount())->toBe(1)
        ->and($s->bot->sent)->toHaveCount(1)
        ->and($s->bot->sent[0]['text'])->toBe('closed ticket')
        ->and($s->bot->sent[0]['payload'])->toBe(['message_thread_id' => 3]);
});

it('edge: a delivered retry drops the pending reply [D-005]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([502]));
    $update = H::telegramUpdate(userId: 42, updateId: 78);

    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    $s->processor->handle($update);
    $s->processor->handle($update);

    // The pending reply was consumed: a later redelivery is a new turn again.
    expect($s->agentCalls)->toBe(4)
        ->and($s->bot->sent)->toHaveCount(2);
});

it('fail: a retry that fails transiently again keeps the pending reply for the next attempt [D-019]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([429, 429]));
    $update = H::telegramUpdate(userId: 42, updateId: 79);

    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    $s->processor->handle($update);

    expect($s->agentCalls)->toBe(2)
        ->and($s->bot->sent[0]['text'])->toBe('closed ticket');
});

it('fail: a retry that fails terminally drops the pending reply without retrying [D-019]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([429, 403]));
    $update = H::telegramUpdate(userId: 42, updateId: 80);

    expect(fn () => $s->processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    $r = $s->processor->handle($update);

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toStartWith('reply_send_fail')
        ->and($s->cache->has('capabilities-messaging:reply:telegram:100:80'))->toBeFalse();
});

it('edge: a pending reply is re-sent before the chat turn limit is counted [D-013]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([429]));
    $limiter = new InMemoryRateLimiter;
    $identity = H::identity();
    $identity->link('42', 'u1');
    $processor = new ProcessTelegramUpdate(
        config: H::config(['telegram' => ['turns_per_minute' => 1]]),
        identity: $identity,
        threads: H::threads(),
        adapter: new TelegramAdapter($s->bot, static fn (array $m): array => ['text' => 'hi']),
        turnLimiter: $limiter,
        pendingReplies: $s->cache,
    );
    $update = H::telegramUpdate(userId: 42, updateId: 81);

    expect(fn () => $processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    expect($processor->handle($update)['ok'])->toBeTrue()
        ->and($limiter->attemptCount('rl:telegram:chat:100'))->toBe(1);
});

it('fail: without a pending-reply store a transient reply failure is terminal, never a second turn [D-005]', function () {
    $s = new ReplyRetryScenario(new FlakyBot([429]), cache: null);

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toStartWith('reply_send_fail');
});

it('happy: the provider wires the host cache as the pending-reply store [D-005]', function () {
    InMemoryUserModel::seed(['u1']);
    $bot = new FlakyBot([429]);
    $app = H::container([
        'telegram' => ['bot_token' => 't', 'webhook_secret' => 's'],
        'agent_profile' => 'support',
        'user_model' => InMemoryUserModel::class,
    ]);
    $app->instance(TelegramBotClient::class, $bot);
    $app->make(IdentityLinker::class)->link('42', 'u1');
    $turn = new class implements AgentTurn
    {
        public int $turns = 0;

        public function toolNames(string $profile): array
        {
            return [];
        }

        public function respond(array $message): array
        {
            $this->turns++;

            return ['text' => 'hi'];
        }

        public function respondWithResults(array $message, array $toolResults): array
        {
            return ['text' => 'unused'];
        }
    };
    $app->instance(AgentTurn::class, $turn);
    $processor = $app->make(ProcessTelegramUpdate::class);
    $update = H::telegramUpdate(userId: 42, updateId: 82);

    expect(fn () => $processor->handle($update))->toThrow(RetryableUpdateFailure::class);
    expect($processor->handle($update)['ok'])->toBeTrue()
        ->and($turn->turns)->toBe(1)
        ->and($bot->sent[0]['text'])->toBe('hi');
});
