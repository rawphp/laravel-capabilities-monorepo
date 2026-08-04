<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

foreach (['code_link', 'allowlist'] as $mode) {
    it("happy: mode {$mode} same tenant linked user can proceed [MSG-002]", function () use ($mode) {
        $overrides = ['identity' => ['mode' => $mode, 'allowlist' => []]];
        if ($mode === 'allowlist') {
            $overrides['identity']['allowlist'] = [
                ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'u1', 'tenant_id' => 'tenant-a'],
            ];
        }
        $id = H::identity($overrides);
        if ($mode === 'code_link') {
            $id->link('tg-1', 'u1', 'tenant-a');
        }
        $user = $id->resolve(['telegram_user_id' => 'tg-1', 'expected_tenant_id' => 'tenant-a']);
        expect($user)->not->toBeNull();
    });

    it("fail: mode {$mode} other tenant identity cannot escalate [MSG-002]", function () use ($mode) {
        $overrides = ['identity' => ['mode' => $mode, 'allowlist' => []]];
        if ($mode === 'allowlist') {
            $overrides['identity']['allowlist'] = [
                ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'u1', 'tenant_id' => 'tenant-a'],
            ];
        }
        $id = H::identity($overrides);
        if ($mode === 'code_link') {
            $id->link('tg-1', 'u1', 'tenant-a');
        }
        $user = $id->resolve(['telegram_user_id' => 'tg-1', 'expected_tenant_id' => 'tenant-other']);
        expect($user)->toBeNull();
    });
}
