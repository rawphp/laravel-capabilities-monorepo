<?php

// D-013: per-chat turns-per-minute cap on Telegram ingress (separate from in-turn tool budget).

declare(strict_types=1);

use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @param  array<string, mixed>  $configOverrides
 * @return array{0: ProcessTelegramUpdate, 1: ArrayObject<int, array<string, mixed>>, 2: InMemoryRateLimiter, 3: FakeTelegramBotClient}
 */
function turnLimitedProcessor(array $configOverrides = []): array
{
    $config = H::config($configOverrides);
    $identity = H::identity($configOverrides);
    $identity->link('42', 'u1');
    $turns = new ArrayObject;
    $bot = H::bot();
    $adapter = new TelegramAdapter($bot, static function (array $m) use ($turns): array {
        $turns[] = $m;

        return ['text' => 'ok', 'tool_calls' => []];
    });
    $limiter = new InMemoryRateLimiter;

    $p = new ProcessTelegramUpdate(
        config: $config,
        identity: $identity,
        threads: H::threads(),
        adapter: $adapter,
        registry: null,
        bot: null,
        turnLimiter: $limiter,
    );

    return [$p, $turns, $limiter, $bot];
}

it('happy: turns under the per-chat cap reach conversation ingress [D-013]', function () {
    [$p, $turns, $limiter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 2]]);

    expect($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['ok'])->toBeTrue()
        ->and($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['ok'])->toBeTrue()
        ->and($turns)->toHaveCount(2)
        ->and($limiter->attemptCount('rl:telegram:chat:100'))->toBe(2);
});

it('fail: turn over the per-chat cap is rate_limited before identity or ingress [D-013]', function () {
    [$p, $turns, , $bot] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    $r = $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('rate_limited')
        ->and($r['steps'])->toBe([])
        ->and($turns)->toHaveCount(1)
        ->and($bot->calls())->toHaveCount(1);
});

it('edge: per-chat cap is keyed by chat_id so other chats keep their budget [D-013]', function () {
    [$p, $turns] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['error'])->toBe('rate_limited')
        ->and($p->handle(H::telegramUpdate(userId: 42, chatId: 200))['ok'])->toBeTrue()
        ->and($turns)->toHaveCount(2);
});

it('edge: a rate-limited turn stops before identity, thread or tools [D-013]', function () {
    [$p, $turns] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    $r = $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($r['error'])->toBe('rate_limited')
        ->and($p->completedSteps())->not->toContain('tool_calls_registry')
        ->and($turns)->toHaveCount(1);
});

it('edge: turns_per_minute <= 0 disables the per-chat cap [D-013]', function () {
    [$p, $turns, $limiter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 0]]);

    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($turns)->toHaveCount(2)
        ->and($limiter->keys())->toBe([]);
});

it('happy: messaging config defaults telegram.turns_per_minute to 20 [D-013]', function () {
    expect(H::config()->hasKey('telegram.turns_per_minute'))->toBeTrue()
        ->and(H::config()->turnsPerMinute())->toBe(20);
});

it('happy: provider injects core RateLimiter contract into ProcessTelegramUpdate when bound [D-013]', function () {
    $src = (string) file_get_contents(H::MSG_SRC.'/MessagingServiceProvider.php');

    expect($src)->toContain('use Rawphp\Capabilities\Contracts\RateLimiter;')
        ->and($src)->toContain('turnLimiter: $app->bound(RateLimiter::class)');
});
