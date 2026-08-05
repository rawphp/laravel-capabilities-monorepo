<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;

it('happy: core has ConversationIngress ConversationReply ConversationIdentity ApprovalNotifier contracts [D-007]', function () {
    A::conversationContractsExist();
});

it('fail: core package source has no Telegram Bot API dependency [D-007]', function () {
    expect(A::coreHasNo('api.telegram.org'))->toBeTrue();
    A::coreHasNoMessagingRuntime();
});

it('fail: core package source has no Slack Bot API dependency [D-007]', function () {
    expect(A::coreHasNo('Slack\\WebAPI') || A::coreHasNo('api.slack.com'))->toBeTrue();
});

it('fail: core package source has no WhatsApp dependency [D-007]', function () {
    expect(A::coreHasNo('WhatsApp Cloud') || A::coreHasNo('graph.facebook.com/whatsapp'))->toBeTrue();
});

it('fail: core package source has no Messaging directory for bot runtime [D-007]', function () {
    expect(is_dir(A::CORE_SRC.'/Messaging'))->toBeFalse();
});

it('happy: messaging package depends on core contracts [D-007]', function () {
    $composer = json_decode((string) file_get_contents(A::MONOREPO_ROOT.'/packages/laravel-capabilities-messaging/composer.json'), true);
    expect($composer['require'] ?? [])->toHaveKey('rawphp/laravel-capabilities');
});

it('happy: messaging never exposes alternate run API [D-007]', function () {
    expect(class_exists('Rawphp\\CapabilitiesMessaging\\DomainMutator'))->toBeFalse();
});

it('edge: core composer suggest lists messaging optional [D-007]', function () {
    A::messagingComposerSuggestOptional();
});

it('fail: core does not require TELEGRAM_BOT_TOKEN [D-021]', function () {
    A::coreDoesNotRequireTelegramToken();
});

it('happy: core ships recording Telegram notifier stub only; production name lives in messaging [D-007]', function () {
    expect(class_exists(RecordingTelegramApprovalNotifier::class))->toBeTrue();
    // Soft-landing dual-class (UR-045 / ORI-752): deprecated alias of the recording double.
    expect(class_exists(Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier::class))->toBeTrue();
    expect(class_exists(TelegramApprovalNotifier::class))->toBeTrue();

    $coreStub = (string) file_get_contents(
        (new ReflectionClass(RecordingTelegramApprovalNotifier::class))->getFileName()
    );
    expect($coreStub)->not->toContain('api.telegram.org')
        ->and($coreStub)->not->toContain('curl_')
        ->and($coreStub)->toContain('recording');

    $deprecated = (string) file_get_contents(
        (new ReflectionClass(Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier::class))->getFileName()
    );
    expect($deprecated)->toContain('@deprecated')
        ->and($deprecated)->not->toContain('api.telegram.org')
        ->and($deprecated)->not->toContain('curl_')
        ->and($deprecated)->not->toContain('Http::')
        ->and($deprecated)->not->toContain('Guzzle');

    $instance = new Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier;
    expect($instance)->toBeInstanceOf(RecordingTelegramApprovalNotifier::class)
        ->and($instance)->toBeInstanceOf(ApprovalNotifier::class);
});

it('fail: core Approval/Notifiers sources do not reference CapabilitiesMessaging [UR-046 / ORI-753]', function () {
    $dir = A::CORE_SRC.'/Approval/Notifiers';
    expect(is_dir($dir))->toBeTrue();

    $hits = [];
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        $src = (string) file_get_contents($file);
        if (str_contains($src, 'CapabilitiesMessaging')) {
            $hits[] = basename($file);
        }
    }

    expect($hits)->toBe([], 'Approval/Notifiers must not reference CapabilitiesMessaging (files: '.implode(', ', $hits).')');
});
