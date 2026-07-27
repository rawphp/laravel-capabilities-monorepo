<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

/**
 * D-011 contract table — each cell asserted with mock peers only.
 */

function contract_harness(string $peer, bool $compatible = true): array
{
    $peers = [
        PeerVersionProbe::PEER_AI => $peer === PeerVersionProbe::PEER_AI ? $compatible : true,
        PeerVersionProbe::PEER_MCP => $peer === PeerVersionProbe::PEER_MCP ? $compatible : true,
    ];
    if (! $compatible) {
        $probe = new PeerVersionProbe(
            installedOverrides: [
                PeerVersionProbe::PEER_AI => true,
                PeerVersionProbe::PEER_MCP => true,
            ],
            compatibleOverrides: [
                PeerVersionProbe::PEER_AI => $peer !== PeerVersionProbe::PEER_AI,
                PeerVersionProbe::PEER_MCP => $peer !== PeerVersionProbe::PEER_MCP,
            ],
        );
    } else {
        $probe = PeerVersionProbe::fake($peers);
    }

    return AdapterHelpers::harness(['probe' => $probe]);
}

it('happy: contract tool_schema_mapping for peer laravel/ai [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_AI);
    $tool = collect($h['ai']->toolsFor('billing'))->firstWhere('name', 'create-invoice');
    expect($tool['input_schema'])->toBe($h['registry']->get('create-invoice')->inputSchema())
        ->and($tool['peer'])->toBe('laravel/ai');
});

it('happy: contract tool_schema_mapping for peer laravel/mcp [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_MCP);
    $tools = $h['mcp']->register('billing');
    $tool = collect($tools)->firstWhere('name', 'create-invoice');
    expect($tool['input_schema'])->toBe($h['registry']->get('create-invoice')->inputSchema())
        ->and($tool['peer'])->toBe('laravel/mcp');
});

it('happy: contract invoke_round_trip for peer laravel/ai [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_AI);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($r->isOk())->toBeTrue()->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: contract invoke_round_trip for peer laravel/mcp [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_MCP);
    $h['mcp']->register('billing');
    $cred = McpCredential::userPat($h['user']);
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, ['profile' => 'billing']);
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('mcp')
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: contract profile_filter for peer laravel/ai [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_AI);
    $names = array_column($h['ai']->toolsFor('support'), 'name');
    expect($names)->not->toContain('void-invoice');
});

it('happy: contract profile_filter for peer laravel/mcp [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_MCP);
    $names = array_column($h['mcp']->register('support'), 'name');
    expect($names)->not->toContain('void-invoice');
});

it('happy: contract authorization_deny for peer laravel/ai [D-011]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($r->errorCode())->toBe('forbidden')->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: contract authorization_deny for peer laravel/mcp [D-011]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $h['mcp']->register('billing');
    $r = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), McpCredential::userPat($h['user']), [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('forbidden')->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: contract idempotency_passthrough for peer laravel/ai [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_AI);
    $opts = ['profile' => 'billing', 'idempotency_key' => 'k-ai'];
    $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], $opts);
    $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], $opts);
    expect($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: contract idempotency_passthrough for peer laravel/mcp [D-011]', function () {
    $h = contract_harness(PeerVersionProbe::PEER_MCP);
    $h['mcp']->register('billing');
    $cred = McpCredential::userPat($h['user']);
    $opts = ['profile' => 'billing', 'idempotency_key' => 'k-mcp'];
    $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, $opts);
    $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, $opts);
    expect($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: contract missing_peer_boot for peer laravel/ai [D-011]', function () {
    $boot = new PeerSurfaceBootstrap(PeerVersionProbe::forMissingPeers());
    expect(fn () => $boot->evaluate('agent', PeerVersionProbe::PEER_AI, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'fail',
    ]))->toThrow(PeerIncompatibleException::class);
});

it('happy: contract missing_peer_boot for peer laravel/mcp [D-011]', function () {
    $boot = new PeerSurfaceBootstrap(PeerVersionProbe::forMissingPeers());
    $status = $boot->evaluate('mcp', PeerVersionProbe::PEER_MCP, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'disable',
    ]);
    expect($status->registersTools)->toBeFalse()
        ->and($status->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE);
});

it('happy: contract unsupported_peer_version for peer laravel/ai [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_AI => true],
        compatibleOverrides: [PeerVersionProbe::PEER_AI => false],
    );
    $h = AdapterHelpers::harness(['probe' => $probe]);
    expect($h['ai']->supportsInstalledPeer())->toBeFalse();
    expect(fn () => $h['ai']->register('billing'))->toThrow(PeerIncompatibleException::class);
    expect($h['ai']->registeredTools())->toBeEmpty();
});

it('happy: contract unsupported_peer_version for peer laravel/mcp [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_MCP => true],
        compatibleOverrides: [PeerVersionProbe::PEER_MCP => false],
    );
    $h = AdapterHelpers::harness(['probe' => $probe]);
    expect($h['mcp']->supportsInstalledPeer())->toBeFalse();
    expect(fn () => $h['mcp']->register('billing'))->toThrow(PeerIncompatibleException::class);
    expect($h['mcp']->registeredTools())->toBeEmpty();
});
