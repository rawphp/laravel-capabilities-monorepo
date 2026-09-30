<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Outbound requests carry Bot API fields only: the reply lands in the forum topic the user wrote
 * in, and internal keys (thread ids, signed payloads, approver hints) never reach api.telegram.org.
 */
function replyArgsFor(array $update): array
{
    $bot = H::bot();
    $identity = H::identity();
    $identity->link('42', 'u1');
    $p = H::processor(['identity' => $identity, 'adapter' => new TelegramAdapter($bot, H::echoAgent()), 'bot' => $bot]);

    expect($p->handle($update)['ok'])->toBeTrue();

    return $bot->calls()[0]['args'];
}

it('happy: a reply to a forum topic update goes into that topic [MSG-004]', function () {
    $args = replyArgsFor(H::telegramUpdate(userId: 42, chatId: -100200, topicId: 3));

    expect($args['message_thread_id'])->toBe(3)
        ->and(array_keys($args))->toEqualCanonicalizing(['chat_id', 'text', 'message_thread_id', 'message_id']);
});

it('edge: a reply outside a topic sends only chat_id and text [MSG-003]', function () {
    $args = replyArgsFor(H::telegramUpdate(userId: 42));

    expect(array_keys($args))->toEqualCanonicalizing(['chat_id', 'text', 'message_id']);
});

it('fail: the approval notifier sends no signed payloads or approver hint to the Bot API [D-006]', function () {
    $bot = H::bot();
    H::notifier(null, $bot)->notifyPending([
        'id' => 'appr-1',
        'approver_hint' => 'user-7',
        'messaging' => ['chat_id' => '55'],
    ]);
    $args = $bot->calls()[0]['args'];
    [$accept, $reject] = $args['reply_markup']['inline_keyboard'][0];

    expect(array_keys($args))->toEqualCanonicalizing(['chat_id', 'text', 'reply_markup', 'message_id'])
        ->and(json_encode($args))->not->toContain('user-7')
        ->and(H::signer()->verify(H::signer()->decode($accept['callback_data']) + ['approver_hint' => 'user-7']))->toBeTrue()
        ->and(H::signer()->decode($reject['callback_data'])['action'])->toBe('reject');
});

it('fail: the HTTP client forwards only known Bot API parameters [MSG-003]', function () {
    $sent = [];
    $client = new HttpTelegramBotClient(
        H::config(['telegram' => ['bot_token' => 'tok']]),
        static function (string $method, array $params) use (&$sent): array {
            $sent[$method] = $params;

            return ['ok' => true, 'result' => []];
        },
    );
    $markup = ['inline_keyboard' => [[['text' => 'Accept', 'callback_data' => 'a.x']]]];

    $client->sendMessage('5', 'hi', [
        'thread_id' => 'tg:5:3',
        'message_thread_id' => 3,
        'reply_markup' => $markup,
        'accept_payload' => ['approver_hint' => 'user-7'],
    ]);
    $client->editMessageText('5', 9, 'expired', ['signed_buttons' => true, 'reply_markup' => $markup]);

    expect($sent['sendMessage'])->toBe(['chat_id' => '5', 'text' => 'hi', 'message_thread_id' => 3, 'reply_markup' => $markup])
        ->and($sent['editMessageText'])->toBe(['chat_id' => '5', 'message_id' => 9, 'text' => 'expired', 'reply_markup' => $markup]);
});
