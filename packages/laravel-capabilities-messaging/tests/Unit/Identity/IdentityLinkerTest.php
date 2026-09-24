<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Boot\TelegramSetup;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: code link flow binds Telegram user to Laravel User [MSG-002]', function () {
    $id = H::identity();
    $code = $id->issueLinkCode('user-9', 'tenant-a');
    $user = $id->bindWithCode('tg-1', $code);
    expect($user)->not->toBeNull()
        ->and($user->id)->toBe('user-9')
        ->and($id->isLinked('tg-1'))->toBeTrue();
});

it('happy: allowlist mode allows listed identities [MSG-002]', function () {
    $id = H::identity([
        'identity' => [
            'mode' => 'allowlist',
            'allowlist' => [
                ['telegram_user_id' => 'tg-al', 'laravel_user_id' => 'user-al', 'tenant_id' => 't1'],
            ],
        ],
    ]);
    $user = $id->resolve(['telegram_user_id' => 'tg-al']);
    expect($user)->not->toBeNull()->and($user->id)->toBe('user-al');
});

it('fail: unlinked user cannot run tools [MSG-002]', function () {
    $id = H::identity();
    $user = $id->resolve(['telegram_user_id' => 'unknown']);
    expect($id->canUseTools($user))->toBeFalse();
});

it('fail: forged identity payload rejected [MSG-002]', function () {
    $id = H::identity();
    expect(fn () => $id->rejectForgedBind(['laravel_user_id' => '1', 'telegram_user_id' => '2']))
        ->toThrow(RuntimeException::class);
});

it('edge: code link expires and cannot be reused after bind [MSG-002]', function () {
    $id = H::identity(['identity' => ['code_ttl_seconds' => 10]]);
    $now = 1_700_000_000;
    $code = $id->issueLinkCode('user-1', null, $now);
    expect($id->bindWithCode('tg-x', $code, $now + 100))->toBeNull();
    $code2 = $id->issueLinkCode('user-2', null, $now);
    expect($id->bindWithCode('tg-y', $code2, $now + 1))->not->toBeNull();
    expect($id->bindWithCode('tg-z', $code2, $now + 2))->toBeNull();
});

it('fail: allowlist identity from wrong tenant cannot escalate [MSG-002]', function () {
    $id = H::identity([
        'identity' => [
            'mode' => 'allowlist',
            'allowlist' => [
                ['telegram_user_id' => 'tg-t', 'laravel_user_id' => 'u1', 'tenant_id' => 'tenant-a'],
            ],
        ],
    ]);
    $user = $id->resolve(['telegram_user_id' => 'tg-t', 'expected_tenant_id' => 'tenant-b']);
    expect($user)->toBeNull();
});

it('fail: unlinked identity never starts agent turn with tools [MSG-002]', function () {
    $identity = H::identity();
    $p = H::processor(['identity' => $identity]);
    $r = $p->handle(H::telegramUpdate(userId: 999));
    expect($r['ok'])->toBeFalse()->and($r['error'])->toContain('identity');
});

it('happy: linked identity resolves to User for ConversationIngress [MSG-002]', function () {
    $identity = H::identity();
    $identity->link('42', 'user-1', 'tenant-a');
    $user = $identity->resolve(['telegram_user_id' => '42']);
    expect($user)->toBeInstanceOf(LinkedUser::class)->and($user->id)->toBe('user-1');
});

it('fail: allowlist mode refuses code link binding [MSG-002]', function () {
    $id = H::identity(['identity' => ['mode' => 'allowlist']]);
    $code = $id->issueLinkCode('user-9', 'tenant-a');
    expect($id->bindWithCode('tg-1', $code))->toBeNull()
        ->and($id->isLinked('tg-1'))->toBeFalse()
        ->and($id->resolve(['telegram_user_id' => 'tg-1']))->toBeNull();
});

it('fail: unrecognized identity mode refuses code link binding [MSG-002]', function () {
    $id = H::identity(['identity' => ['mode' => 'allow_list']]);
    $code = $id->issueLinkCode('user-9');
    expect($id->bindWithCode('tg-1', $code))->toBeNull()
        ->and($id->isLinked('tg-1'))->toBeFalse();
});

it('fail: allowlist with a duplicated telegram_user_id fails loudly instead of last-wins [MSG-002]', function () {
    expect(fn () => H::identity([
        'identity' => [
            'mode' => 'allowlist',
            'allowlist' => [
                ['telegram_user_id' => 'tg-dup', 'laravel_user_id' => 'user-a'],
                ['telegram_user_id' => 'tg-ok', 'laravel_user_id' => 'user-b'],
                ['telegram_user_id' => 'tg-dup', 'laravel_user_id' => 'user-c'],
            ],
        ],
    ]))->toThrow(RuntimeException::class, 'tg-dup');
});

it('fail: telegram-setup rejects an allowlist with a duplicated telegram_user_id [MSG-002]', function () {
    $cfg = H::config(['identity' => ['allowlist' => [
        ['telegram_user_id' => '42', 'laravel_user_id' => 'user-a'],
        ['telegram_user_id' => 42, 'laravel_user_id' => 'user-b'],
    ]]]);
    $result = TelegramSetup::validate($cfg);
    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('42');
});

it('edge: telegram-setup rejects a duplicated allowlist even when secret checks are skipped [MSG-002]', function () {
    $cfg = H::config([
        'skip_boot_checks' => true,
        'identity' => ['allowlist' => [
            ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'user-a'],
            ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'user-a'],
        ]],
    ]);
    expect(TelegramSetup::validate($cfg)['ok'])->toBeFalse();
});

it('edge: distinct allowlist telegram_user_ids pass telegram-setup [MSG-002]', function () {
    $cfg = H::config(['identity' => ['allowlist' => [
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'user-a'],
        ['telegram_user_id' => 'tg-2', 'laravel_user_id' => 'user-a'],
    ]]]);
    expect(TelegramSetup::validate($cfg)['ok'])->toBeTrue();
});

it('edge: blank allowlist telegram_user_ids are not counted as duplicates [MSG-002]', function () {
    $cfg = H::config(['identity' => ['allowlist' => [
        ['telegram_user_id' => '', 'laravel_user_id' => 'user-x'],
        ['telegram_user_id' => '', 'laravel_user_id' => 'user-y'],
    ]]]);
    expect(fn () => $cfg->requireUniqueAllowlist())->not->toThrow(RuntimeException::class);
});
