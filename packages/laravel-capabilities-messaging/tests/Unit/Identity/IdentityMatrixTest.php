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
