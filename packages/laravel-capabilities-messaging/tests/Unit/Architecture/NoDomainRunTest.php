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


it("fail: messaging source has no alternate Capability::run wrapper bypassing registry [D-007]", function () {
    $hits = H::scanSource('/Capability::run\s*\(/');
    expect($hits)->toBeEmpty();
    $hits2 = H::scanSource('/function\s+run\s*\(/');
    // ProcessTelegramUpdate etc should not define domain run()
    $bad = array_filter($hits2, fn ($p) => ! str_contains($p, 'Tests'));
    expect($bad)->toBeEmpty();
});

it("fail: messaging source does not import app Eloquent models for mutation [D-007]", function () {
    $hits = H::scanSource('/use\s+App\\\\Models\\\\/');
    expect($hits)->toBeEmpty();
    $eloquent = H::scanSource('/\\\\Eloquent\\\\Model|extends\s+Model\b/');
    expect($eloquent)->toBeEmpty();
});

it("happy: messaging depends on core contracts only for ingress reply identity notifier [D-007]", function () {
    expect(interface_exists(ConversationIngress::class))->toBeTrue();
    expect(interface_exists(ConversationReply::class))->toBeTrue();
    expect(interface_exists(ConversationIdentity::class))->toBeTrue();
    expect(interface_exists(ApprovalNotifier::class))->toBeTrue();
    $adapter = new TelegramAdapter();
    expect($adapter)->toBeInstanceOf(ConversationIngress::class)
        ->and($adapter)->toBeInstanceOf(ConversationReply::class);
    $identity = new IdentityLinker(H::config());
    expect($identity)->toBeInstanceOf(ConversationIdentity::class);
    $notifier = H::notifier();
    expect($notifier)->toBeInstanceOf(ApprovalNotifier::class);
});

it("edge: later Slack WhatsApp channels would live in messaging not core [D-007]", function () {
    expect(is_dir(H::CORE_SRC.'/Telegram'))->toBeFalse();
    expect(is_dir(H::CORE_SRC.'/Slack'))->toBeFalse();
    expect(is_dir(H::CORE_SRC.'/Messaging'))->toBeFalse();
    expect(is_dir(H::MSG_SRC.'/Telegram'))->toBeTrue();
});
