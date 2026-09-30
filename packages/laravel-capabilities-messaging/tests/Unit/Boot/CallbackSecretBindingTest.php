<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Boot\MessagingBindings;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Callback buttons are signed with telegram.callback_secret (falls back to the webhook secret);
 * never with a hard-coded key (D-006 / D-021).
 */
it('happy: provider signer is keyed on callback_secret, not webhook_secret [D-006]', function () {
    $app = H::container(['telegram' => ['callback_secret' => 'cb', 'webhook_secret' => 'wh']]);

    $payload = (new TelegramCallbackSigner('cb'))->sign('a1', 'accept');

    expect($app->make(TelegramCallbackSigner::class)->verify($payload))->toBeTrue()
        ->and((new TelegramCallbackSigner('wh'))->verify($payload))->toBeFalse();
});

it('edge: provider signer falls back to webhook_secret when callback_secret is unset [D-006]', function () {
    $app = H::container(['telegram' => ['webhook_secret' => 'wh']]);

    $payload = (new TelegramCallbackSigner('wh'))->sign('a1', 'reject');

    expect($app->make(TelegramCallbackSigner::class)->verify($payload))->toBeTrue();
});

it('fail: resolving the signer with no secrets throws instead of using a known key [D-021]', function () {
    $app = H::container(['telegram' => []]);

    expect(fn () => $app->make(TelegramCallbackSigner::class))
        ->toThrow(RuntimeException::class, 'callback secret is not configured');
});

it('edge: resolving the notifier does not require secrets (checked on notify) [D-021]', function () {
    $app = H::container(['telegram' => []]);

    expect($app->make(TelegramApprovalNotifier::class))->toBeInstanceOf(TelegramApprovalNotifier::class);
});

it('happy: provider notifier signs buttons with callback_secret [D-006]', function () {
    $app = H::container([
        'telegram' => ['bot_token' => 't', 'callback_secret' => 'cb', 'webhook_secret' => 'wh'],
        'bot_driver' => 'fake',
    ]);
    /** @var FakeTelegramBotClient $bot */
    $bot = $app->make(TelegramBotClient::class);

    $app->make(TelegramApprovalNotifier::class)->notifyPending(['id' => 'a1', 'chat_id' => '5']);

    $signer = new TelegramCallbackSigner('cb');
    $accept = $signer->decode($bot->calls()[0]['args']['reply_markup']['inline_keyboard'][0][0]['callback_data']);
    expect($signer->verify($accept ?? []))->toBeTrue();
});

it('edge: build() needs no secrets and never signs with a literal fallback key [D-021]', function () {
    $built = MessagingBindings::build(['telegram' => ['enabled' => true]], 'testing');

    expect($built)->not->toHaveKey('signer')
        ->and(fn () => $built['notifier']->notifyPending(['id' => 'a1', 'chat_id' => '5']))
        ->toThrow(RuntimeException::class);
});
