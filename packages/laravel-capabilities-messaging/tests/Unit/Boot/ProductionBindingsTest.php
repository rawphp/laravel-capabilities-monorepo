<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LaravelUpdateQueue;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\UpdateQueue;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdateJob;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * REQ-074 / L-004: production bindings vs fake/testing selection.
 * No live Telegram network; HTTP bot uses injected transport only.
 */

it('production auto drivers select laravel queue and http bot [L-004]', function () {
    $resolved = MessagingBindings::resolve([
        'telegram' => ['enabled' => true, 'bot_token' => 't', 'webhook_secret' => 's'],
    ], 'production');

    expect($resolved['drivers']['queue'])->toBe('laravel')
        ->and($resolved['drivers']['bot'])->toBe('http')
        ->and($resolved['bindings'][UpdateQueue::class])->toBe(LaravelUpdateQueue::class)
        ->and($resolved['bindings'][TelegramBotClient::class])->toBe(HttpTelegramBotClient::class)
        ->and($resolved['register_bindings'])->toBeTrue()
        ->and($resolved['residuals'])->toHaveKey('L-006');
});

it('testing auto drivers select FakeQueue and FakeTelegramBotClient [L-004]', function () {
    $resolved = MessagingBindings::resolve([
        'telegram' => ['enabled' => true],
    ], 'testing');

    expect($resolved['drivers']['queue'])->toBe('fake')
        ->and($resolved['drivers']['bot'])->toBe('fake')
        ->and($resolved['bindings'][UpdateQueue::class])->toBe(FakeQueue::class)
        ->and($resolved['bindings'][TelegramBotClient::class])->toBe(FakeTelegramBotClient::class);
});

it('explicit driver=fake forces fakes even in production [L-004]', function () {
    $resolved = MessagingBindings::resolve([
        'telegram' => ['enabled' => true],
        'queue_driver' => 'fake',
        'bot_driver' => 'fake',
    ], 'production');

    expect($resolved['drivers']['queue'])->toBe('fake')
        ->and($resolved['drivers']['bot'])->toBe('fake')
        ->and($resolved['bindings'][UpdateQueue::class])->toBe(FakeQueue::class)
        ->and($resolved['bindings'][TelegramBotClient::class])->toBe(FakeTelegramBotClient::class);
});

it('explicit production drivers override testing env [L-004]', function () {
    $resolved = MessagingBindings::resolve([
        'telegram' => ['enabled' => true],
        'queue_driver' => 'laravel',
        'bot_driver' => 'http',
    ], 'testing');

    expect($resolved['drivers']['queue'])->toBe('laravel')
        ->and($resolved['drivers']['bot'])->toBe('http');
});

it('does not register production bindings when telegram disabled [L-004]', function () {
    $resolved = MessagingBindings::resolve([
        'telegram' => ['enabled' => false],
    ], 'production');

    expect($resolved['register_bindings'])->toBeFalse()
        ->and($resolved['telegram_enabled'])->toBeFalse();
});

it('build production path constructs LaravelUpdateQueue and HttpTelegramBotClient without network [L-004]', function () {
    $dispatched = [];
    $transportCalls = [];

    $built = MessagingBindings::build(
        [
            'telegram' => [
                'enabled' => true,
                'bot_token' => 'tok',
                'webhook_secret' => 'sec',
            ],
            'queue_driver' => 'laravel',
            'bot_driver' => 'http',
        ],
        'production',
        queueDispatcher: function (string $job, array $payload) use (&$dispatched): void {
            $dispatched[] = ['job' => $job, 'payload' => $payload];
        },
        httpTransport: function (string $method, array $params) use (&$transportCalls): array {
            $transportCalls[] = ['method' => $method, 'params' => $params];

            return ['ok' => true, 'result' => ['message_id' => 9]];
        },
    );

    expect($built['queue'])->toBeInstanceOf(LaravelUpdateQueue::class)
        ->and($built['bot'])->toBeInstanceOf(HttpTelegramBotClient::class)
        ->and($built['aliases'][UpdateQueue::class])->toBe(LaravelUpdateQueue::class)
        ->and($built['aliases'][TelegramBotClient::class])->toBe(HttpTelegramBotClient::class)
        ->and($built['residuals'])->toHaveKey('L-006');

    $built['queue']->push(ProcessTelegramUpdate::class, ['update' => ['update_id' => 1]]);
    expect($dispatched)->toHaveCount(1)
        ->and($dispatched[0]['job'])->toBe(ProcessTelegramUpdate::class);

    $built['bot']->sendMessage('1', 'hi');
    expect($transportCalls)->toHaveCount(1)
        ->and($transportCalls[0]['method'])->toBe('sendMessage');
});

it('build testing path still uses Fake* by default [L-004]', function () {
    $built = MessagingBindings::build([
        'telegram' => ['enabled' => true, 'bot_token' => 't', 'webhook_secret' => 's'],
    ], 'testing');

    expect($built['queue'])->toBeInstanceOf(FakeQueue::class)
        ->and($built['bot'])->toBeInstanceOf(FakeTelegramBotClient::class);
});

