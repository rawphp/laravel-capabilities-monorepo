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


it("happy: valid webhook secret accepts update and queues ProcessTelegramUpdate [MSG-003]", function () {
    $q = H::queue();
    $ctrl = H::webhook([], $q);
    $r = $ctrl->handle(
        ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'],
        H::telegramUpdate(),
    );
    expect($r['ok'])->toBeTrue()->and($r['queued'])->toBeTrue();
    expect($q->count())->toBe(1);
    expect($q->pushed()[0]['job'])->toBe(ProcessTelegramUpdate::class);
});

it("fail: invalid webhook secret rejects request [MSG-003]", function () {
    $ctrl = H::webhook();
    $r = $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'wrong'], H::telegramUpdate());
    expect($r['ok'])->toBeFalse()->and($r['status'])->toBe(401)->and($r['queued'])->toBeFalse();
});

it("fail: missing webhook secret when channel enabled fails on first request not boot [D-021]", function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['enabled' => true, 'webhook_secret' => null]]);
    expect($plan['secrets_required_at_boot'])->toBeFalse();
    $ctrl = new TelegramWebhookController(
        H::config(['telegram' => ['webhook_secret' => null, 'bot_token' => 't']]),
        H::queue(),
    );
    $r = $ctrl->handle([], H::telegramUpdate());
    expect($r['ok'])->toBeFalse();
});

it("edge: secrets not required at service provider boot for artisan migrate [D-021]", function () {
    $plan = MessagingServiceProvider::registrationPlan(['telegram' => ['bot_token' => null]]);
    expect($plan['secrets_required_at_boot'])->toBeFalse();
});

it("edge: queues ProcessTelegramUpdate async not sync domain mutation [MSG-003]", function () {
    $q = H::queue();
    $ctrl = H::webhook([], $q);
    $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], H::telegramUpdate());
    expect($q->count())->toBe(1);
    expect($ctrl->registryInvokeCount())->toBe(0);
});

it("fail: forged webhook body rejected before queue [MSG-003]", function () {
    $q = H::queue();
    $ctrl = H::webhook([], $q);
    $r = $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], []);
    expect($r['ok'])->toBeFalse()->and($q->count())->toBe(0);
});

it("fail: webhook does not invoke capability registry directly [D-007]", function () {
    $ctrl = H::webhook();
    $ctrl->handle(['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'], H::telegramUpdate());
    expect($ctrl->registryInvokeCount())->toBe(0);
});
