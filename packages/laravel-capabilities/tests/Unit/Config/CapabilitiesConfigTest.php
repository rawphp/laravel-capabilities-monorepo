<?php

// REQ-014: Capabilities config keys and defaults (CFG-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;

it('happy: default config array has expected top-level keys [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKeys(CapabilitiesConfig::TOP_LEVEL_KEYS);
});

it('happy: config key path present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('path')
        ->and(CapabilitiesConfig::get('path'))->not->toBeNull();
});

it('happy: config key surfaces present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('surfaces')
        ->and(CapabilitiesConfig::get('surfaces'))->not->toBeNull();
});

it('happy: config key audit present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('audit')
        ->and(CapabilitiesConfig::get('audit'))->not->toBeNull();
});

it('happy: config key transactions present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('transactions')
        ->and(CapabilitiesConfig::get('transactions'))->not->toBeNull();
});

it('happy: config key events present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('events')
        ->and(CapabilitiesConfig::get('events'))->not->toBeNull();
});

it('happy: config key approval present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('approval')
        ->and(CapabilitiesConfig::get('approval'))->not->toBeNull();
});

it('happy: config key idempotency present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('idempotency')
        ->and(CapabilitiesConfig::get('idempotency'))->not->toBeNull();
});

it('happy: config key validation present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('validation')
        ->and(CapabilitiesConfig::get('validation'))->not->toBeNull();
});

it('happy: config key rate_limits present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('rate_limits')
        ->and(CapabilitiesConfig::get('rate_limits'))->not->toBeNull();
});

it('happy: config key observability present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('observability')
        ->and(CapabilitiesConfig::get('observability'))->not->toBeNull();
});

it('happy: config key clients present [CFG-001]', function () {
    expect(CapabilitiesConfig::defaults())->toHaveKey('clients')
        ->and(CapabilitiesConfig::get('clients'))->not->toBeNull();
});

it('happy: audit.mode default best_effort [D-010]', function () {
    expect(CapabilitiesConfig::get('audit.mode'))->toBe('best_effort');
});

it('happy: transactions.wrap_run default false [D-010]', function () {
    expect(CapabilitiesConfig::get('transactions.wrap_run'))->toBeFalse();
});

it('happy: validation.validate_output default true [D-014]', function () {
    expect(CapabilitiesConfig::get('validation.validate_output'))->toBeTrue();
});

it('happy: approval.execution default deferred [D-006]', function () {
    expect(CapabilitiesConfig::get('approval.execution'))->toBe('deferred');
});

it('happy: approval.ttl_hours default 24 [D-006]', function () {
    expect(CapabilitiesConfig::get('approval.ttl_hours'))->toBe(24);
});

it('happy: idempotency.ttl_hours default 24 [D-005]', function () {
    expect(CapabilitiesConfig::get('idempotency.ttl_hours'))->toBe(24);
});

it('happy: rate_limits.defaults.per_minute default 60 [D-013]', function () {
    expect(CapabilitiesConfig::get('rate_limits.defaults.per_minute'))->toBe(60);
});

it('happy: rate_limits.defaults.per_capability_per_minute default 30 [D-013]', function () {
    expect(CapabilitiesConfig::get('rate_limits.defaults.per_capability_per_minute'))->toBe(30);
});

it('happy: agent max_tools_hard default 64 [D-008]', function () {
    expect(CapabilitiesConfig::get('surfaces.agent.max_tools_hard'))->toBe(64);
});

it('happy: agent max_tools_warn default 32 [D-008]', function () {
    expect(CapabilitiesConfig::get('surfaces.agent.max_tools_warn'))->toBe(32);
});

it('happy: agent max_tool_calls_per_turn default 16 [D-013]', function () {
    expect(CapabilitiesConfig::get('surfaces.agent.max_tool_calls_per_turn'))->toBe(16);
});

it('happy: mcp auth default_profile user_pat [D-023]', function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.auth.default_profile'))->toBe('user_pat');
});

it('happy: mcp allow_integration_credentials default false [D-023]', function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.auth.allow_integration_credentials'))->toBeFalse();
});

it('happy: clients privilege_order default http cli mcp agent job [D-022]', function () {
    expect(CapabilitiesConfig::get('clients.privilege_order'))->toBe(['http', 'cli', 'mcp', 'agent', 'job']);
});

it('happy: clients reject_upgrade_attempts default false [D-022]', function () {
    expect(CapabilitiesConfig::get('clients.reject_upgrade_attempts'))->toBeFalse();
});

it('edge: token_abilities capabilities:cli maps to cli [D-022]', function () {
    expect(CapabilitiesConfig::mapTokenAbility('capabilities:cli'))->toBe('cli');
});