it('webhook requires UpdateQueue and does not default FakeQueue [L-004]', function () {
    $config = H::config();
    $ref = new ReflectionClass(TelegramWebhookController::class);
    $ctor = $ref->getConstructor();
    expect($ctor)->not->toBeNull();
    $params = $ctor->getParameters();
    expect($params)->toHaveCount(2)
        ->and($params[1]->getName())->toBe('queue')
        ->and($params[1]->allowsNull())->toBeFalse();

    $queue = new FakeQueue;
    $ctrl = new TelegramWebhookController($config, $queue);
    expect($ctrl->queue())->toBe($queue)
        ->and($ctrl->queue())->toBeInstanceOf(UpdateQueue::class)
        ->and($ctrl->queue())->not->toBeInstanceOf(LaravelUpdateQueue::class); // injected Fake for unit test only
});

it('LaravelUpdateQueue dispatches via injected dispatcher [L-004]', function () {
    $seen = [];
    $q = new LaravelUpdateQueue(function (string $job, array $payload) use (&$seen): void {
        $seen[] = compact('job', 'payload');
    });
    $q->push(ProcessTelegramUpdate::class, ['update' => ['x' => 1]]);
    expect($seen)->toHaveCount(1)
        ->and($seen[0]['job'])->toBe(ProcessTelegramUpdate::class)
        ->and($seen[0]['payload']['update']['x'])->toBe(1);
});

it('HttpTelegramBotClient uses transport only — no network [L-004]', function () {
    $cfg = H::config(['telegram' => ['bot_token' => 'secret-token']]);
    $calls = [];
    $bot = new HttpTelegramBotClient(
        $cfg,
        function (string $method, array $params, string $token) use (&$calls): array {
            $calls[] = compact('method', 'params', 'token');

            return ['ok' => true, 'result' => ['message_id' => 3, 'text' => $params['text'] ?? '']];
        },
    );

    $out = $bot->sendMessage('42', 'hello', ['parse_mode' => 'HTML']);
    expect($out['ok'])->toBeTrue()
        ->and($calls[0]['method'])->toBe('sendMessage')
        ->and($calls[0]['token'])->toBe('secret-token')
        ->and($calls[0]['params']['chat_id'])->toBe('42')
        ->and($calls[0]['params']['text'])->toBe('hello');

    $bot->editMessageText('42', 3, 'edited');
    expect($calls[1]['method'])->toBe('editMessageText');
    expect($bot->calls())->toHaveCount(2);
});

it('HttpTelegramBotClient fails closed without bot token [L-004]', function () {
    $cfg = MessagingConfig::fromArray([
        'telegram' => ['bot_token' => null, 'webhook_secret' => 's'],
    ], 'production');
    $bot = new HttpTelegramBotClient($cfg, static fn (): array => ['ok' => true]);
    expect(fn () => $bot->sendMessage('1', 'x'))->toThrow(RuntimeException::class);
});

it('ProcessTelegramUpdateJob invokes ProcessTelegramUpdate handle [L-004]', function () {
    $processor = H::processor();
    $job = new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 99));
    // identity not linked → may fail pipeline; still exercises handle wiring
    $identity = H::identity();
    $identity->link('99', 'u99');
    $processor = H::processor(['identity' => $identity]);
    $job = new ProcessTelegramUpdateJob(H::telegramUpdate(userId: 99));
    $result = $job->handle($processor);
    expect($result)->toBeArray()
        ->and($result['ok'] ?? null)->not->toBeNull();
});

it('registrationPlan exposes production binding classes and L-006 residual [L-004]', function () {
    $plan = MessagingServiceProvider::registrationPlan([
        'telegram' => ['enabled' => true],
        'queue_driver' => 'laravel',
        'bot_driver' => 'http',
    ], 'production');

    expect($plan['register_bindings'])->toBeTrue()
        ->and($plan['binding_concretes'][UpdateQueue::class])->toBe(LaravelUpdateQueue::class)
        ->and($plan['binding_concretes'][TelegramBotClient::class])->toBe(HttpTelegramBotClient::class)
        ->and($plan['residuals']['L-006'])->toBeString()
        ->and($plan['residuals']['L-006'])->toContain('durable');
});

it('provider source register() binds MessagingConfig and UpdateQueue [L-004]', function () {
    $src = file_get_contents(H::MSG_SRC.'/MessagingServiceProvider.php');
    expect($src)->toContain('singleton(MessagingConfig::class')
        ->and($src)->toContain('singleton(UpdateQueue::class')
        ->and($src)->toContain('singleton(TelegramBotClient::class')
        ->and($src)->toContain('singleton(ProcessTelegramUpdate::class')
        ->and($src)->toContain('LaravelUpdateQueue')
        ->and($src)->toContain('ProcessTelegramUpdateJob')
        // FakeQueue only on explicit fake driver branch — not a hard production default
        ->and($src)->toContain("drivers['queue'] === 'fake'");
});

it('webhook controller source does not hard-default FakeQueue [L-004]', function () {
    $src = file_get_contents(H::MSG_SRC.'/Telegram/TelegramWebhookController.php');
    expect($src)->not->toMatch('/\$queue\s*\?\?\s*new\s+FakeQueue/')
        ->and($src)->not->toContain('use Rawphp\CapabilitiesMessaging\Support\FakeQueue');
});

it('README documents L-006 residual for identity/threads [L-004]', function () {
    $readme = file_get_contents(H::MSG_ROOT.'/README.md');
    expect($readme)->toContain('L-006')
        ->and($readme)->toMatch('/in-memory|process-local|not durable/i');
});
