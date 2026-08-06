<?php

// ORI-790: config-driven auto-registration of MCP servers from surfaces.mcp profiles.
// Unit-only; mock peer probe + McpToolAdapter — no live laravel/mcp.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\Mcp\McpServerRegistrar;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

/**
 * @return array<string, mixed>
 */
function mcpRegistrarConfig(array $overrides = []): array
{
    $base = [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'fail',
        'require_profile' => true,
        'auto_register' => true,
        'path_prefix' => '/mcp',
        'profiles' => [
            'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
            'support' => ['list-invoices', 'get-customer'],
        ],
        'servers' => [],
        'auth' => [
            'default_profile' => 'user_pat',
            'allow_integration_credentials' => false,
            'integration_actors' => [],
            'audit_client_id' => true,
        ],
    ];

    // Top-level keys in overrides replace entirely (so profiles => [] clears defaults).
    foreach ($overrides as $key => $value) {
        if ($key === 'auth' && is_array($value)) {
            $base['auth'] = array_merge($base['auth'], $value);
            continue;
        }
        $base[$key] = $value;
    }

    return $base;
}

it('happy: enabled + peer present registers one MCP server per config profile without host listing tools [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig(),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toHaveCount(2);

    $names = array_column($servers, 'name');
    expect($names)->toContain('billing')->toContain('support');

    $billing = collect($servers)->firstWhere('name', 'billing');
    $toolNames = array_column($billing['tools'], 'name');
    expect($toolNames)->toContain('create-invoice')
        ->and($toolNames)->toContain('void-invoice')
        ->and($toolNames)->not->toContain('delete-account')
        ->and($billing['profile'])->toBe('billing')
        ->and($billing['path'])->toBe('/mcp/billing')
        ->and($billing['source'])->toBe('config')
        ->and($billing['adapter_api'])->toBeInt();
});

it('happy: auto-register uses McpToolAdapter register path for tools [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig(['profiles' => ['billing' => ['create-invoice', 'void-invoice', 'list-invoices']]]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toHaveCount(1)
        ->and($h['mcp']->isRegistered())->toBeTrue()
        ->and($h['mcp']->activeProfile())->toBe('billing');
});

it('happy: tools/call handle path works after auto-register [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    McpServerRegistrar::register(
        mcpRegistrarConfig(['profiles' => ['billing' => ['create-invoice', 'void-invoice', 'list-invoices']]]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    $r = $h['mcp']->handle(
        'create-invoice',
        AdapterHelpers::input(),
        McpCredential::userPat($h['user']),
    );

    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('mcp')
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: require_profile enforced — empty profiles registers no full-catalog server [D-008] [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig([
            'require_profile' => true,
            'profiles' => [],
        ]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toBeEmpty()
        ->and($h['mcp']->isRegistered())->toBeFalse();
});

it('fail: require_profile true never dumps unscoped catalog as a server [D-008] [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $plan = McpServerRegistrar::plan(mcpRegistrarConfig([
        'require_profile' => true,
        'profiles' => [],
    ]), BootHelpers::probe(mcp: true));

    expect($plan)->toBeEmpty();
});

it('fail: surfaces.mcp disabled registers nothing [SURF-003] [ORI-790]', function () {
    $h = AdapterHelpers::harness(['mcp_enabled' => false]);
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig(['enabled' => false]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toBeEmpty()
        ->and($h['mcp']->isRegistered())->toBeFalse();
});

it('fail: missing peer with on_incompatible=fail throws and registers nothing [D-011] [ORI-790]', function () {
    $h = AdapterHelpers::harness(['require_peer' => true]);
    $probe = BootHelpers::probe(mcp: false);

    expect(fn () => McpServerRegistrar::register(
        mcpRegistrarConfig(['on_incompatible' => 'fail']),
        $h['mcp'],
        $probe,
    ))->toThrow(PeerIncompatibleException::class);

    expect($h['mcp']->isRegistered())->toBeFalse();
});

it('edge: missing peer with on_incompatible=disable soft-disables no half-register [D-011] [ORI-790]', function () {
    $h = AdapterHelpers::harness(['require_peer' => false]);
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig(['on_incompatible' => 'disable']),
        $h['mcp'],
        BootHelpers::probe(mcp: false),
    );

    expect($servers)->toBeEmpty()
        ->and($h['mcp']->isRegistered())->toBeFalse();
});

it('happy: plan lists server keys from profile names without invoking peer tools [ORI-790]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig(),
        BootHelpers::probe(mcp: true),
    );

    expect($plan)->toHaveCount(2);
    $names = array_column($plan, 'name');
    expect($names)->toBe(['billing', 'support']);
    foreach ($plan as $row) {
        expect($row)->toHaveKeys(['name', 'profile', 'path', 'source'])
            ->and($row['tools'] ?? null)->toBeNull();
    }
});

it('happy: path_prefix from config is applied to each server path [ORI-790]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig(['path_prefix' => 'mcp/v1']),
        BootHelpers::probe(mcp: true),
    );

    $paths = array_column($plan, 'path');
    expect($paths)->toContain('/mcp/v1/billing')->toContain('/mcp/v1/support');
});

