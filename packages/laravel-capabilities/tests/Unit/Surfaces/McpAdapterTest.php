<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
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

function mcpAdapterSystemRegistry(): CapabilityRegistry
{
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'mcp.cap',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    return $reg;
}

function mcpAdapterResolver(): McpAuthProfileResolver
{
    return new McpAuthProfileResolver([
        'allow_integration_credentials' => true,
        'integration_actors' => ['c1' => 'integration-c1'],
    ]);
}

it('edge: disabled mcp surface registers and lists nothing and refuses handle as not_runnable', function () {
    $disabled = new McpToolAdapterV1(
        mcpAdapterSystemRegistry(),
        PeerVersionProbe::fake(['laravel/mcp' => true]),
        mcpAdapterResolver(),
        surfaceEnabled: false,
        requireCompatiblePeer: false,
    );
    $cred = McpCredential::userPat((object) ['id' => 1], 'c1');

    expect($disabled->register('ops'))->toBe([])
        ->and($disabled->listTools())->toBe([])
        ->and($disabled->isRegistered())->toBeFalse()
        ->and($disabled->activeProfile())->toBeNull()
        ->and($disabled->handle('mcp.cap', [], $cred)->errorCode())->toBe('not_runnable');
});

it('happy: mcp adapter registers tools from a groups ToolSelection', function () {
    $adapter = new McpToolAdapterV1(
        mcpAdapterSystemRegistry(),
        PeerVersionProbe::fake(['laravel/mcp' => true]),
        mcpAdapterResolver(),
        surfaceEnabled: true,
        requireCompatiblePeer: false,
    );
    $adapter->register(ToolSelection::of(['groups' => ['ops']]));

    expect($adapter->registeredTools())->toBeArray();
});

it('fail: mcp handle rejects model-supplied user_id and actor as forbidden', function () {
    $adapter = new McpToolAdapterV1(
        mcpAdapterSystemRegistry(),
        PeerVersionProbe::fake(['laravel/mcp' => true]),
        mcpAdapterResolver(),
        surfaceEnabled: true,
        requireCompatiblePeer: false,
    );
    $cred = McpCredential::userPat((object) ['id' => 1], 'c1');

    $spoof = $adapter->handle('mcp.cap', ['user_id' => 9, 'actor' => 1], $cred);

    expect($spoof->errorCode())->toBe('forbidden');
});

it('happy: mcp handle returns a CapabilityResult for scoped system actor options', function () {
    $adapter = new McpToolAdapterV1(
        mcpAdapterSystemRegistry(),
        PeerVersionProbe::fake(['laravel/mcp' => true]),
        mcpAdapterResolver(),
        surfaceEnabled: true,
        requireCompatiblePeer: false,
    );
    $cred = McpCredential::userPat((object) ['id' => 1], 'c1');

    $ok = $adapter->handle('mcp.cap', ['x' => 1], $cred, [
        'scope' => new CapabilityScope(tenantId: 't'),
        'actor' => SystemActor::named('s'),
    ]);

    expect($ok)->toBeInstanceOf(CapabilityResult::class);
});

it('fail: mcp register throws when peer is required but missing', function () {
    $strict = new McpToolAdapterV1(
        mcpAdapterSystemRegistry(),
        PeerVersionProbe::forMissingPeers(),
        mcpAdapterResolver(),
        surfaceEnabled: true,
        requireCompatiblePeer: true,
    );

    expect(fn () => $strict->register('ops'))->toThrow(PeerIncompatibleException::class);
});
