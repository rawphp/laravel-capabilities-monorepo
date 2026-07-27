<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
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


it("happy: webhook secret valid accepted [MSG-003]", function () {
    $r = H::webhook()->handle(
        ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        H::telegramUpdate(),
    );
    expect($r['ok'])->toBeTrue();
});

it("fail: webhook secret invalid rejected [MSG-003]", function () {
    $r = H::webhook()->handle(['X-Telegram-Bot-Api-Secret-Token' => 'nope'], H::telegramUpdate());
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe(401);
});

it("fail: webhook secret missing rejected [MSG-003]", function () {
    $r = H::webhook()->handle([], H::telegramUpdate());
    expect($r['ok'])->toBeFalse();
});

it("fail: webhook secret empty rejected [MSG-003]", function () {
    $r = H::webhook()->handle(['X-Telegram-Bot-Api-Secret-Token' => ''], H::telegramUpdate());
    expect($r['ok'])->toBeFalse();
});
