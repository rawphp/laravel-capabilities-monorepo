<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdateJob;
use Rawphp\CapabilitiesMessaging\Telegram\RetryableUpdateFailure;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Spec pipeline: verify webhook secret -> queue ProcessTelegramUpdate. The job must really queue,
 * and transient failures must fail the job so the queue retries (D-019 failed-job tags reachable).
 */
function botFailingWith(int $telegramCode): TelegramBotClient
{
    return new class($telegramCode) implements TelegramBotClient
    {
        public function __construct(private int $code) {}

        public function sendMessage(string $chatId, string $text, array $payload = []): array
        {
            throw TelegramBotApiException::fromResponse('sendMessage', ['ok' => false, 'error_code' => $this->code]);
        }

        public function editMessageText(string $chatId, string|int $messageId, string $text, array $payload = []): array
        {
            return ['ok' => true];
        }

        public function answerCallbackQuery(string $callbackQueryId, string $text = '', array $payload = []): array
        {
            return ['ok' => true];
        }
    };
}

function linkedIdentity(): IdentityLinker
{
    $identity = H::identity();
    $identity->link('42', 'u1');

    return $identity;
}

it('happy: ProcessTelegramUpdateJob is queued by the Laravel bus, with finite retries [L-004]', function () {
    $job = new ProcessTelegramUpdateJob([]);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60]);
});

it('fail: a retryable Bot API reply failure fails the job so the queue retries [D-019]', function () {
    $bot = botFailingWith(429);
    $processor = H::processor([
        'identity' => linkedIdentity(),
        'adapter' => new TelegramAdapter($bot, static fn () => ['text' => 'hi', 'tool_calls' => []]),
        // The retry re-sends the kept reply only (see ReplyRetryTest).
        'pending_replies' => new Repository(new ArrayStore),
    ]);

    expect(fn () => (new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 42)))->handle($processor))
        ->toThrow(RetryableUpdateFailure::class, 'reply_send_fail');
});

it('edge: a non-retryable Bot API reply failure is terminal (no retry) [D-019]', function () {
    $processor = H::processor([
        'identity' => linkedIdentity(),
        'adapter' => new TelegramAdapter(botFailingWith(403), static fn () => ['text' => 'hi', 'tool_calls' => []]),
    ]);

    $result = (new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 42)))->handle($processor);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toContain('reply_send_fail');
});

it('edge: a retryable registry result is answered by the agent, not retried with a new turn [D-005]', function () {
    // Retrying the job would ask the LLM again and could issue different tool calls.
    $bus = new FakeCapabilityBus;
    $bus->when('support.ping', CapabilityResult::failure(code: 'internal', message: 'db blip'));
    $agentCalls = 0;
    $processor = H::processor([
        'identity' => linkedIdentity(),
        'registry' => $bus,
        'adapter' => new TelegramAdapter(H::bot(), static function () use (&$agentCalls): array {
            $agentCalls++;

            return ['text' => 'x', 'tool_calls' => [['name' => 'support.ping', 'input' => []]]];
        }),
    ]);

    $result = (new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 42)))->handle($processor);

    expect($result['error'])->toBe('internal')
        ->and($result['steps'])->toContain('conversation_reply')
        ->and($bus->invokeCount())->toBe(1)
        ->and($agentCalls)->toBe(2);
});

it('edge: terminal outcomes return without throwing (identity_unresolved, forbidden) [D-019]', function () {
    $bus = new FakeCapabilityBus;
    $bus->alwaysFail('forbidden');
    $processor = H::processor(['registry' => $bus]);

    $result = (new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 404)))->handle($processor);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('identity_unresolved');
});
