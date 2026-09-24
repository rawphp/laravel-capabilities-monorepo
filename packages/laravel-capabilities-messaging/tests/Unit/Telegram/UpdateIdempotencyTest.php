<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramUpdateParser;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @param  list<array{name: string, input?: array<string, mixed>}>  $toolCalls
 */
function idempotentProcessor(FakeCapabilityBus $registry, array $toolCalls): ProcessTelegramUpdate
{
    $identity = H::identity();
    $identity->link('42', 'u1');
    $adapter = new TelegramAdapter(H::bot(), static fn (array $m) => [
        'text' => 'ok',
        'tool_calls' => $toolCalls,
    ]);

    return H::processor([
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => ['support.ping', 'support.close'],
    ]);
}

it('happy: tool invoke carries idempotency key derived from chat and update_id [D-005]', function () {
    $registry = new FakeCapabilityBus;
    $p = idempotentProcessor($registry, [['name' => 'support.ping', 'input' => []]]);

    $r = $p->handle(H::telegramUpdate(chatId: 77, userId: 42, updateId: 501));

    expect($r['ok'])->toBeTrue()
        ->and($registry->invocations()[0]['options']['idempotency_key'])->toBe('telegram:77:501:0');
});

it('happy: redelivered update reuses the same idempotency key [D-005]', function () {
    $registry = new FakeCapabilityBus;
    $p = idempotentProcessor($registry, [['name' => 'support.ping', 'input' => []]]);
    $update = H::telegramUpdate(chatId: 77, userId: 42, updateId: 501);

    $p->handle($update);
    $p->handle($update);

    $keys = array_map(fn (array $i) => $i['options']['idempotency_key'], $registry->invocations());
    expect($keys)->toBe(['telegram:77:501:0', 'telegram:77:501:0']);
});

it('edge: each tool call in one update gets its own key [D-005]', function () {
    $registry = new FakeCapabilityBus;
    $p = idempotentProcessor($registry, [
        ['name' => 'support.ping', 'input' => []],
        ['name' => 'support.close', 'input' => ['id' => 1]],
    ]);

    $p->handle(H::telegramUpdate(chatId: 77, userId: 42, updateId: 501));

    $keys = array_map(fn (array $i) => $i['options']['idempotency_key'], $registry->invocations());
    expect($keys)->toBe(['telegram:77:501:0', 'telegram:77:501:1']);
});

it('edge: group chat negative id still yields a D-005-safe key', function () {
    $key = TelegramUpdateParser::idempotencyKey(H::telegramUpdate(chatId: -1001234567890, updateId: 9), 2);

    expect($key)->toBe('telegram:-1001234567890:9:2')
        ->and(preg_match('/^[A-Za-z0-9._:-]{1,128}$/', (string) $key))->toBe(1);
});

it('fail: no key is invented when update_id is missing or not an integer', function (array $update) {
    expect(TelegramUpdateParser::idempotencyKey($update, 0))->toBeNull();
})->with([
    'missing update_id' => [['message' => ['chat' => ['id' => 77], 'text' => 'hi']]],
    'non-numeric update_id' => [['update_id' => 'abc', 'message' => ['chat' => ['id' => 77]]]],
    'non-numeric chat id' => [['update_id' => 5, 'chat_id' => 'room one']],
]);

it('fail: invoke without update_id sends no idempotency key option', function () {
    $registry = new FakeCapabilityBus;
    $p = idempotentProcessor($registry, [['name' => 'support.ping', 'input' => []]]);
    $update = H::telegramUpdate(chatId: 77, userId: 42);
    unset($update['update_id']);

    $p->handle($update);

    expect($registry->invocations()[0]['options'])->not->toHaveKey('idempotency_key');
});
