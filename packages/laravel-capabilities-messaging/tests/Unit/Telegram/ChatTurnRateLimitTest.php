<?php

// D-013: per-chat turns-per-minute cap on Telegram ingress (separate from in-turn tool budget).

declare(strict_types=1);

use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @param  array<string, mixed>  $configOverrides
 * @return array{0: ProcessTelegramUpdate, 1: TelegramAdapter, 2: InMemoryRateLimiter}
 */
function turnLimitedProcessor(array $configOverrides = []): array
{
    $config = H::config($configOverrides);
    $identity = H::identity($configOverrides);
    $identity->link('42', 'u1');
    $adapter = new TelegramAdapter(H::bot());
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

    return [$p, $adapter, $limiter];
}

it('happy: turns under the per-chat cap reach conversation ingress [D-013]', function () {
    [$p, $adapter, $limiter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 2]]);

    expect($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['ok'])->toBeTrue()
        ->and($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['ok'])->toBeTrue()
        ->and($adapter->handled())->toHaveCount(2)
        ->and($limiter->attemptCount('rl:telegram:chat:100'))->toBe(2);
});

it('fail: turn over the per-chat cap is rate_limited before identity or ingress [D-013]', function () {
    [$p, $adapter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    $r = $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('rate_limited')
        ->and($r['steps'])->toBe([])
        ->and($adapter->handled())->toHaveCount(1)
        ->and($adapter->replies())->toHaveCount(1);
});

it('edge: per-chat cap is keyed by chat_id so other chats keep their budget [D-013]', function () {
    [$p, $adapter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($p->handle(H::telegramUpdate(userId: 42, chatId: 100))['error'])->toBe('rate_limited')
        ->and($p->handle(H::telegramUpdate(userId: 42, chatId: 200))['ok'])->toBeTrue()
        ->and($adapter->handled())->toHaveCount(2);
});

it('edge: runPipeline reports rate_limited as the failed step [D-013]', function () {
    [$p] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 1]]);
    $p->runPipeline(H::telegramUpdate(userId: 42, chatId: 100));

    $r = $p->runPipeline(H::telegramUpdate(userId: 42, chatId: 100));

    expect($r['ok'])->toBeFalse()
        ->and($r['failed_step'])->toBe('rate_limited')
        ->and($r['tools_reached'])->toBeFalse();
});

it('edge: turns_per_minute <= 0 disables the per-chat cap [D-013]', function () {
    [$p, $adapter, $limiter] = turnLimitedProcessor(['telegram' => ['turns_per_minute' => 0]]);

    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));
    $p->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($adapter->handled())->toHaveCount(2)
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
