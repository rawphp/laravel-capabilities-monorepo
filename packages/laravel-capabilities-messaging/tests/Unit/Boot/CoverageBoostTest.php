<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\Boot\TelegramSetup;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;

/**
 * Targeted branch coverage for messaging package (≥95%).
 */
it('covers MessagingBindings build and aliases', function () {
    $built = MessagingBindings::build([
        'telegram' => [
            'enabled' => true,
            'bot_token' => 't',
            'webhook_secret' => 's',
            'callback_ttl_seconds' => 60,
        ],
        'agent_profile' => 'support',
    ]);
    expect($built['config'])->toBeInstanceOf(MessagingConfig::class);
    expect($built['webhook'])->toBeInstanceOf(TelegramWebhookController::class);
    expect($built['processor'])->toBeInstanceOf(ProcessTelegramUpdate::class);
    expect($built['notifier'])->toBeInstanceOf(TelegramApprovalNotifier::class);
    expect($built['aliases'])->not->toBeEmpty();
    expect(MessagingBindings::singletonKeys())->toContain(MessagingConfig::class);
    $plan = MessagingServiceProvider::registrationPlan();
    expect($plan['bindings_built'])->toContain('adapter');
});

it('covers MessagingConfig edges', function () {
    $cfg = MessagingConfig::fromArray([
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            'webhook_secret' => '',
            'callback_secret' => '',
            'callback_ttl_seconds' => 30,
        ],
        'agent_profile' => null,
        'identity' => ['mode' => 'allowlist', 'allowlist' => 'bad'],
        'skip_boot_checks' => true,
    ], 'testing');
    expect($cfg->telegramEnabled())->toBeFalse();
    expect($cfg->botToken())->toBeNull();
    expect($cfg->webhookSecret())->toBeNull();
    expect($cfg->allowlist())->toBeEmpty();
    expect($cfg->maySkipSecretChecks())->toBeTrue();
    expect($cfg->hasKey('missing.nested'))->toBeFalse();
    expect($cfg->appEnv())->toBe('testing');
    expect($cfg->all())->toHaveKey('telegram');
    $cfg->requireTelegramSecrets(); // skipped
    expect(fn () => $cfg->callbackSecret())->toThrow(RuntimeException::class);
    expect(fn () => $cfg->requireAgentProfile())->toThrow(RuntimeException::class);

    $prod = MessagingConfig::fromArray(['skip_boot_checks' => true], 'production');
    expect($prod->maySkipSecretChecks())->toBeFalse();
    $health = MessagingConfig::fromArray([
        'telegram' => ['bot_token' => null, 'webhook_secret' => 'x'],
        'agent_profile' => 'p',
    ])->health();
    expect($health['ready'])->toBeFalse();
});

it('covers IdentityLinker extract paths and user factory', function () {
    $id = new IdentityLinker(H::config(), static fn (string $uid, ?string $t) => new LinkedUser($uid, $t, null, 'N'));
    $id->link('10', 'u10', 't1');
    expect($id->resolve(['from' => ['id' => 10]]))->not->toBeNull();
    expect($id->resolve(['user_id' => '10']))->not->toBeNull();
    expect($id->resolve([]))->toBeNull();
});

// LinkedUser getAuthIdentifier
it('covers LinkedUser auth identifier', function () {
    $u = new LinkedUser('abc', 't', 'tg', 'Name');
    expect($u->getAuthIdentifier())->toBe('abc');
    expect($u->name)->toBe('Name');
    expect($u->telegramUserId)->toBe('tg');
});

it('covers FakeTelegramBotClient reset and fail', function () {
    $bot = new FakeTelegramBotClient;
    $bot->failNextSend(true);
    expect(fn () => $bot->sendMessage('1', 'hi'))->toThrow(RuntimeException::class);
    $bot->sendMessage('1', 'ok');
    $bot->editMessageText('1', 2, 'edited');
    expect($bot->calls())->not->toBeEmpty();
    $bot->reset();
    expect($bot->calls())->toBeEmpty();
});

it('covers FakeQueue reset', function () {
    $q = new FakeQueue;
    $q->push('Job', ['a' => 1]);
    expect($q->count())->toBe(1);
    $q->reset();
    expect($q->pushed())->toBeEmpty();
});

it('covers TelegramCallbackSigner encode decode and empty secret', function () {
    expect(fn () => new TelegramCallbackSigner(''))->toThrow(RuntimeException::class);
    $s = H::signer();
    $p = $s->sign('a', 'accept');
    $tok = $s->encode($p);
    expect($s->decode($tok))->toMatchArray(['approval_id' => 'a']);
    expect($s->decode('!!!'))->toBeNull();
    expect($s->verify(['sig' => '', 'approval_id' => 'a', 'action' => 'accept', 'exp' => time() + 10]))->toBeFalse();
    expect($s->verify(['sig' => 'x']))->toBeFalse();
});

