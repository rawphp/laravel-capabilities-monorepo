<?php

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Boot\BootGuard;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\Boot\SurfaceRegistrar;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Unit-test helpers for Boot / Config / Events / Observability (REQ-014).
 */
final class BootHelpers
{
    /**
     * @return array<string, mixed>
     */
    public static function config(array $overrides = []): array
    {
        return array_replace_recursive(CapabilitiesConfig::defaults(), $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function surfaces(array $enabledMap = []): array
    {
        $surfaces = CapabilitiesConfig::defaults()['surfaces'];
        foreach ($enabledMap as $name => $enabled) {
            $surfaces[$name]['enabled'] = (bool) $enabled;
        }

        return $surfaces;
    }

    /**
     * @return array<string, bool>
     */
    public static function globalMap(?array $enabledMap = null): array
    {
        $base = SurfaceNames::defaultEnabledMap();
        if ($enabledMap === null) {
            return $base;
        }
        foreach ($enabledMap as $k => $v) {
            $base[$k] = (bool) $v;
        }

        return $base;
    }

    public static function probe(bool $ai = true, bool $mcp = true, ?bool $aiCompatible = null, ?bool $mcpCompatible = null): PeerVersionProbe
    {
        return new PeerVersionProbe(
            classExists: static fn (string $class): bool => false,
            installedOverrides: [
                PeerVersionProbe::PEER_AI => $ai,
                PeerVersionProbe::PEER_MCP => $mcp,
            ],
            compatibleOverrides: [
                PeerVersionProbe::PEER_AI => $aiCompatible ?? $ai,
                PeerVersionProbe::PEER_MCP => $mcpCompatible ?? $mcp,
            ],
            versions: [
                PeerVersionProbe::PEER_AI => $ai ? '1.0.0-test' : null,
                PeerVersionProbe::PEER_MCP => $mcp ? '1.0.0-test' : null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $surfaceConfig
     */
    public static function evaluatePeer(
        string $surface,
        array $surfaceConfig,
        PeerVersionProbe $probe,
    ): PeerSurfaceStatus {
        $peer = SurfaceNames::PEER_PACKAGES[$surface] ?? $surface;

        return (new PeerSurfaceBootstrap($probe))->evaluate($surface, $peer, $surfaceConfig);
    }

    /**
     * @return array{status: PeerSurfaceStatus|null, threw: ?PeerIncompatibleException, registers: bool}
     */
    public static function peerCell(
        string $surface,
        bool $installed,
        bool $compatible,
        string $mode,
        bool $requirePackage = true,
    ): array {
        $peer = $surface === 'agent' ? PeerVersionProbe::PEER_AI : PeerVersionProbe::PEER_MCP;
        $probe = new PeerVersionProbe(
            classExists: static fn (string $class): bool => false,
            installedOverrides: [$peer => $installed, PeerVersionProbe::PEER_AI => $installed || $surface !== 'agent', PeerVersionProbe::PEER_MCP => $installed || $surface !== 'mcp'],
            compatibleOverrides: [$peer => $compatible, PeerVersionProbe::PEER_AI => true, PeerVersionProbe::PEER_MCP => true],
        );
        // Fix overrides precisely for the peer under test:
        $probe = new PeerVersionProbe(
            classExists: static fn (string $class): bool => false,
            installedOverrides: [
                PeerVersionProbe::PEER_AI => $surface === 'agent' ? $installed : true,
                PeerVersionProbe::PEER_MCP => $surface === 'mcp' ? $installed : true,
            ],
            compatibleOverrides: [
                PeerVersionProbe::PEER_AI => $surface === 'agent' ? $compatible : true,
                PeerVersionProbe::PEER_MCP => $surface === 'mcp' ? $compatible : true,
            ],
        );

        $cfg = [
            'enabled' => true,
            'require_package' => $requirePackage,
            'on_incompatible' => $mode,
        ];

        try {
            $status = self::evaluatePeer($surface, $cfg, $probe);

            return [
                'status' => $status,
                'threw' => null,
                'registers' => $status->registersTools,
                'artifacts' => SurfaceRegistrar::artifacts($surface, [$surface => $cfg], $probe),
            ];
        } catch (PeerIncompatibleException $e) {
            return [
                'status' => null,
                'threw' => $e,
                'registers' => false,
                'artifacts' => [],
            ];
        }
    }

    /**
     * @param  list<string>  $capSurfaces
     * @return list<string>
     */
    public static function effective(array $capSurfaces, array $globalEnabled): array
    {
        return BootGuard::effectiveSurfaces($capSurfaces, $globalEnabled);
    }

    public static function definition(array $surfaces = ['agent', 'mcp', 'http', 'cli']): CapabilityDefinition
    {
        return new CapabilityDefinition(
            name: 'boot-probe',
            description: 'boot probe',
            surfaces: $surfaces,
            readOnly: true,
        );
    }

    /**
     * Registry with a capability; used to assert disabled surfaces do not reach domain.
     *
     * @return array{registry: CapabilityRegistry, runs: object, name: string}
     */
    public static function invokeHarness(array $globalEnabled = []): array
    {
        $runs = new class
        {
            public int $value = 0;
        };
        $registry = new CapabilityRegistry(globallyEnabledSurfaces: self::globalMap($globalEnabled));
        $name = 'boot-invoke-probe';
        $registry->register(new CapabilityDefinition(
            name: $name,
            description: 'probe',
            surfaces: SurfaceNames::INVOKE_DEFAULT_ON,
            input: null,
            readOnly: true,
            run: static function () use ($runs) {
                $runs->value++;

                return CapabilityResult::ok(['ok' => true]);
            },
        ));

        return ['registry' => $registry, 'runs' => $runs, 'name' => $name];
    }

    public static function tryInvoke(CapabilityRegistry $registry, string $name, string $caller): CapabilityResult
    {
        return $registry->invoke($name, [], [
            'caller' => $caller,
            'actor' => new SystemActor('boot-test'),
        ]);
    }
}

