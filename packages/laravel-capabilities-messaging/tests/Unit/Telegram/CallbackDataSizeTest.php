<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Telegram rejects inline buttons whose callback_data exceeds 64 bytes (BUTTON_DATA_INVALID).
 * The encoded token must fit, even for long ids and a bound approver hint (D-006).
 */
it('happy: encoded callback_data fits 64 bytes for a UUID approval id and a long hint [D-006]', function (string $action) {
    $s = H::signer();
    $payload = $s->sign('0b7e7d1a-3c3f-4f43-9d55-2d7c4f0e9a11', $action, str_repeat('Z', 36));

    expect(strlen($s->encode($payload)))->toBeLessThanOrEqual(TelegramCallbackSigner::MAX_CALLBACK_DATA_BYTES)
        ->and(TelegramCallbackSigner::MAX_CALLBACK_DATA_BYTES)->toBe(64);
})->with(['accept', 'reject']);

it('happy: an unbound token round-trips through encode/decode and verifies [D-006]', function () {
    $s = H::signer();
    $token = $s->encode($s->sign('01HZX3K8M2N4P6Q8R0S2T4V6W8', 'reject', null, 1000));

    $decoded = $s->decode($token);

    expect($decoded)->toMatchArray(['approval_id' => '01HZX3K8M2N4P6Q8R0S2T4V6W8', 'action' => 'reject', 'exp' => 1900])
        ->and($s->verify($decoded, 1000))->toBeTrue();
});

it('happy: a bound token verifies only once the hint is supplied, not from the button [D-006]', function () {
    $s = H::signer();
    $decoded = $s->decode($s->encode($s->sign('ap-1', 'accept', 'u1', 1000)));

    expect($decoded)->not->toHaveKey('approver_hint')
        ->and($s->verify($decoded, 1000))->toBeFalse()
        ->and($s->verify($decoded + ['approver_hint' => 'u2'], 1000))->toBeFalse()
        ->and($s->verify($decoded + ['approver_hint' => 'u1'], 1000))->toBeTrue();
});

it('fail: approval id too long for callback_data throws instead of sending a button Telegram rejects [D-006]', function () {
    $s = H::signer();

    expect(fn () => $s->encode($s->sign(str_repeat('x', 60), 'accept')))
        ->toThrow(RuntimeException::class, '64');
});

it('fail: malformed or tampered tokens decode to null or fail verify [D-006]', function () {
    $s = H::signer();
    $token = $s->encode($s->sign('ap-1', 'accept', null, 1000));

    expect($s->decode('!!!'))->toBeNull()
        ->and($s->decode(''))->toBeNull()
        ->and($s->decode('x.ap-1.abc.'.str_repeat('A', 16)))->toBeNull()
        ->and($s->decode('a.ap-1.@@.'.str_repeat('A', 16)))->toBeNull()
        ->and($s->decode('a.ap-1.abc.short'))->toBeNull()
        ->and($s->verify($s->decode(str_replace('ap-1', 'ap-2', $token)), 1000))->toBeFalse();
});

it('happy: CallbackHandler accepts a decoded bound token for the hinted approver [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-tok', 'capability_name' => 'x', 'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1', 'original_caller' => 'http', 'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $identity->link('99', 'u2');
    $s = H::signer();
    $decoded = $s->decode($s->encode($s->sign('ap-tok', 'accept', 'u1')));
    $handler = new CallbackHandler($s, $identity, $approvals);

    expect($handler->handle($decoded, ['id' => '99']))->toBe(['status' => 'invalid', 'message' => 'invalid_signature_or_expired'])
        ->and($approvals->find('ap-tok')['status'])->toBe('pending')
        ->and($handler->handle($decoded, ['id' => '42'])['status'])->toBe('ok');
});

it('happy: CallbackHandler accepts a decoded unbound token from any linked approver [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-unb', 'capability_name' => 'x', 'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1', 'original_caller' => 'http', 'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $s = H::signer();

    $r = (new CallbackHandler($s, $identity, $approvals))->handle($s->decode($s->encode($s->sign('ap-unb', 'reject'))), ['id' => '42']);

    expect($r['status'])->toBe('ok');
});

it('fail: CallbackHandler refuses a decoded token forged for an unlinked user before any state change [D-006]', function () {
    $approvals = H::approvals();
    $s = H::signer();
    $decoded = $s->decode($s->encode($s->sign('ap-x', 'accept', 'u1')));

    $r = (new CallbackHandler($s, H::identity(), $approvals))->handle($decoded, ['id' => 'nobody']);

    expect($r['status'])->toBe('forbidden');
});

it('happy: notifier sends callback_data within the Bot API limit [D-006]', function () {
    $bot = H::bot();
    H::notifier(null, $bot)->notifyPending(['id' => '01HZX3K8M2N4P6Q8R0S2T4V6W8', 'chat_id' => '5', 'approver_hint' => str_repeat('u', 36)]);

    $buttons = $bot->calls()[0]['args']['reply_markup']['inline_keyboard'][0];
    foreach ($buttons as $button) {
        expect(strlen($button['callback_data']))->toBeLessThanOrEqual(64);
    }
});

it('fail: FakeTelegramBotClient rejects callback_data over 64 bytes like the Bot API [D-006]', function () {
    $bot = new FakeTelegramBotClient;

    expect(fn () => $bot->sendMessage('5', 'hi', [
        'reply_markup' => ['inline_keyboard' => [[['text' => 'A', 'callback_data' => str_repeat('x', 65)]]]],
    ]))->toThrow(TelegramBotApiException::class, 'BUTTON_DATA_INVALID');

    expect($bot->calls())->toBe([]);
});