it('covers TelegramAdapter fail modes and handlers', function () {
    $bot = H::bot();
    $a = new TelegramAdapter($bot, static fn (array $m) => ['text' => 'custom', 'tool_calls' => []]);
    $a->failIngress(true);
    expect(fn () => $a->handle(['text' => 'x']))->toThrow(RuntimeException::class);
    $a->failIngress(false);
    expect($a->handle((object) ['text' => 'obj']))->toBeArray();
    $a->failReply(true);
    expect(fn () => $a->reply(['chat_id' => '1', 'text' => 't']))->toThrow(RuntimeException::class);
    $a->failReply(false);
    $a->reply((object) ['chat_id' => '1', 'text' => 't']);
    expect($a->handled())->not->toBeEmpty();
    expect($a->replies())->not->toBeEmpty();
});

it('covers ThreadStore failures and unknown thread', function () {
    $s = new ThreadStore;
    expect(fn () => $s->appendHistory('missing', []))->toThrow(RuntimeException::class);
    $s->failNext(true);
    expect(fn () => $s->getOrCreate('c'))->toThrow(RuntimeException::class);
    $t = $s->getOrCreate('c');
    expect($s->historyForChat('c', null, true))->toBeArray();
});

it('covers CallbackHandler edges', function () {
    $approvals = H::approvals();
    $identity = H::identity();
    $identity->link('1', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    expect(fn () => $handler->handle(['approval_id' => 'x'], ['id' => '1']))
        ->toThrow(RuntimeException::class);
    $p = H::signer()->sign('missing', 'accept');
    expect($handler->handle($p, ['id' => '1'])['status'])->toBe('not_found');

    $handlerNoMgr = new CallbackHandler(H::signer(), $identity, null);
    $thrown = null;
    try {
        $handlerNoMgr->handle(H::signer()->sign('a', 'accept'), ['id' => '1']);
    } catch (RuntimeException $e) {
        $thrown = $e;
    }
    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($thrown->getMessage())->toMatch('/ApprovalGateway is required/');
});

it('covers ProcessTelegramUpdate logs tags and profile resolver', function () {
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry = new FakeCapabilityBus;
    $registry->when('support.ping', CapabilityResult::ok(['pong' => true]));
    $adapter = new TelegramAdapter(H::bot(), static fn () => [
        'text' => 'hi',
        'tool_calls' => [['name' => 'support.ping', 'input' => ['q' => 1]]],
    ]);
    $p = new ProcessTelegramUpdate(
        H::config(),
        $identity,
        new ThreadStore,
        $adapter,
        $registry,
        H::bot(),
        static fn () => ['support.ping'],
    );
    $r = $p->handle(H::telegramUpdate(userId: 42));
    expect($r['ok'])->toBeTrue();
    expect($p->completedSteps())->toContain('conversation_reply');
    expect($p->domainBypassAttempted())->toBeFalse();
    expect($p->failedJobTags())->toHaveKey('channel');
    expect($p->logs())->toBeArray();

    // registry unavailable
    $p2 = new ProcessTelegramUpdate(
        H::config(),
        $identity,
        new ThreadStore,
        new TelegramAdapter(H::bot(), static fn () => [
            'text' => 'x',
            'tool_calls' => [['name' => 'support.ping', 'input' => []]],
        ]),
        null,
        null,
        static fn () => ['support.ping'],
    );
    $r2 = $p2->handle(H::telegramUpdate(userId: 42));
    expect($r2['ok'])->toBeFalse();
});

it('covers notifier missing chat and edit without message', function () {
    $n = H::notifier();
    expect(fn () => $n->notifyPending(['id' => 'a1']))->toThrow(RuntimeException::class);
    $n->editMessage(['id' => 'a'], 'expired');
    expect($n->edits())->not->toBeEmpty();
});

it('covers webhook logs and secret header case', function () {
    $ctrl = H::webhook();
    $ctrl->handle(['x-telegram-bot-api-secret-token' => 'test-webhook-secret'], H::telegramUpdate());
    expect($ctrl->logs())->toBeArray();
    expect($ctrl->queue())->toBeInstanceOf(FakeQueue::class);
});

it('covers TelegramSetup success path', function () {
    $result = TelegramSetup::validate(H::config());
    expect($result['ok'])->toBeTrue();
    TelegramSetup::runOrFail(H::config());
    expect(true)->toBeTrue();
});

it('covers IdentityLinker allowlist empty entries', function () {
    $id = H::identity([
        'identity' => [
            'allowlist' => [
                ['telegram_user_id' => '', 'laravel_user_id' => 'x'],
                ['telegram_user_id' => 'tg', 'laravel_user_id' => ''],
            ],
        ],
    ]);
    expect($id->resolve(['telegram_user_id' => 'tg']))->toBeNull();
});
