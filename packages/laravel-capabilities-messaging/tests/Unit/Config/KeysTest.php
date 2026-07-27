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


it("happy: messaging config has key telegram.enabled [MSG-001]", function () {
    expect(H::config()->hasKey('telegram.enabled'))->toBeTrue();
});

it("happy: messaging config has key telegram.bot_token [MSG-001]", function () {
    expect(H::config()->hasKey('telegram.bot_token'))->toBeTrue();
});

it("happy: messaging config has key telegram.webhook_secret [MSG-001]", function () {
    expect(H::config()->hasKey('telegram.webhook_secret'))->toBeTrue();
});

it("happy: messaging config has key telegram.callback_ttl_seconds [MSG-001]", function () {
    expect(H::config()->hasKey('telegram.callback_ttl_seconds'))->toBeTrue();
});

it("happy: messaging config has key agent_profile [MSG-001]", function () {
    expect(H::config()->hasKey('agent_profile'))->toBeTrue();
});

it("happy: messaging config has key identity.mode [MSG-001]", function () {
    expect(H::config()->hasKey('identity.mode'))->toBeTrue();
});
