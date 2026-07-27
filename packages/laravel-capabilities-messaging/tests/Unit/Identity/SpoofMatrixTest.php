<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
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



it("fail: forged field telegram_user_id cannot escalate privileges [MSG-002]", function () {
    $id = H::identity();
    $user = $id->resolve([
        'telegram_user_id' => 'attacker',
        'laravel_user_id' => 'root',
        'tenant_id' => 'other',
        'chat_id' => '999',
        'from' => ['id' => 'attacker'],
    ]);
    expect($user)->toBeNull();
    expect($id->canUseTools($user))->toBeFalse();
});

it("fail: forged field telegram_user_id cannot bind identity without code flow [MSG-002]", function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['field' => 'telegram_user_id', 'telegram_user_id' => 'y']))
        ->toThrow(RuntimeException::class);
});


it("fail: forged field laravel_user_id cannot escalate privileges [MSG-002]", function () {
    $id = H::identity();
    $user = $id->resolve([
        'telegram_user_id' => 'attacker',
        'laravel_user_id' => 'root',
        'tenant_id' => 'other',
        'chat_id' => '999',
        'from' => ['id' => 'attacker'],
    ]);
    expect($user)->toBeNull();
    expect($id->canUseTools($user))->toBeFalse();
});

it("fail: forged field laravel_user_id cannot bind identity without code flow [MSG-002]", function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['field' => 'laravel_user_id', 'telegram_user_id' => 'y']))
        ->toThrow(RuntimeException::class);
});


it("fail: forged field tenant_id cannot escalate privileges [MSG-002]", function () {
    $id = H::identity();
    $user = $id->resolve([
        'telegram_user_id' => 'attacker',
        'laravel_user_id' => 'root',
        'tenant_id' => 'other',
        'chat_id' => '999',
        'from' => ['id' => 'attacker'],
    ]);
    expect($user)->toBeNull();
    expect($id->canUseTools($user))->toBeFalse();
});

it("fail: forged field tenant_id cannot bind identity without code flow [MSG-002]", function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['field' => 'tenant_id', 'telegram_user_id' => 'y']))
        ->toThrow(RuntimeException::class);
});


it("fail: forged field chat_id cannot escalate privileges [MSG-002]", function () {
    $id = H::identity();
    $user = $id->resolve([
        'telegram_user_id' => 'attacker',
        'laravel_user_id' => 'root',
        'tenant_id' => 'other',
        'chat_id' => '999',
        'from' => ['id' => 'attacker'],
    ]);
    expect($user)->toBeNull();
    expect($id->canUseTools($user))->toBeFalse();
});

it("fail: forged field chat_id cannot bind identity without code flow [MSG-002]", function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['field' => 'chat_id', 'telegram_user_id' => 'y']))
        ->toThrow(RuntimeException::class);
});


it("fail: forged field from.id cannot escalate privileges [MSG-002]", function () {
    $id = H::identity();
    $user = $id->resolve([
        'telegram_user_id' => 'attacker',
        'laravel_user_id' => 'root',
        'tenant_id' => 'other',
        'chat_id' => '999',
        'from' => ['id' => 'attacker'],
    ]);
    expect($user)->toBeNull();
    expect($id->canUseTools($user))->toBeFalse();
});

it("fail: forged field from.id cannot bind identity without code flow [MSG-002]", function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['field' => 'from.id', 'telegram_user_id' => 'y']))
        ->toThrow(RuntimeException::class);
});
