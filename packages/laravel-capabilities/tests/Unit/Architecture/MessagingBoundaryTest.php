<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

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
