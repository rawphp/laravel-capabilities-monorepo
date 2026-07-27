<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSupportMatrix;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;

it('happy: PeerSupportMatrix is non-empty for laravel/ai and laravel/mcp [D-011]', function () {
    $matrix = PeerSupportMatrix::constraints();

    expect($matrix)->toHaveKey(PeerSupportMatrix::PEER_AI)
        ->and($matrix)->toHaveKey(PeerSupportMatrix::PEER_MCP)
        ->and($matrix[PeerSupportMatrix::PEER_AI])->not->toBeEmpty()
        ->and($matrix[PeerSupportMatrix::PEER_MCP])->not->toBeEmpty();
});

it('happy: PeerSupportMatrix constraints are not bare wildcard forever [D-011]', function () {
    foreach (PeerSupportMatrix::constraints() as $peer => $constraints) {
        expect($constraints)->not->toBeEmpty("peer {$peer} must declare constraints");
        $onlyStar = count($constraints) === 1 && $constraints[0] === '*';
        expect($onlyStar)->toBeFalse("peer {$peer} must not use bare * as sole support forever");
    }
});

it('happy: PeerSupportMatrix peers() lists both official peers [D-011]', function () {
    expect(PeerSupportMatrix::peers())->toBe([
        PeerSupportMatrix::PEER_AI,
        PeerSupportMatrix::PEER_MCP,
    ]);
});

it('happy: matrix constraint match accepts in-range injected versions [D-011]', function () {
    expect(PeerSupportMatrix::versionSatisfies('0.1.0', ['^0.1']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('0.1.5', ['^0.1']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('1.2.3', ['^1.0']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('1.0.0', PeerSupportMatrix::for(PeerSupportMatrix::PEER_AI)))->toBeTrue();
});

it('fail: matrix constraint match rejects out-of-range injected versions [D-011]', function () {
    expect(PeerSupportMatrix::versionSatisfies('0.2.0', ['^0.1']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('2.0.0', ['^1.0']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('0.0.1-bad', PeerSupportMatrix::for(PeerSupportMatrix::PEER_AI)))->toBeFalse();
});

it('happy: PeerVersionProbe defaults supported versions from PeerSupportMatrix [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [
            PeerVersionProbe::PEER_AI => true,
            PeerVersionProbe::PEER_MCP => true,
        ],
        versions: [
            PeerVersionProbe::PEER_AI => '1.0.0',
            PeerVersionProbe::PEER_MCP => '0.1.2',
        ],
    );

    expect($probe->isCompatible(PeerVersionProbe::PEER_AI))->toBeTrue()
        ->and($probe->isCompatible(PeerVersionProbe::PEER_MCP))->toBeTrue()
        ->and($probe->supportedVersions())->toBe(PeerSupportMatrix::constraints());
});

it('fail: PeerVersionProbe matrix defaults mark out-of-range version incompatible [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_AI => true],
        versions: [PeerVersionProbe::PEER_AI => '9.9.9'],
    );

    expect($probe->isInstalled(PeerVersionProbe::PEER_AI))->toBeTrue()
        ->and($probe->isCompatible(PeerVersionProbe::PEER_AI))->toBeFalse()
        ->and($probe->supports(PeerVersionProbe::PEER_AI))->toBeFalse();
});

it('happy: PeerVersionProbe still accepts explicit supportedVersions overrides [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_AI => true],
        versions: [PeerVersionProbe::PEER_AI => '9.9.9'],
        supportedVersions: [PeerVersionProbe::PEER_AI => ['9.9.9']],
    );

    expect($probe->isCompatible(PeerVersionProbe::PEER_AI))->toBeTrue();
});

it('fail: incompatible injected version does not half-register tools via bootstrap [D-011]', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: [PeerVersionProbe::PEER_AI => true],
        versions: [PeerVersionProbe::PEER_AI => '9.9.9'],
    );
    $boot = new PeerSurfaceBootstrap($probe);

    expect(fn () => $boot->evaluate('agent', PeerVersionProbe::PEER_AI, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'fail',
    ]))->toThrow(PeerIncompatibleException::class);

    $status = $boot->evaluate('agent', PeerVersionProbe::PEER_AI, [
        'enabled' => true,
        'require_package' => true,
        'on_incompatible' => 'disable',
    ]);
    expect($status->status)->toBe(PeerSurfaceStatus::DISABLED_INCOMPATIBLE)
        ->and($status->registersTools)->toBeFalse()
        ->and($status->logs)->not->toBeEmpty();
});

it('happy: published config peers.support mirrors PeerSupportMatrix [D-011]', function () {
    $config = require dirname(__DIR__, 3).'/config/capabilities.php';

    expect($config)->toHaveKey('peers')
        ->and($config['peers'])->toHaveKey('support')
        ->and($config['peers']['support'])->toBe(PeerSupportMatrix::constraints());
});
