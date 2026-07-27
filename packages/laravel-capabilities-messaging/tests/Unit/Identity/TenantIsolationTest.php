<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Support\FakeQueue;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramWebhookController;
use Rawphp\CapabilitiesMessaging\Threads\ThreadStore;
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
