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


it("happy: registers messaging config [MSG-001]", function () {
    $plan = MessagingServiceProvider::registrationPlan();
    expect($plan['config_merged'])->toBeTrue()
        ->and($plan['config_keys'])->toContain('telegram')
        ->and($plan['config_keys'])->toContain('agent_profile');
});

it("edge: registers webhook routes when telegram enabled [MSG-001]", function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => true]]);
    expect($plan['routes'])->toContain('telegram.webhook');
});

it("fail: registers no webhook routes when telegram disabled [MSG-001]", function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => false]]);
    expect($plan['routes'])->toBeEmpty();
});

it("happy: binds ApprovalNotifier implementation [D-006]", function () {
    $plan = MessagingServiceProvider::registrationPlan();
    expect($plan['bindings'])->toContain(TelegramApprovalNotifier::class);
    expect(H::notifier())->toBeInstanceOf(ApprovalNotifier::class);
});
