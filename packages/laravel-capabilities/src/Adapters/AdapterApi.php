<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Internal bridge version for peer adapters (D-011) — not the peer package version.
 *
 * Bump when Tool/Mcp mapping call shapes change. Apps depend on stable
 * Capability::aiTools / mcpTools surfaces; AdapterApi is an internal detail.
 */
final class AdapterApi
{
    public const V1 = 1;

    public const CURRENT = self::V1;

    /**
     * Known bridge versions this package can select via PeerVersionProbe.
     *
     * @return list<int>
     */
    public static function supported(): array
    {
        return [self::V1];
    }

    /**
     * Factory selection for future V2+ — apps never call this for tool listing.
     */
    public static function select(int $requested = self::CURRENT): int
    {
        if (! in_array($requested, self::supported(), true)) {
            return self::CURRENT;
        }

        return $requested;
    }

    /**
     * Whether a bridge call-shape change requires an AdapterApi bump (contract guard).
     *
     * @param  array<string, mixed>  $previousShape
     * @param  array<string, mixed>  $nextShape
     */
    public static function requiresBump(array $previousShape, array $nextShape): bool
    {
        return $previousShape !== $nextShape;
    }
}
