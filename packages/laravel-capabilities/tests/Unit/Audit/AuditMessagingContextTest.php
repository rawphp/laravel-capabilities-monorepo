<?php

// Audit carries messaging ingress metadata alongside mcp (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: audit entry records messaging context for messaging invokes [D-010]', function () {
    $messaging = ['channel' => 'telegram', 'chat_id' => '4242', 'user_link_id' => 7];
    $state = new InvokeState(new CapabilityDefinition(name: 'msg-cap', description: 'd', readOnly: true), [], 'agent');
    $state->context = CapabilityContext::make(['caller' => 'agent', 'actor' => H::user(), 'messaging' => $messaging]);

    expect(AuditLogger::entry($state, true)['messaging'])->toBe($messaging);
});

it('edge: audit entry messaging is null without messaging context [D-010]', function () {
    $state = new InvokeState(new CapabilityDefinition(name: 'http-cap', description: 'd', readOnly: true), [], 'http');

    expect(AuditLogger::entry($state, true))->toHaveKey('messaging')
        ->and(AuditLogger::entry($state, true)['messaging'])->toBeNull();

    $state->context = CapabilityContext::make(['caller' => 'http', 'actor' => H::user()]);

    expect(AuditLogger::entry($state, false)['messaging'])->toBeNull();
});

it('happy: registry invoke writes messaging context into the audit entry [D-010]', function () {
    $messaging = ['channel' => 'telegram', 'chat_id' => '99', 'user_link_id' => 3];
    $h = AuditHelpers::harness(['name' => 'audit-messaging-wired', 'surfaces' => ['messaging' => true]]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent', ['messaging' => $messaging]));

    expect($r->isOk())->toBeTrue()
        ->and($h['audit']->all()[0]['messaging'] ?? null)->toBe($messaging);
});
