<?php

namespace Rawphp\Capabilities\Boot;

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\JobSurface;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Http\RouteTable;

/**
 * Pure surface registration tables (SURF-003).
 *
 * Disabled surfaces return empty artifact lists — never half-registered stubs.
 * Host apps map these tables onto Laravel routes/console/bus at boot.
 */
final class SurfaceRegistrar
{
    /**
     * @param  array<string, mixed>  $surfacesConfig  config('capabilities.surfaces')
     * @return list<string>
     */
    public static function artifacts(
        string $surface,
        array $surfacesConfig = [],
        ?PeerVersionProbe $probe = null,
    ): array {
        $surfaceCfg = (array) ($surfacesConfig[$surface] ?? []);
        $enabled = (bool) ($surfaceCfg['enabled'] ?? ($surface !== SurfaceNames::MESSAGING));

        if (! $enabled) {
            return [];
        }

        return match ($surface) {
            SurfaceNames::AGENT => self::agentArtifacts($surfaceCfg, $probe),
            SurfaceNames::MCP => self::mcpArtifacts($surfaceCfg, $probe),
            SurfaceNames::HTTP => self::httpArtifacts($surfaceCfg),
            SurfaceNames::CLI => self::cliArtifacts($surfacesConfig),
            SurfaceNames::JOB => JobSurface::registeredHelpers($surfaceCfg),
            SurfaceNames::ARTISAN => array_column(ArtisanCommandTable::commands($surfaceCfg), 'signature'),
            SurfaceNames::MESSAGING => ['messaging.webhook', 'messaging.threads'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $surfacesConfig
     */
    public static function isRegistered(string $surface, array $surfacesConfig = [], ?PeerVersionProbe $probe = null): bool
    {
        return self::artifacts($surface, $surfacesConfig, $probe) !== [];
    }

    /**
     * Half-registration is never allowed when a surface is disabled or peer fails.
     *
     * @param  array<string, mixed>  $surfacesConfig
     */
    public static function isHalfRegistered(string $surface, array $surfacesConfig = [], ?PeerVersionProbe $probe = null): bool
    {
        // By construction: empty artifacts when disabled; full set when enabled+peers OK.
        // Partial lists are never returned.
        $surfaceCfg = (array) ($surfacesConfig[$surface] ?? []);
        $enabled = (bool) ($surfaceCfg['enabled'] ?? ($surface !== SurfaceNames::MESSAGING));
        $artifacts = self::artifacts($surface, $surfacesConfig, $probe);

        if (! $enabled) {
            return $artifacts !== [];
        }

        // Peer surfaces: if status not up, must have zero tools (not a non-empty partial).
        if (in_array($surface, [SurfaceNames::AGENT, SurfaceNames::MCP], true) && $probe !== null) {
            $peer = SurfaceNames::PEER_PACKAGES[$surface];
            $bootstrap = new PeerSurfaceBootstrap($probe);
            try {
                $status = $bootstrap->evaluate($surface, $peer, $surfaceCfg);
            } catch (\Throwable) {
                return false; // fail path registers nothing
            }
            if (! $status->registersTools && $artifacts !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $surfacesConfig
     * @return array<string, list<string>>
     */
    public static function allArtifacts(array $surfacesConfig = [], ?PeerVersionProbe $probe = null): array
    {
        $out = [];
        foreach (SurfaceNames::ALL as $surface) {
            $out[$surface] = self::artifacts($surface, $surfacesConfig, $probe);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $agentConfig
     * @return list<string>
     */
    private static function agentArtifacts(array $agentConfig, ?PeerVersionProbe $probe): array
    {
        $probe ??= PeerVersionProbe::fake([PeerVersionProbe::PEER_AI => true]);
        $status = self::peerStatus(SurfaceNames::AGENT, PeerVersionProbe::PEER_AI, $agentConfig, $probe);
        if (! $status->registersTools) {
            return [];
        }

        return ['ai.tools', 'ai.tool_handle', 'laravel/ai'];
    }

    /**
     * @param  array<string, mixed>  $mcpConfig
     * @return list<string>
     */
    private static function mcpArtifacts(array $mcpConfig, ?PeerVersionProbe $probe): array
    {
        $probe ??= PeerVersionProbe::fake([PeerVersionProbe::PEER_MCP => true]);
        $status = self::peerStatus(SurfaceNames::MCP, PeerVersionProbe::PEER_MCP, $mcpConfig, $probe);
        if (! $status->registersTools) {
            return [];
        }

        return ['mcp.tools', 'mcp.tool_handle', 'laravel/mcp'];
    }

    /**
     * @param  array<string, mixed>  $httpConfig
     * @return list<string>
     */
    private static function httpArtifacts(array $httpConfig): array
    {
        return array_column(RouteTable::routes($httpConfig), 'key');
    }

    /**
     * CLI product surface: device-code helpers gated by cli + http (SURF-003 / D-009).
     *
     * @param  array<string, mixed>  $surfacesConfig
     * @return list<string>
     */
    private static function cliArtifacts(array $surfacesConfig): array
    {
        $cliCfg = (array) ($surfacesConfig[SurfaceNames::CLI] ?? ['enabled' => true]);
        $httpCfg = (array) ($surfacesConfig[SurfaceNames::HTTP] ?? ['enabled' => true]);
        if (! (bool) ($cliCfg['enabled'] ?? true)) {
            return [];
        }
        if (! (bool) ($httpCfg['enabled'] ?? true)) {
            return [];
        }

        $auth = new AuthController($httpCfg, $cliCfg);
        $flows = [];
        if ($auth->deviceCodeFlowAvailable()) {
            $flows[] = 'cli.device_code';
        }
        if ($auth->tokenFlowAvailable()) {
            $flows[] = 'cli.token';
        }
        $flows[] = 'cli.http_client';

        return $flows;
    }

    /**
     * @param  array<string, mixed>  $surfaceConfig
     */
    private static function peerStatus(
        string $surface,
        string $peer,
        array $surfaceConfig,
        PeerVersionProbe $probe,
    ): PeerSurfaceStatus {
        $bootstrap = new PeerSurfaceBootstrap($probe);
        try {
            return $bootstrap->evaluate($surface, $peer, $surfaceConfig);
        } catch (\Throwable) {
            return new PeerSurfaceStatus(
                surface: $surface,
                status: PeerSurfaceStatus::DISABLED_INCOMPATIBLE,
                registersTools: false,
                peer: $peer,
                reason: 'boot_failed',
            );
        }
    }
}
