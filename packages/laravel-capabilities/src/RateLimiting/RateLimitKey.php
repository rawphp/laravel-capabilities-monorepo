<?php

namespace Rawphp\Capabilities\RateLimiting;

/**
 * Composes rate-limit keys: actor + capability + surface + tenant (D-013).
 *
 * Each dimension is rawurlencoded so a ':' inside a tenant, actor id, or
 * capability name cannot shift segments and merge two distinct buckets.
 */
final class RateLimitKey
{
    /** Segment for a null tenant. rawurlencode never emits '*', so no tenant id can produce it. */
    private const NO_TENANT = '*';

    /**
     * Per-actor surface budget key (defaults.per_minute).
     */
    public static function actorSurface(
        ?string $tenantId,
        string $actorType,
        string $actorId,
        string $surface,
    ): string {
        return self::compose('actor', $tenantId, [$actorType, $actorId, $surface]);
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
        return self::compose('cap', $tenantId, [$actorType, $actorId, $capability, $surface]);
    }

    /**
     * Decoded key segments, in order.
     *
     * @return list<string>
     */
    public static function parts(string $key): array
    {
        return array_map(rawurldecode(...), explode(':', $key));
    }

    public static function includesActor(string $key, string $actorType, string $actorId): bool
    {
        return self::hasSegment($key, $actorType) && self::hasSegment($key, $actorId);
    }

    public static function includesCapability(string $key, string $capability): bool
    {
        return self::hasSegment($key, $capability);
    }

    public static function includesSurface(string $key, string $surface): bool
    {
        return self::hasSegment($key, $surface);
    }

    public static function includesTenant(string $key, ?string $tenantId): bool
    {
        return in_array(self::tenantSegment($tenantId), explode(':', $key), true);
    }

    /**
     * @param  list<string>  $dimensions
     */
    private static function compose(string $scope, ?string $tenantId, array $dimensions): string
    {
        return implode(':', [
            'rl',
            $scope,
            self::tenantSegment($tenantId),
            ...array_map(rawurlencode(...), $dimensions),
        ]);
    }

    private static function tenantSegment(?string $tenantId): string
    {
        return $tenantId === null ? self::NO_TENANT : rawurlencode($tenantId);
    }

    private static function hasSegment(string $key, string $value): bool
    {
        return in_array(rawurlencode($value), explode(':', $key), true);
    }
}
