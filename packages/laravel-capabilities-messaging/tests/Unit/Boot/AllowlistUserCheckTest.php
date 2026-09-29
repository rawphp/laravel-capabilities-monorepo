<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Boot\TelegramSetup;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

function allowlistConfig(array $entries): MessagingConfig
{
    return H::config(['identity' => ['mode' => 'allowlist', 'allowlist' => $entries]]);
}

it('happy: setup passes when every allowlist laravel_user_id resolves [MSG-002]', function () {
    $seen = [];
    $lookup = function (string $id, ?string $tenantId) use (&$seen): ?object {
        $seen[] = [$id, $tenantId];

        return (object) ['id' => $id];
    };

    $result = TelegramSetup::validate(allowlistConfig([
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => '42', 'tenant_id' => 't1'],
        ['telegram_user_id' => 'tg-2', 'laravel_user_id' => '43'],
    ]), $lookup);

    expect($result['ok'])->toBeTrue()
        ->and($seen)->toBe([['42', 't1'], ['43', null]]);
});

it('fail: setup fails loudly on a stale allowlist laravel_user_id [MSG-002]', function () {
    $cfg = allowlistConfig([
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => '42'],
        ['telegram_user_id' => 'tg-2', 'laravel_user_id' => '999'],
    ]);
    $lookup = fn (string $id): ?object => $id === '42' ? (object) ['id' => $id] : null;

    $result = TelegramSetup::validate($cfg, $lookup);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('identity.allowlist[1]')
        ->and($result['message'])->toContain('"999"')
        ->and($result['message'])->toContain('"tg-2"')
        ->and($result['message'])->not->toContain('"42"');
    expect(fn () => TelegramSetup::runOrFail($cfg, $lookup))
        ->toThrow(RuntimeException::class, 'laravel_user_id "999"');
});

it('edge: setup reports every stale allowlist entry in one message [MSG-002]', function () {
    $result = TelegramSetup::validate(allowlistConfig([
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'gone-1'],
        ['telegram_user_id' => 'tg-2', 'laravel_user_id' => 'gone-2'],
    ]), fn (): ?object => null);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('identity.allowlist[0]')
        ->and($result['message'])->toContain('identity.allowlist[1]');
});

it('fail: setup rejects allowlist entries with a missing id even without a lookup [MSG-002]', function () {
    $result = TelegramSetup::validate(allowlistConfig([
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => ''],
        ['laravel_user_id' => '42'],
    ]));

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('identity.allowlist[0]')
        ->and($result['message'])->toContain('identity.allowlist[1]');
});

it('edge: setup without a user lookup does not resolve allowlist users [MSG-002]', function () {
    $result = TelegramSetup::validate(allowlistConfig([
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => '999'],
    ]));

    expect($result['ok'])->toBeTrue();
});

it('edge: secret failures are reported before allowlist users are resolved [D-021]', function () {
    $cfg = H::config([
        'telegram' => ['bot_token' => null],
        'identity' => ['allowlist' => [['telegram_user_id' => 'tg-1', 'laravel_user_id' => '1']]],
    ]);
    $called = false;

    $result = TelegramSetup::validate($cfg, function () use (&$called): ?object {
        $called = true;

        return null;
    });

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('TELEGRAM_BOT_TOKEN')
        ->and($called)->toBeFalse();
});
