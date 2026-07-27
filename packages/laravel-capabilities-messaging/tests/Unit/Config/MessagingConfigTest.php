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


it("happy: telegram channel config reads env keys [MSG-001]", function () {
    $cfg = H::config(['telegram' => ['bot_token' => 'tok', 'webhook_secret' => 'sec']]);
    expect($cfg->botToken())->toBe('tok')->and($cfg->webhookSecret())->toBe('sec');
});

it("happy: agent profile name required in messaging config for bot [D-008]", function () {
    expect(H::config()->requireAgentProfile())->toBe('support');
});

it("edge: telegram enabled false registers no webhook routes [MSG-001]", function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => false]]);
    expect($plan['routes'])->toBeEmpty();
});

it("fail: missing agent profile name fails loudly on first bot traffic [D-008]", function () {
    $cfg = H::config(['agent_profile' => '']);
    expect(fn () => $cfg->requireAgentProfile())->toThrow(RuntimeException::class);
});

it("happy: telegram channel switch independent of core messaging surface [MSG-001]", function () {
    // Channel can be on while tests do not require core surfaces.messaging
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => true]]);
    expect($plan['telegram_enabled'])->toBeTrue()->and($plan['routes'])->toContain('telegram.webhook');
});

it("edge: webhook secret config key present [MSG-001]", function () {
    expect(array_key_exists('webhook_secret', MessagingConfig::defaults()['telegram']))->toBeTrue();
});

it("edge: bot token config key present [MSG-001]", function () {
    expect(array_key_exists('bot_token', MessagingConfig::defaults()['telegram']))->toBeTrue();
});
