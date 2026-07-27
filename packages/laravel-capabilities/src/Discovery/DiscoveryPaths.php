<?php

namespace Rawphp\Capabilities\Discovery;

/**
 * Configured discovery path helpers (D-017).
 */
final class DiscoveryPaths
{
    /**
     * Default path: app/Capabilities (app_path when Laravel helpers exist).
     */
    public static function default(): string
    {
        return AttributeDiscoverer::defaultPath();
    }

    /**
     * @param  array{path?: string|list<string>}  $config
     * @return list<string>
     */
    public static function fromConfig(array $config): array
    {
        $path = $config['path'] ?? self::default();
        if (is_string($path)) {
            return [$path];
        }

        return array_values($path);
    }
}
