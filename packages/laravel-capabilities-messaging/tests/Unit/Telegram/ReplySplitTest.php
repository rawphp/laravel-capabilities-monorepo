<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Support\TelegramText;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Agent replies over Telegram's 4096-character limit arrive as several messages; an empty
 * answer gets a short fallback instead of a Bot API 400 (M-204).
 */
function splitReplyRun(array|callable $answer, ?FakeCapabilityBus $bus = null): array
{
    $bot = H::bot();
    $identity = H::identity();
    $identity->link('42', 'u1');
    $agent = is_callable($answer) ? $answer : static fn (array $m): array => $answer;
    $r = H::processor([
        'identity' => $identity,
        'bot' => $bot,
        'registry' => $bus ?? new FakeCapabilityBus,
        'adapter' => new TelegramAdapter($bot, $agent),
    ])->handle(H::telegramUpdate(userId: 42, topicId: 3));

    return [$r, array_values(array_filter($bot->calls(), fn (array $c) => $c['method'] === 'sendMessage'))];
}

it('happy: a 9000-character reply goes out as three messages in the same topic [M-204]', function () {
    [$r, $sends] = splitReplyRun(['text' => str_repeat('word ', 1800)]);

    expect($r['ok'])->toBeTrue()
        ->and($sends)->toHaveCount(3)
        ->and(array_map(fn (array $c) => TelegramText::length($c['args']['text']), $sends))->each->toBeLessThanOrEqual(4096)
        ->and(array_column(array_column($sends, 'args'), 'message_thread_id'))->toBe([3, 3, 3]);
});

it('edge: a 4097-character reply is two messages [M-204]', function () {
    [, $sends] = splitReplyRun(['text' => str_repeat('x', 4097)]);

    expect($sends)->toHaveCount(2)
        ->and($sends[1]['args']['text'])->toBe('x');
});

it('edge: an empty or missing answer is replied to with a short fallback, never an empty send [M-204]', function () {
    [$empty, $emptySends] = splitReplyRun(['text' => '  ']);
    [, $missingSends] = splitReplyRun([]);

    expect($empty['ok'])->toBeTrue()
        ->and($emptySends[0]['args']['text'])->toBe('Done.')
        ->and($missingSends[0]['args']['text'])->toBe('Done.');
});

it('edge: an empty answer after a failed tool call says it did not go through [M-204]', function () {
    $bus = new FakeCapabilityBus;
    $bus->when('support.ping', CapabilityResult::failure('forbidden', 'no'));

    [$r, $sends] = splitReplyRun(static fn (array $m): array => isset($m['tool_results'])
        ? ['text' => '']
        : ['text' => '', 'tool_calls' => [['name' => 'support.ping', 'input' => []]]], $bus);

    expect($r['error'])->toBe('forbidden')
        ->and($sends[0]['args']['text'])->toBe('That did not go through (forbidden).');
});

it('edge: the adapter sends nothing for blank text and splits long text for any caller [M-204]', function () {
    $bot = H::bot();
    $adapter = new TelegramAdapter($bot);

    $adapter->reply(['chat_id' => '1', 'text' => '']);
    $adapter->reply(['chat_id' => '1', 'text' => str_repeat('y', 5000)]);

    expect(array_map(fn (array $c) => strlen($c['args']['text']), $bot->calls()))->toBe([4096, 904]);
});
