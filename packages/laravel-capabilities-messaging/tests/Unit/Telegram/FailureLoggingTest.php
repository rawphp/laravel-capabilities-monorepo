<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\RecordingLogger;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;

/**
 * D-019: webhook and processing failures reach the host PSR-3 logger with channel/chat/update tags.
 */
function loggedProcessor(RecordingLogger $logger, ?IdentityLinker $identity = null, ?TelegramAdapter $adapter = null): ProcessTelegramUpdate
{
    return new ProcessTelegramUpdate(
        H::config(),
        $identity ?? H::identity(),
        new ThreadStore,
        $adapter ?? new TelegramAdapter(H::bot(), static fn () => ['text' => 'ok', 'tool_calls' => []]),
        new FakeCapabilityBus,
        H::bot(),
        static fn () => ['support.ping'],
        logger: $logger,
    );
}

it('happy: an unlinked user logs one warning tagged with chat and update ids [D-019]', function () {
    $logger = new RecordingLogger;

    loggedProcessor($logger)->handle(H::telegramUpdate(chatId: 100, userId: 404, updateId: 9));

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('warning')
        ->and($logger->records[0]['message'])->toContain('identity_unresolved')
        ->and($logger->records[0]['context']['tags'])->toBe(['channel' => 'telegram', 'chat_id' => '100', 'update_id' => 9]);
});

it('fail: an unexpected processing failure logs an error [D-019]', function () {
    $logger = new RecordingLogger;
    $identity = H::identity();
    $identity->link('42', 'u1');
    $adapter = new TelegramAdapter(H::bot(), static fn () => throw new RuntimeException('agent exploded'));

    loggedProcessor($logger, $identity, $adapter)->handle(H::telegramUpdate(userId: 42));

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe('error')
        ->and($logger->records[0]['context']['failure'])->toBe('agent exploded');
});

it('edge: a successful update logs nothing [D-019]', function () {
    $logger = new RecordingLogger;
    $identity = H::identity();
    $identity->link('42', 'u1');

    $r = loggedProcessor($logger, $identity)->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeTrue()->and($logger->records)->toBe([]);
});

it('happy: webhook bad secret and queue failures reach the logger [D-019]', function () {
    $logger = new RecordingLogger;
    $queue = new FakeQueue;
    $webhook = new TelegramWebhookController(H::config(), $queue, $logger);

    $webhook->handle(['X-Telegram-Bot-Api-Secret-Token' => 'wrong'], H::telegramUpdate());
    $queue->failNextPush();
    $webhook->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], H::telegramUpdate());

    expect(array_column($logger->records, 'level'))->toBe(['warning', 'error'])
        ->and($logger->records[0]['context']['phase'])->toBe('verify_webhook_secret')
        ->and($logger->records[1]['context']['phase'])->toBe('queue');
});

it('happy: provider injects the bound PSR-3 logger into webhook and processor [D-019]', function () {
    $app = H::container(['telegram' => ['bot_token' => 't', 'webhook_secret' => 's']]);
    $logger = new RecordingLogger;
    $app->instance(LoggerInterface::class, $logger);

    $app->make(TelegramWebhookController::class)->handle([], H::telegramUpdate());
    $app->make(ProcessTelegramUpdate::class)->handle(H::telegramUpdate(userId: 404));

    expect($logger->records)->toHaveCount(2);
});

it('edge: without a bound logger the provider still builds both services [D-019]', function () {
    $app = H::container(['telegram' => []]);

    expect($app->make(TelegramWebhookController::class)->handle([], []))->toMatchArray(['ok' => false])
        ->and($app->make(ProcessTelegramUpdate::class)->handle(['garbage' => true]))->toMatchArray(['ok' => false]);
});
