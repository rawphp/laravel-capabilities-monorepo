<?php

namespace Rawphp\Capabilities\RateLimiting;

/**
 * Composes rate-limit keys: actor + capability + surface + tenant (D-013).
 */
final class RateLimitKey
{
    /**
     * Per-actor surface budget key (defaults.per_minute).
     */
    public static function actorSurface(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $surface,
    ): string {
        return implode(':', [
            'rl',
            'actor',
            $tenantId ?? 'none',
            $actorType,
            $actorId,
            $surface,
        ]);
    }

    /**
     * Per-capability budget key (defaults.per_capability_per_minute or override).
     */
    public static function capability(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $capability,
        string $surface,
    ): string {
        return implode(':', [
            'rl',
            'cap',
            $tenantId ?? 'none',
            $actorType,
            $actorId,
            $capability,
            $surface,
        ]);
    }

    /**
     * Assert a composed key includes the expected dimension values.
     *
     * @return list<string>
     */
    public static function parts(string $key): array
    {
        return explode(':', $key);
    }

    public static function includesActor(string $key, string $actorType, string $actorId): bool
    {
        return str_contains($key, $actorType) && str_contains($key, $actorId);
    }

    public static function includesCapability(string $key, string $capability): bool
    {
        return str_contains($key, $capability);
    }

    public static function includesSurface(string $key, string $surface): bool
    {
        return str_contains($key, $surface);
    }

    public static function includesTenant(string $key, ?string $tenantId): bool
    {
        return str_contains($key, $tenantId ?? 'none');
    }
}
