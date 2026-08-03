<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

foreach (['code_link', 'allowlist'] as $mode) {
    it("happy: mode {$mode} allows linked identity [MSG-002]", function () use ($mode) {
        $overrides = ['identity' => ['mode' => $mode, 'allowlist' => []]];
        if ($mode === 'allowlist') {
            $overrides['identity']['allowlist'] = [
                ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'u1', 'tenant_id' => 't1'],
            ];
        }
        $id = H::identity($overrides);
        if ($mode === 'code_link') {
            $id->link('tg-1', 'u1', 't1');
        }
        expect($id->resolve(['telegram_user_id' => 'tg-1']))->not->toBeNull();
    });

    it("fail: mode {$mode} denies unlinked identity [MSG-002]", function () use ($mode) {
        $id = H::identity(['identity' => ['mode' => $mode, 'allowlist' => []]]);
        expect($id->resolve(['telegram_user_id' => 'nope']))->toBeNull();
    });

    it("fail: mode {$mode} denies forged payload [MSG-002]", function () use ($mode) {
        $id = H::identity(['identity' => ['mode' => $mode]]);
        expect($id->resolve([
            'telegram_user_id' => 'forged',
            'laravel_user_id' => 'admin',
        ]))->toBeNull();
    });
}
