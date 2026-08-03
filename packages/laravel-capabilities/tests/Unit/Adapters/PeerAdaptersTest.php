<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Schema\CatalogHealth;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it('happy: AdapterApi CURRENT is V1 [D-011]', function () {
    expect(AdapterApi::CURRENT)->toBe(AdapterApi::V1);
});

it('happy: AiToolAdapter supportsInstalledPeer false when package missing [D-011]', function () {
    $probe = PeerVersionProbe::forMissingPeers();
    $h = AdapterHelpers::harness(['probe' => $probe, 'require_peer' => false]);
    $ai = new AiToolAdapterV1($h['registry'], $probe);
    expect($ai->supportsInstalledPeer())->toBeFalse();
});

it('happy: McpToolAdapter supportsInstalledPeer false when package missing [D-011]', function () {
    $probe = PeerVersionProbe::forMissingPeers();
    $h = AdapterHelpers::harness(['probe' => $probe, 'require_peer' => false]);
    $mcp = new McpToolAdapterV1($h['registry'], $probe, $h['auth']);
    expect($mcp->supportsInstalledPeer())->toBeFalse();
});

it('happy: PeerVersionProbe feature-detects installed peer [D-011]', function () {
    $missing = PeerVersionProbe::forMissingPeers();
    expect($missing->probe(PeerVersionProbe::PEER_AI)['installed'])->toBeFalse();

    $present = PeerVersionProbe::fake([
        PeerVersionProbe::PEER_AI => true,
        PeerVersionProbe::PEER_MCP => true,
    ]);
    $result = $present->probe(PeerVersionProbe::PEER_AI);
    expect($result['installed'])->toBeTrue()
        ->and($result['compatible'])->toBeTrue()
        ->and($result['adapter_api'])->toBe(AdapterApi::CURRENT);
});

it('fail: boot fail when surface enabled require_package and peer missing [D-011]', function () {
    $probe = PeerVersionProbe::forMissingPeers();
    $boot = new PeerSurfaceBootstrap($probe);
    expect(fn () => $boot->evaluate('agent', PeerVersionProbe::PEER_AI, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'fail',
    ]))->toThrow(PeerIncompatibleException::class);
});

it('edge: boot disable surface when on_incompatible disable [D-011]', function () {
    $probe = PeerVersionProbe::forMissingPeers();
    $boot = new PeerSurfaceBootstrap($probe);
    $status = $boot->evaluate('mcp', PeerVersionProbe::PEER_MCP, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'disable',
    ]);
    expect($status->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($status->registersTools)->toBeFalse()
        ->and($status->logs)->not->toBeEmpty()
        ->and($status->logs[0]['level'])->toBe('critical');
});

it('happy: tool schema mapping unit maps capability JSON Schema to peer tool fields via mock peer [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['ai']->toolsFor('billing');
    $create = collect($tools)->firstWhere('name', 'create-invoice');
    expect($create)->not->toBeNull()
        ->and($create['input_schema'])->toBe($h['registry']->get('create-invoice')->inputSchema())
        ->and($create['source'])->toBe('registry')
        ->and($create)->toHaveKeys(['name', 'description', 'input_schema', 'peer']);
});

it('happy: invoke round-trip mock peer tool call to registry result shape [D-011]', function () {
    $h = AdapterHelpers::harness();
    $result = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('agent')
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('happy: profile filter still applies through adapter [D-011]', function () {
    $h = AdapterHelpers::harness();
    $names = array_column($h['ai']->toolsFor('support'), 'name');
    expect($names)->toContain('list-invoices')
        ->and($names)->toContain('get-customer')
        ->and($names)->not->toContain('create-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('happy: authorization deny through adapter does not mutate [D-011]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $result = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
    ]);
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: idempotency key passed through adapter when present [D-011]', function () {
    $h = AdapterHelpers::harness();
    $key = 'idem-ai-1';
    $r1 = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
        'idempotency_key' => $key,
    ]);
    $r2 = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
        'idempotency_key' => $key,
    ]);
    expect($r1->isOk())->toBeTrue()
        ->and($r2->isOk())->toBeTrue()
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: unsupported peer version does not half-register tools [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_AI => true],
        compatibleOverrides: [PeerVersionProbe::PEER_AI => false],
        versions: [PeerVersionProbe::PEER_AI => '0.0.1-bad'],
    );
    $h = AdapterHelpers::harness(['probe' => $probe]);
    $ai = new AiToolAdapterV1($h['registry'], $probe, surfaceEnabled: true);
    expect(fn () => $ai->register('billing'))->toThrow(PeerIncompatibleException::class);
    expect($ai->isRegistered())->toBeFalse()
        ->and($ai->registeredTools())->toBe([]);
});

it('fail: catch-all swallow of adapter errors leading to empty tools is refused [D-011]', function () {
    $probe = PeerVersionProbe::forMissingPeers();
    $h = AdapterHelpers::harness(['probe' => $probe]);
    $ai = new AiToolAdapterV1($h['registry'], $probe);
    // Must throw, not return []
    expect(fn () => $ai->register('billing'))->toThrow(PeerIncompatibleException::class);
});

it('happy: AdapterApi bump required when bridge call shapes change [D-011]', function () {
    $previous = ['name' => 'x', 'input_schema' => []];
    $next = ['name' => 'x', 'input_schema' => [], 'extra' => true];
    expect(AdapterApi::requiresBump($previous, $next))->toBeTrue()
        ->and(AdapterApi::requiresBump($previous, $previous))->toBeFalse();
});

it('edge: health reports disabled_incompatible when soft-disabled [D-011]', function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => ['agent' => CatalogHealth::STATUS_DISABLED_INCOMPATIBLE],
    ]);
    $report = $h['catalog']->health();
    expect($report['surfaces']['agent']['status'] ?? null)
        ->toBe(CatalogHealth::STATUS_DISABLED_INCOMPATIBLE);

    $boot = new PeerSurfaceBootstrap(PeerVersionProbe::forMissingPeers());
    $status = $boot->evaluate('agent', PeerVersionProbe::PEER_AI, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'disable',
    ]);
    expect($status->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE);
});
