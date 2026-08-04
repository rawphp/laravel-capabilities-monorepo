<?php

declare(strict_types=1);

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
