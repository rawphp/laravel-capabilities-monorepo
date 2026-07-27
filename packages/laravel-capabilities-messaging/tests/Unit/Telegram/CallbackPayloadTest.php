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


it("happy: callback payload includes approval_id [D-006]", function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('approval_id');
});

it("happy: callback payload includes action [D-006]", function () {
    expect(H::signer()->sign('a1', 'reject')['action'])->toBe('reject');
});

it("happy: callback payload includes exp [D-006]", function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('exp');
});

it("happy: callback payload includes approver_hint [D-006]", function () {
    expect(H::signer()->sign('a1', 'accept', 'h'))->toHaveKey('approver_hint');
});

it("happy: callback payload includes signature [D-006]", function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('sig');
});

it("fail: callback payload must not include capability input [D-006]", function () {
    expect(fn () => H::signer()->assertSafePayload(['input' => []]))->toThrow(RuntimeException::class);
});

it("fail: callback payload must not include raw bot token [D-006]", function () {
    expect(fn () => H::signer()->assertSafePayload(['bot_token' => 'x']))->toThrow(RuntimeException::class);
});
