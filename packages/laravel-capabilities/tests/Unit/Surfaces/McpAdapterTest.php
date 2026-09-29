<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('happy: McpToolAdapterV1 registers tools from profile [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['mcp']->register('billing');
    $names = array_column($tools, 'name');
    expect($h['mcp']->isRegistered())->toBeTrue()
        ->and($names)->toContain('create-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('happy: tools call invokes registry with caller mcp and auth profile [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('mcp')
        ->and($h['registry']->lastState()?->context?->mcp()['auth_profile'] ?? null)->toBe('user_pat');
});

it('fail: tools call does not accept actor from tool JSON [D-023]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(['actor' => 'evil', 'user_id' => 999]),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('edge: tool input_schema equals catalog input_schema no second schema [D-004]', function () {
    $h = AdapterHelpers::harness();
    $tool = collect($h['mcp']->register('billing'))->firstWhere('name', 'create-invoice');
    expect($tool['input_schema'])->toBe($h['registry']->get('create-invoice')->inputSchema())
        ->and($tool['source'])->toBe('registry');
});

it('fail: mcp surface disabled registers no tools [SURF-003]', function () {
    $h = AdapterHelpers::harness(['mcp_enabled' => false]);
    expect($h['mcp']->register('billing'))->toBe([])
        ->and($h['mcp']->isRegistered())->toBeFalse();
});

it('happy: mcp progressive disclosure listing still constrained by profile [P2-007]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('support');
    $listed = array_column($h['mcp']->listTools(), 'name');
    expect($listed)->toContain('list-invoices')
        ->and($listed)->not->toContain('void-invoice');

    $meta = $h['registry']->mcpMetaTools('support');
    $allow = $meta[0]['allowlist'] ?? [];
    expect($allow)->not->toContain('void-invoice');
});

it('happy: idempotency_key tool arg passed through [D-005]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $cred = McpCredential::userPat($h['user']);
    $input = AdapterHelpers::input(['idempotency_key' => 'mcp-key-1']);
    $h['mcp']->handle('create-invoice', $input, $cred, ['profile' => 'billing']);
    $h['mcp']->handle('create-invoice', $input, $cred, ['profile' => 'billing']);
    expect($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: authorization deny through mcp does not mutate [D-011]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );
    expect($r->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('fail: handle without profile after multi-profile register refuses instead of last-profile fallback [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $h['mcp']->register('support');

    $r = $h['mcp']->handle(
        'list-invoices',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
    );

    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('not_runnable')
        ->and($r->error['normalized_code'] ?? null)->toBe('profile_required')
        ->and($r->error['registered_profiles'] ?? null)->toBe(['billing', 'support'])
        ->and($h['runs']['list-invoices']->value)->toBe(0)
        ->and($h['mcp']->handleStructured('list-invoices', AdapterHelpers::input(), McpCredential::userPat($h['user']))['error']['code'])
        ->toBe('profile_required');
});

it('happy: explicit profile still runs after multi-profile register [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $h['mcp']->register('support');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
        ['profile' => 'billing'],
    );

    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('edge: re-registering the same profile keeps the implicit single-profile default [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['mcp']->register('billing');
    $h['mcp']->register('billing');

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
    );

    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});
