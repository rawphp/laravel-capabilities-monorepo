<?php

namespace Rawphp\Capabilities\Boot;

/**
 * Typed access to package default config (CFG-001).
 *
 * Loads the published array shape without a Laravel app container.
 */
final class CapabilitiesConfig
{
    /** @var list<string> */
    public const TOP_LEVEL_KEYS = [
        'path',
        'surfaces',
        'audit',
        'transactions',
        'events',
        'approval',
        'idempotency',
        'validation',
        'rate_limits',
        'observability',
        'clients',
        'peers',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2).'/config/capabilities.php';

        return $config;
    }

    /**
     * Dot-path get (e.g. "surfaces.agent.enabled").
     */
    public static function get(string $dotPath, ?array $config = null): mixed
    {
        $config ??= self::defaults();
        $parts = explode('.', $dotPath);
        $cursor = $config;
        foreach ($parts as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                return null;
            }
            $cursor = $cursor[$part];
        }

        return $cursor;
    }

    /**
     * @return array<string, bool>
     */
    public static function globallyEnabledSurfaces(?array $config = null): array
    {
        $config ??= self::defaults();
        $surfaces = $config['surfaces'] ?? [];
        $map = [];
        foreach (SurfaceNames::ALL as $name) {
            $map[$name] = (bool) ($surfaces[$name]['enabled'] ?? ($name !== SurfaceNames::MESSAGING));
        }

        return $map;
    }

    /**
     * Env-style surface toggle key for SURF-005 documentation / mapping.
     */
    public static function envKeyForSurface(string $surface): string
    {
        return 'CAPABILITIES_SURFACE_'.strtoupper($surface);
    }

    /**
     * Map clients.token_abilities ability → caller (D-022).
     *
     * @param  array<string, mixed>  $clientsConfig
     */
    public static function mapTokenAbility(string $ability, array $clientsConfig = []): ?string
    {
        $map = $clientsConfig['token_abilities'] ?? (self::defaults()['clients']['token_abilities'] ?? []);
        if ($ability === '' || $ability === 'unmapped') {
            return null;
        }

        $mapped = $map[$ability] ?? null;

        return is_string($mapped) ? $mapped : null;
    }

    /**
     * Map oauth client id → caller (D-022).
     *
     * @param  array<string, mixed>  $clientsConfig
     */
    public static function mapOauthClient(string $clientId, array $clientsConfig = []): ?string
    {
        $map = $clientsConfig['oauth'] ?? (self::defaults()['clients']['oauth'] ?? []);
        if ($clientId === '' || $clientId === 'unknown') {
            return null;
        }

        $mapped = $map[$clientId] ?? null;

        return is_string($mapped) ? $mapped : null;
    }
}
