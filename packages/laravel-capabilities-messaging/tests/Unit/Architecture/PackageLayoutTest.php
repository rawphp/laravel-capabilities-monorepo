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


it("happy: messaging layout includes Telegram [LAYOUT]", function () {
    expect(is_dir(H::MSG_SRC.'/Telegram'))->toBeTrue();
});

it("happy: messaging layout includes Identity [LAYOUT]", function () {
    expect(is_dir(H::MSG_SRC.'/Identity'))->toBeTrue();
});

it("happy: messaging layout includes Threads [LAYOUT]", function () {
    expect(is_dir(H::MSG_SRC.'/Threads'))->toBeTrue();
});

it("happy: messaging layout includes Notifiers [LAYOUT]", function () {
    expect(is_dir(H::MSG_SRC.'/Notifiers'))->toBeTrue();
});

it("fail: messaging layout reimplements registry pipeline [D-007]", function () {
    $hits = H::scanSource('/class\s+CapabilityRegistry\b/');
    expect($hits)->toBeEmpty();
    $pipe = H::scanSource('/PipelineStages|stageJsonSchemaValidate/');
    expect($pipe)->toBeEmpty();
});
