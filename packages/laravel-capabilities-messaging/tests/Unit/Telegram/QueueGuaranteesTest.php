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


it("happy: valid webhook enqueues ProcessTelegramUpdate [MSG-003]", function () {
    $q = H::queue();
    H::webhook([], $q)->handle(
        ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        H::telegramUpdate(),
    );
    expect($q->pushed()[0]['job'])->toBe(ProcessTelegramUpdate::class);
});

it("fail: valid webhook does not sync-mutate domain [D-007]", function () {
    $ctrl = H::webhook();
    $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], H::telegramUpdate());
    expect($ctrl->registryInvokeCount())->toBe(0);
});

it("fail: invalid webhook does not enqueue [MSG-003]", function () {
    $q = H::queue();
    H::webhook([], $q)->handle(['X-Telegram-Bot-Api-Secret-Token' => 'bad'], H::telegramUpdate());
    expect($q->count())->toBe(0);
});

it("edge: enqueue failure is surfaced [MSG-003]", function () {
    $q = H::queue();
    $q->failNextPush(true);
    $r = H::webhook([], $q)->handle(
        ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        H::telegramUpdate(),
    );
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe(500);
});
