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


it("fail: messaging must never: call_eloquent_create [D-007]", function () {
    // Production messaging must not call Eloquent create for domain mutations.
    $hits = H::scanSource('/App\\\\Models\\\\/');
    expect($hits)->toBeEmpty();
});

it("fail: messaging must never: call_eloquent_update [D-007]", function () {
    $hits = H::scanSource('/Illuminate\\\\Database\\\\Eloquent/');
    expect($hits)->toBeEmpty();
});

it("fail: messaging must never: call_eloquent_delete [D-007]", function () {
    $hits = H::scanSource('/SoftDeletes|Model::destroy/');
    expect($hits)->toBeEmpty();
});

it("fail: messaging must never: own_run_method [D-007]", function () {
    $hits = H::scanSource('/function\s+run\s*\(/');
    expect($hits)->toBeEmpty();
});

it("fail: messaging must never: bypass_registry_for_tools [D-007]", function () {
    $adapter = new TelegramAdapter();
    expect($adapter->ownsDomainRunPath())->toBeFalse();
});

it("fail: messaging must never: hard_depend_core_on_messaging [D-007]", function () {
    $composer = json_decode((string) file_get_contents(H::CORE_SRC.'/../composer.json'), true);
    $req = $composer['require'] ?? [];
    expect($req)->not->toHaveKey('rawphp/laravel-capabilities-messaging');
});

it("fail: messaging must never: embed_capability_input_in_callback [D-007]", function () {
    $signer = H::signer();
    $payload = $signer->sign('a1', 'accept');
    expect($payload)->not->toHaveKey('input')
        ->and($payload)->not->toHaveKey('input_json');
    expect(fn () => $signer->assertSafePayload(['approval_id' => 'a', 'input' => ['x' => 1]]))
        ->toThrow(RuntimeException::class);
});

it("fail: messaging must never: approve_without_linked_user [D-007]", function () {
    $identity = H::identity();
    $approvals = H::approvals();
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $payload = H::signer()->sign('a1', 'accept');
    $result = $handler->handle($payload, ['id' => '999']);
    expect($result['status'])->toBe('forbidden');
});

it("fail: messaging must never: trust_telegram_user_id_as_laravel_user [D-007]", function () {
    $identity = H::identity();
    // Forged claim that telegram id IS laravel id must not resolve without link
    $user = $identity->resolve([
        'telegram_user_id' => '42',
        'laravel_user_id' => '42',
    ]);
    expect($user)->toBeNull();
});
