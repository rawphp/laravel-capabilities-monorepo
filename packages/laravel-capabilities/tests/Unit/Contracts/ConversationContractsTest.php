<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\ScopedQueryFactory;

/**
 * Bot / channel SDK type fragments that must never appear on core conversation contracts.
 *
 * @var list<string>
 */
$botApiFragments = [
    'Telegram',
    'Slack',
    'WhatsApp',
    'BotApi',
    'Nutgram',
    'Discord',
];

/**
 * @param  class-string  $interface
 * @param  list<string>  $forbidden
 */
function assertContractDefinedWithoutBotApi(string $interface, array $forbidden): void
{
    expect(interface_exists($interface))->toBeTrue("{$interface} must be defined in core");

    $ref = new ReflectionClass($interface);
    $file = $ref->getFileName();
    expect($file)->not->toBeFalse();

    $source = file_get_contents((string) $file);
    expect($source)->not->toBeFalse();

    foreach ($forbidden as $fragment) {
        expect($source)->not->toContain($fragment, "{$interface} must not embed Bot API type {$fragment}");
    }

    foreach ($ref->getMethods() as $method) {
        $return = $method->getReturnType();
        if ($return instanceof ReflectionNamedType) {
            foreach ($forbidden as $fragment) {
                expect($return->getName())->not->toContain($fragment);
            }
        }

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType) {
                foreach ($forbidden as $fragment) {
                    expect($type->getName())->not->toContain($fragment);
                }
            }
        }
    }
}

it('happy: contract ConversationIngress is defined in core [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationIngress::class, $botApiFragments);
    expect(method_exists(ConversationIngress::class, 'handle'))->toBeTrue();
});

it('fail: contract ConversationIngress does not embed Bot API types [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationIngress::class, $botApiFragments);
});

it('happy: contract ConversationReply is defined in core [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationReply::class, $botApiFragments);
    expect(method_exists(ConversationReply::class, 'reply'))->toBeTrue();
});

it('fail: contract ConversationReply does not embed Bot API types [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationReply::class, $botApiFragments);
});

it('happy: contract ConversationIdentity is defined in core [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationIdentity::class, $botApiFragments);
    expect(method_exists(ConversationIdentity::class, 'resolve'))->toBeTrue();
});

it('fail: contract ConversationIdentity does not embed Bot API types [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ConversationIdentity::class, $botApiFragments);
});

it('happy: contract ApprovalNotifier is defined in core [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ApprovalNotifier::class, $botApiFragments);
    expect(method_exists(ApprovalNotifier::class, 'notifyPending'))->toBeTrue();
});

it('fail: contract ApprovalNotifier does not embed Bot API types [D-007]', function () use ($botApiFragments) {
    assertContractDefinedWithoutBotApi(ApprovalNotifier::class, $botApiFragments);
});

it('happy: core contracts DefinesCapability SchemaProvider ScopeResolver ScopedQueryFactory exist [REQ-003]', function () {
    expect(interface_exists(DefinesCapability::class))->toBeTrue()
        ->and(interface_exists(SchemaProvider::class))->toBeTrue()
        ->and(interface_exists(ScopeResolver::class))->toBeTrue()
        ->and(interface_exists(ScopedQueryFactory::class))->toBeTrue();

    expect(method_exists(SchemaProvider::class, 'jsonSchema'))->toBeTrue()
        ->and(method_exists(SchemaProvider::class, 'validate'))->toBeTrue()
        ->and(method_exists(ScopeResolver::class, 'resolve'))->toBeTrue()
        ->and(method_exists(ScopedQueryFactory::class, 'for'))->toBeTrue();
});