it('happy: explicit servers config overrides profile-derived server list [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig([
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
            'servers' => [
                'ops-billing' => [
                    'profile' => 'billing',
                    'path' => '/mcp/ops-billing',
                ],
            ],
        ]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toHaveCount(1)
        ->and($servers[0]['name'])->toBe('ops-billing')
        ->and($servers[0]['profile'])->toBe('billing')
        ->and($servers[0]['path'])->toBe('/mcp/ops-billing');

    $toolNames = array_column($servers[0]['tools'], 'name');
    expect($toolNames)->toContain('create-invoice')
        ->and($toolNames)->not->toContain('delete-account');
});

it('happy: registerInto invokes sink once per server for peer facade wiring [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $seen = [];
    $keys = McpServerRegistrar::registerInto(
        mcpRegistrarConfig(['profiles' => [
            'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
            'support' => ['list-invoices', 'get-customer'],
        ]]),
        $h['mcp'],
        function (array $server) use (&$seen): void {
            $seen[] = $server['name'];
        },
        BootHelpers::probe(mcp: true),
    );

    expect($keys)->toBe(['billing', 'support'])
        ->and($seen)->toBe(['billing', 'support']);
});

it('fail: plan returns empty when peer not up (disable mode) [D-011] [ORI-790]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig(['on_incompatible' => 'disable']),
        BootHelpers::probe(mcp: false),
    );

    expect($plan)->toBeEmpty();
});

it('fail: plan throws when peer missing and on_incompatible=fail [D-011] [ORI-790]', function () {
    expect(fn () => McpServerRegistrar::plan(
        mcpRegistrarConfig(['on_incompatible' => 'fail']),
        BootHelpers::probe(mcp: false),
    ))->toThrow(PeerIncompatibleException::class);
});

it('edge: empty profiles+servers skips peer eval — plan [] no throw when peer missing + fail [ORI-801]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig([
            'auto_register' => true,
            'profiles' => [],
            'servers' => [],
            'on_incompatible' => 'fail',
        ]),
        BootHelpers::probe(mcp: false),
    );

    expect($plan)->toBeEmpty();
});

it('edge: auto_register false skips peer eval — plan [] no throw when peer missing [ORI-801]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig([
            'auto_register' => false,
            'on_incompatible' => 'fail',
        ]),
        BootHelpers::probe(mcp: false),
    );

    expect($plan)->toBeEmpty();
});

it('fail: non-empty profiles still throw when peer missing + on_incompatible=fail [ORI-801]', function () {
    expect(fn () => McpServerRegistrar::plan(
        mcpRegistrarConfig([
            'profiles' => ['billing' => ['create-invoice']],
            'on_incompatible' => 'fail',
        ]),
        BootHelpers::probe(mcp: false),
    ))->toThrow(PeerIncompatibleException::class);
});

it('edge: non-empty profiles + peer missing + disable → plan [] [ORI-801]', function () {
    $plan = McpServerRegistrar::plan(
        mcpRegistrarConfig([
            'profiles' => ['billing' => ['create-invoice']],
            'on_incompatible' => 'disable',
        ]),
        BootHelpers::probe(mcp: false),
    );

    expect($plan)->toBeEmpty();
});

it('edge: auto_register false skips server registration even when profiles present [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $servers = McpServerRegistrar::register(
        mcpRegistrarConfig(['auto_register' => false]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($servers)->toBeEmpty()
        ->and($h['mcp']->isRegistered())->toBeFalse();
});

it('happy: bootMcpServersWith registers from config via service provider entry [ORI-790]', function () {
    $h = AdapterHelpers::harness();
    $seen = [];
    $names = \Rawphp\Capabilities\CapabilitiesServiceProvider::bootMcpServersWith(
        mcpRegistrarConfig(),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
        function (array $server) use (&$seen): void {
            $seen[] = $server['name'];
        },
    );

    expect($names)->toBe(['billing', 'support'])
        ->and($seen)->toBe(['billing', 'support']);
});

it('fail: bootMcpServersWith with surface disabled registers nothing [ORI-790]', function () {
    $h = AdapterHelpers::harness(['mcp_enabled' => false]);
    $names = \Rawphp\Capabilities\CapabilitiesServiceProvider::bootMcpServersWith(
        mcpRegistrarConfig(['enabled' => false]),
        $h['mcp'],
        BootHelpers::probe(mcp: true),
    );

    expect($names)->toBeEmpty();
});

it('edge: bootMcpServersWith empty profiles + missing peer → [] no throw [ORI-801]', function () {
    $h = AdapterHelpers::harness();
    $names = \Rawphp\Capabilities\CapabilitiesServiceProvider::bootMcpServersWith(
        mcpRegistrarConfig([
            'profiles' => [],
            'servers' => [],
            'on_incompatible' => 'fail',
        ]),
        $h['mcp'],
        BootHelpers::probe(mcp: false),
    );

    expect($names)->toBeEmpty();
});

it('happy: artifactKeys includes per-server keys when profiles configured [ORI-790]', function () {
    $keys = McpServerRegistrar::artifactKeys(
        mcpRegistrarConfig(),
        BootHelpers::probe(mcp: true),
    );

    expect($keys)->toContain('mcp.servers')
        ->and($keys)->toContain('mcp.server.billing')
        ->and($keys)->toContain('mcp.server.support')
        ->and($keys)->toContain('laravel/mcp');
});

it('fail: artifactKeys empty when surface disabled [ORI-790]', function () {
    $keys = McpServerRegistrar::artifactKeys(
        mcpRegistrarConfig(['enabled' => false]),
        BootHelpers::probe(mcp: true),
    );

    expect($keys)->toBeEmpty();
});
