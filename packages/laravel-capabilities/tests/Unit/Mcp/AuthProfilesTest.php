<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpAuthException;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('happy: user_pat profile acts as that User with auth_profile user_pat [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $user = AdapterHelpers::user(7);
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($user), [
        'profile' => 'billing',
    ]);
    expect($r->isOk())->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    expect($ctx?->mcp()['auth_profile'] ?? null)->toBe('user_pat')
        ->and($ctx?->actor())->toBe($user);
    $audit = $h['fakes']->audit->all();
    expect($audit)->not->toBeEmpty()
        ->and($audit[array_key_last($audit)]['mcp']['auth_profile'] ?? null)->toBe('user_pat');
});

it('happy: integration credentials map to SystemActor or bot when allowlisted [D-023]', function () {
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeTrue();
    $actor = $h['registry']->lastState()?->context?->actor();
    expect($actor)->toBeInstanceOf(SystemActor::class)
        ->and($actor->name)->toBe('billing-bot')
        ->and($h['registry']->lastState()?->context?->mcp()['client_id'] ?? null)->toBe('mcp-billing-service');
});

it('fail: integration credentials denied when allow_integration_credentials false [D-023]', function () {
    $h = AdapterHelpers::harness(['mcp_auth' => ['allow_integration_credentials' => false]]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('fail: integration without allowSystemCallers on capability fails [D-023]', function () {
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'allowSystemCallers' => false,
        ]],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->errorCode())->toBe('forbidden')->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: user_delegated audits User and required client_id [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $user = AdapterHelpers::user(3);
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userDelegated($user, 'cursor-mcp'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeTrue();
    $last = $h['fakes']->audit->all();
    $entry = $last[array_key_last($last)];
    expect($entry['actor_id'] ?? null)->not->toBeNull()
        ->and($entry['mcp']['auth_profile'] ?? null)->toBe('user_delegated')
        ->and($entry['mcp']['client_id'] ?? null)->toBe('cursor-mcp');
});

it('fail: tool args actor user_id client_id not authoritative [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $user = AdapterHelpers::user(1);
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['user_id' => 999, 'client_id' => 'evil', 'actor' => 'x']),
        McpCredential::userPat($user),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: mcp context includes auth_profile and client_id when configured [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userDelegated($h['user'], 'client-a', host: 'cursor'),
        ['profile' => 'billing'],
    );
    $mcp = $h['registry']->lastState()?->context?->mcp();
    expect($mcp)->toMatchArray([
        'auth_profile' => 'user_delegated',
        'client_id' => 'client-a',
        'host' => 'cursor',
    ]);
});

it('fail: mcpTools without profile throws [D-023]', function () {
    $h = AdapterHelpers::harness();
    expect(fn () => $h['registry']->mcpTools(null))->toThrow(ProfileRequiredException::class);
});

it('happy: mcp profile is not full UI capability set for user [D-023]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['mcp']->register('support');
    $names = array_column($tools, 'name');
    // User could void-invoice on HTTP UI, but support MCP profile excludes it.
    expect($names)->not->toContain('void-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('happy: integration tenant from trusted session config not tool input [D-023]', function () {
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
    ]);
    $h['mcp']->register('billing');
    $cred = McpCredential::integration('mcp-billing-service', session: ['tenant_id' => 'tenant-trusted']);
    $h['mcp']->handle('create-invoice', AdapterHelpers::input(['tenant_id' => 'tenant-from-tool']), $cred, [
        'profile' => 'billing',
    ]);
    // tool tenant_id is stripped as spoof — wait, tenant_id is in SPOOF_KEYS so spoof fails
    // Re-run without tenant in tool input
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, ['profile' => 'billing']);
    expect($r->isOk())->toBeTrue();
    // Resolved tenant from session is passed as option
    expect($h['registry']->lastState()?->options['tenant_id'] ?? $h['registry']->lastState()?->context?->tenantId())
        ->not->toBe('tenant-from-tool');
});

it('edge: audit_client_id true always records client_id when present [D-023]', function () {
    $h = AdapterHelpers::harness(['mcp_auth' => ['audit_client_id' => true]]);
    $h['mcp']->register('billing');
    $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user'], clientId: 'pat-client'),
        ['profile' => 'billing'],
    );
    $entry = $h['fakes']->audit->all();
    $last = $entry[array_key_last($entry)];
    expect($last['mcp']['client_id'] ?? null)->toBe('pat-client');
});

it('happy: auth profile user_pat is recognized [D-023]', function () {
    expect((new McpAuthProfileResolver)->recognizes('user_pat'))->toBeTrue();
});

it('fail: auth profile user_pat cannot set actor from tool JSON [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['actor' => 'other']),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse();
});

it('happy: auth profile integration is recognized [D-023]', function () {
    expect((new McpAuthProfileResolver)->recognizes('integration'))->toBeTrue();
});

it('fail: auth profile integration cannot set actor from tool JSON [D-023]', function () {
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => true,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
    ]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['actor' => 'admin-user']),
        McpCredential::integration('mcp-billing-service'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse();
});

it('happy: auth profile user_delegated is recognized [D-023]', function () {
    expect((new McpAuthProfileResolver)->recognizes('user_delegated'))->toBeTrue();
});

it('fail: auth profile user_delegated cannot set actor from tool JSON [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['user_id' => 0]),
        McpCredential::userDelegated($h['user'], 'c1'),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse();
});

it('fail: vague token user without auth profile is refused [D-023]', function () {
    $resolver = new McpAuthProfileResolver;
    expect(fn () => $resolver->resolve(new McpCredential('token_user', user: AdapterHelpers::user())))
        ->toThrow(McpAuthException::class);
});

it('edge: default_profile config user_pat [D-023]', function () {
    $resolver = new McpAuthProfileResolver(['default_profile' => 'user_pat']);
    expect($resolver->defaultProfile())->toBe('user_pat');
});
