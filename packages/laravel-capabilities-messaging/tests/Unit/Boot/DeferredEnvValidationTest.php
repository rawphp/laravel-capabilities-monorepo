<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Boot\MessagingRegistration;
use Rawphp\CapabilitiesMessaging\Boot\TelegramSetup;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;


it("happy: package boot with messaging enabled does not require TELEGRAM_BOT_TOKEN [D-021]", function () {
    $plan = MessagingServiceProvider::registrationPlan([
        'telegram' => ['enabled' => true, 'bot_token' => null, 'webhook_secret' => null],
    ]);
    expect($plan['secrets_required_at_boot'])->toBeFalse();
});

it("fail: first webhook without token fails loudly [D-021]", function () {
    $ctrl = new TelegramWebhookController(H::config([
        'telegram' => ['bot_token' => null, 'webhook_secret' => 'sec'],
    ]), H::queue());
    $r = $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'sec'], H::telegramUpdate());
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe(503);
});

it("fail: messaging:telegram-setup without secrets fails loudly [D-021]", function () {
    $cfg = H::config(['telegram' => ['bot_token' => null, 'webhook_secret' => null]]);
    $result = TelegramSetup::validate($cfg);
    expect($result['ok'])->toBeFalse();
    expect(fn () => TelegramSetup::runOrFail($cfg))->toThrow(RuntimeException::class);
});

it("fail: first outbound notify without secrets fails loudly [D-021]", function () {
    $n = H::notifier(H::config(['telegram' => ['bot_token' => null, 'webhook_secret' => null]]));
    expect(fn () => $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]))
        ->toThrow(RuntimeException::class);
});

it("edge: CAPABILITIES_SKIP_BOOT_CHECKS does not apply to production [D-021]", function () {
    $cfg = H::config(['skip_boot_checks' => true], 'production');
    expect($cfg->maySkipSecretChecks())->toBeFalse();
});

it("happy: artisan migrate path does not require TELEGRAM secrets at boot [D-021]", function () {
    $plan = MessagingServiceProvider::registrationPlan([
        'telegram' => ['enabled' => true, 'bot_token' => null],
    ]);
    expect($plan['secrets_required_at_boot'])->toBeFalse();
});

it("fail: production ignores CAPABILITIES_SKIP_BOOT_CHECKS for messaging secrets path [D-021]", function () {
    $cfg = H::config([
        'skip_boot_checks' => true,
        'telegram' => ['bot_token' => null, 'webhook_secret' => null],
    ], 'production');
    expect(fn () => $cfg->requireTelegramSecrets())->toThrow(RuntimeException::class);
});

it("edge: health includes messaging readiness when surface on [D-021]", function () {
    $health = H::config()->health();
    expect($health)->toHaveKeys(['ready', 'telegram_enabled', 'secrets_configured', 'agent_profile']);
    expect($health['ready'])->toBeTrue();
});
