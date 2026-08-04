<?php

namespace Rawphp\Capabilities\Discovery;

use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * Boot-time auto-discovery into the shared registry (D-017 / REQ-022).
 *
 * Pure entry point for unit tests and {@see CapabilitiesServiceProvider}.
 */
final class CapabilityDiscoveryBoot
{
    /**
     * Discover capabilities from config path(s) into the registry.
     *
     * Missing directories are a no-op (AttributeDiscoverer skips non-dirs).
     * Duplicate names throw via registry register() (D-017 single map).
     *
     * @param  array<string, mixed>  $config  full capabilities config or subset with path
     * @return list<string> discovered capability names
     */
    public static function run(CapabilityRegistry $registry, array $config = []): array
    {
        $paths = DiscoveryPaths::fromConfig($config);
        $before = array_keys($registry->all());

        $registry->discover(paths: $paths);

        $after = array_keys($registry->all());

        return array_values(array_diff($after, $before));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function paths(array $config = []): array
    {
        return DiscoveryPaths::fromConfig($config);
    }
}
