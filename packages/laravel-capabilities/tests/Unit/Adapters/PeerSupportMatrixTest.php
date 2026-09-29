<?php

declare(strict_types=1);

use Composer\InstalledVersions;
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

it('edge: caret on 0.0.x pins the patch; tilde pins the minor [D-011]', function () {
    expect(PeerSupportMatrix::versionSatisfies('0.0.3', ['^0.0.3']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('0.0.4', ['^0.0.3']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('1.2.9', ['~1.2']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('1.3.0', ['~1.2']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('1.1.9', ['~1.2']))->toBeFalse();
});

it('edge: host constraints may be exact versions or a wildcard; v-prefixes and pre-release tags normalise [D-011]', function () {
    expect(PeerSupportMatrix::versionSatisfies('v1.4.0', ['1.4']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('1.4.1', ['1.4.0']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('2.0.0-beta.1', ['^2.0']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('anything', ['*']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('anything', ['']))->toBeTrue()
        ->and(PeerSupportMatrix::versionSatisfies('dev-main', ['^1.0']))->toBeFalse()
        ->and(PeerSupportMatrix::versionSatisfies('1.0.0', []))->toBeFalse();
});

it('fail: an unknown peer has no declared constraints [D-011]', function () {
    expect(PeerSupportMatrix::for('acme/peer'))->toBe([]);
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

it('happy: PeerVersionProbe::fromComposer reads installed peer versions into the matrix gate [D-011]', function () {
    $lookedUp = [];
    $probe = PeerVersionProbe::fromComposer(
        versionLookup: function (string $package) use (&$lookedUp): ?string {
            $lookedUp[] = $package;

            return $package === PeerVersionProbe::PEER_AI ? 'v1.2.0' : null;
        },
        classExists: static fn (string $class): bool => true,
    );

    expect($lookedUp)->toBe([PeerVersionProbe::PEER_AI, PeerVersionProbe::PEER_MCP])
        ->and($probe->installedVersion(PeerVersionProbe::PEER_AI))->toBe('v1.2.0')
        ->and($probe->installedVersion(PeerVersionProbe::PEER_MCP))->toBeNull()
        ->and($probe->supports(PeerVersionProbe::PEER_AI))->toBeTrue()
        ->and($probe->supportedVersions())->toBe(PeerSupportMatrix::constraints());
});

it('fail: PeerVersionProbe::fromComposer marks an out-of-matrix installed peer incompatible [D-011]', function () {
    $probe = PeerVersionProbe::fromComposer(
        versionLookup: static fn (string $package): ?string => '9.0.0',
        classExists: static fn (string $class): bool => true,
    );

    expect($probe->isInstalled(PeerVersionProbe::PEER_MCP))->toBeTrue()
        ->and($probe->isCompatible(PeerVersionProbe::PEER_MCP))->toBeFalse()
        ->and($probe->supports(PeerVersionProbe::PEER_AI))->toBeFalse();
});

it('edge: PeerVersionProbe::fromComposer honours host supportedVersions overrides [D-011]', function () {
    $probe = PeerVersionProbe::fromComposer(
        supportedVersions: [PeerVersionProbe::PEER_AI => ['^9.0']],
        versionLookup: static fn (string $package): ?string => '9.0.0',
        classExists: static fn (string $class): bool => true,
    );

    expect($probe->supports(PeerVersionProbe::PEER_AI))->toBeTrue();
});

it('happy: PeerVersionProbe::composerVersion reads Composer installed versions [D-011]', function () {
    expect(PeerVersionProbe::composerVersion('pestphp/pest'))
        ->toBe(InstalledVersions::getPrettyVersion('pestphp/pest'))
        ->and(PeerVersionProbe::composerVersion('rawphp/not-installed-peer'))->toBeNull();
});

it('PeerVersionProbe reports installed and compatible from overrides and supported versions', function () {
    $probe = new PeerVersionProbe(
        installedOverrides: ['laravel/ai' => true, 'laravel/mcp' => false],
        compatibleOverrides: [],
        versions: ['laravel/ai' => '2.0.0', 'laravel/mcp' => null],
        supportedVersions: ['laravel/ai' => ['1.0.0'], 'laravel/mcp' => ['*']],
    );

    expect($probe->isInstalled('laravel/ai'))->toBeTrue()
        ->and($probe->isInstalled('laravel/mcp'))->toBeFalse()
        ->and($probe->isCompatible('laravel/mcp'))->toBeFalse(); // not installed

    // installed with version not in list
    expect($probe->isCompatible('laravel/ai'))->toBeFalse();

    $probe2 = new PeerVersionProbe(
        installedOverrides: ['laravel/ai' => true],
        compatibleOverrides: [],
        versions: ['laravel/ai' => null],
        supportedVersions: ['laravel/ai' => ['*']],
    );
    expect($probe2->isCompatible('laravel/ai'))->toBeTrue();

    $probe3 = new PeerVersionProbe(
        installedOverrides: [],
        classExists: static fn (string $class): bool => false,
    );
    expect($probe3->isInstalled('laravel/ai'))->toBeFalse();

    $probe4 = new PeerVersionProbe(
        installedOverrides: ['laravel/ai' => true],
        compatibleOverrides: [],
        versions: ['laravel/ai' => '1.0.0'],
        supportedVersions: ['laravel/ai' => ['1.0.0']],
    );
    expect($probe4->isCompatible('laravel/ai'))->toBeTrue();
});
