<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

/**
 * Integration clients are bound to server-configured MCP profiles (D-023 / D-008):
 * an integration principal may only run inside profiles listed for its client_id.
 */
function integration_binding_harness(array $integrationProfiles): array
{
    return AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
            'integration_profiles' => $integrationProfiles,
        ],
    ]);
}

it('happy: integration client runs inside its allow-listed profile [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['billing']]);
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );

    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: integration client requesting a profile outside its allowlist is forbidden [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['support']]);
    $h['mcp']->register('billing');

    $s = $h['mcp']->handleStructured(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );

    expect($s['ok'])->toBeFalse()
        ->and($s['error']['code'])->toBe('integration_profile_forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0)
        ->and($h['registry']->lastState())->toBeNull();
});

it('fail: integration client without a configured profile allowlist is forbidden [D-023]', function () {
    $h = integration_binding_harness([]);
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );

    expect($r->errorCode())->toBe('forbidden')
        ->and($r->error['normalized_code'] ?? null)->toBe('integration_profile_forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('fail: integration client with no profile in play is forbidden [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['billing']]);
    // No register() → no active profile; no options profile → unscoped invoke.

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
    );

    expect($r->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('fail: integration client with an ad-hoc selection instead of a named profile is forbidden [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['billing']]);
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => ['groups' => ['billing']]],
    );

    expect($r->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('edge: integration client allowlist accepts several profiles [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['support', 'billing']]);
    $h['mcp']->register('support');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );

    expect($r->isOk())->toBeTrue();
});

it('edge: user principals keep profile choice regardless of integration_profiles [D-023]', function () {
    $h = integration_binding_harness(['mcp-billing-service' => ['support']]);
    $h['mcp']->register('billing');

    $pat = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    $delegated = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userDelegated($h['user'], 'mcp-billing-service'),
        ['profile' => 'billing'],
    );

    expect($pat->isOk())->toBeTrue()
        ->and($delegated->isOk())->toBeTrue();
});

it('happy: resolver reports whether an integration client may use a profile [D-023]', function () {
    $resolver = new McpAuthProfileResolver([
        'integration_profiles' => ['svc' => ['billing', 'support'], 'solo' => 'ops'],
    ]);

    expect($resolver->integrationAllowsProfile('svc', 'billing'))->toBeTrue()
        ->and($resolver->integrationAllowsProfile('svc', 'admin'))->toBeFalse()
        ->and($resolver->integrationAllowsProfile('svc', null))->toBeFalse()
        ->and($resolver->integrationAllowsProfile('solo', 'ops'))->toBeTrue()
        ->and($resolver->integrationAllowsProfile('unknown', 'billing'))->toBeFalse();
});
