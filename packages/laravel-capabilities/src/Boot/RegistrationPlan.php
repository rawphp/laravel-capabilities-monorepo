<?php

namespace Rawphp\Capabilities\Boot;

use Rawphp\Capabilities\Adapters\PeerVersionProbe;

/**
 * Snapshot of what CapabilitiesServiceProvider would register (BOOT-001 / SURF-003).
 *
 * Pure function of config + peer probe — no Illuminate Application required.
 */
final class RegistrationPlan
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *     config_merged: bool,
     *     bindings: list<string>,
     *     publish_tags: list<string>,
     *     routes: list<string>,
     *     commands: list<string>,
     *     surfaces: array<string, list<string>>,
     *     registry_singleton: bool
     * }
     */
    public static function build(array $config = [], ?PeerVersionProbe $probe = null): array
    {
        $config = $config === [] ? CapabilitiesConfig::defaults() : $config;
        $surfaces = (array) ($config['surfaces'] ?? []);
        $probe ??= PeerVersionProbe::fake([
            PeerVersionProbe::PEER_AI => true,
            PeerVersionProbe::PEER_MCP => true,
        ]);

        $artifacts = SurfaceRegistrar::allArtifacts($surfaces, $probe);

        return [
            'config_merged' => true,
            'bindings' => ContainerBindings::abstracts(),
            'publish_tags' => ContainerBindings::PUBLISH_TAGS,
            'routes' => $artifacts[SurfaceNames::HTTP] ?? [],
            'commands' => $artifacts[SurfaceNames::ARTISAN] ?? [],
            'surfaces' => $artifacts,
            'registry_singleton' => true,
            'ai_tools' => $artifacts[SurfaceNames::AGENT] ?? [],
            'mcp_tools' => $artifacts[SurfaceNames::MCP] ?? [],
        ];
    }
}
