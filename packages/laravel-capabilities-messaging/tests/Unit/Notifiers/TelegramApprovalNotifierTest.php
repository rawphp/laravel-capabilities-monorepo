<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: notifyPending sends message with signed buttons [D-006]', function () {
    $bot = H::bot();
    $n = H::notifier(null, $bot);
    $n->notifyPending([
        'id' => 'appr-1',
        'capability_name' => 'billing.void',
        'summary' => 'void invoice',
        'messaging' => ['chat_id' => '55'],
    ]);
    expect($bot->calls())->toHaveCount(1);
    expect($bot->calls()[0]['method'])->toBe('sendMessage');
    $accept = $bot->calls()[0]['args']['reply_markup']['inline_keyboard'][0][0]['callback_data'];
    expect(H::signer()->verify(H::signer()->decode($accept) ?? []))->toBeTrue();
});

it('happy: notifier never executes capability [D-006]', function () {
    $bot = H::bot();
    H::notifier(null, $bot)->notifyPending(['id' => 'a', 'messaging' => ['chat_id' => '1']]);

    // Its only side effect is the Bot API message; it holds no bus or approval gateway to execute with.
    expect(array_column($bot->calls(), 'method'))->toBe(['sendMessage'])
        ->and(H::constructorTypes(TelegramApprovalNotifier::class))->not->toContain(CapabilityBus::class)
        ->and(H::constructorTypes(TelegramApprovalNotifier::class))->not->toContain(ApprovalGateway::class);
});

it('edge: expired approval may edit message to expired [D-006]', function () {
    $bot = H::bot();
    $n = H::notifier(null, $bot);
    $n->editMessage([
        'id' => 'a',
        'messaging' => ['chat_id' => '1', 'message_id' => 9],
    ], 'expired');
    expect($bot->calls()[0]['method'])->toBe('editMessageText')
        ->and($bot->calls()[0]['args']['text'])->toBe('expired');
});

it('fail: notify with invalid approval id does not execute capability [D-006]', function () {
    $bot = H::bot();
    H::notifier(null, $bot)->notifyPending(['id' => '', 'messaging' => ['chat_id' => '1']]);

    expect($bot->calls())->toBe([]);
});

it('happy: notifier routes accept reject only through ApprovalManager [D-006]', function () {
    $n = H::notifier();
    $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]);
    expect($n)->toBeInstanceOf(ApprovalNotifier::class);
    expect(method_exists($n, 'accept'))->toBeFalse();
});

it('fail: notifier does not call domain services [D-007]', function () {
    expect(H::constructorTypes(TelegramApprovalNotifier::class))->toBe([
        MessagingConfig::class,
        TelegramBotClient::class,
        TelegramCallbackSigner::class,
        AuditWriter::class,
    ]);
});

it('fail: notifyPending sends nothing when telegram is disabled even with secrets set [D-021]', function () {
    $bot = H::bot();
    $n = H::notifier(H::config(['telegram' => ['enabled' => false]], 'production'), $bot);
    $n->notifyPending([
        'id' => 'appr-1',
        'capability_name' => 'billing.void',
        'messaging' => ['chat_id' => '55'],
    ]);
    expect($bot->calls())->toBe([]);
});

it('fail: editMessage sends nothing when telegram is disabled [D-021]', function () {
    $bot = H::bot();
    $n = H::notifier(H::config(['telegram' => ['enabled' => false]], 'production'), $bot);
    $n->editMessage([
        'id' => 'a',
        'messaging' => ['chat_id' => '1', 'message_id' => 9],
    ], 'expired');
    expect($bot->calls())->toBe([]);
});

it('edge: an approval with no chat target (HTTP / CLI request) is skipped, not an error [M-101]', function () {
    $bot = H::bot();

    H::notifier(null, $bot)->notifyPending(['id' => 'http-1', 'capability_name' => 'billing.void', 'messaging' => null]);
    H::notifier(null, $bot)->notifyPending(['id' => 'http-2', 'capability_name' => 'billing.void']);

    expect($bot->calls())->toBe([]);
});
