<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;

/**
 * Pure helpers for D-020 assertParity surface resolution and result class labels.
 *
 * Unit-test friendly; no IO. Registry uses these then invokes via {@see \Rawphp\Capabilities\Registry\CapabilityRegistry::invoke}.
 */
final class AssertParity
{
    /**
     * Spec / matrix aliases → registry caller (CapabilityContext::CALLERS).
     *
     * - `ai` → agent (AI adapter path)
     * - `registry` → http (direct registry invoke; same choke point, labeled distinctly in errors)
     *
     * @var array<string, string>
     */
    public const SURFACE_ALIASES = [
        'ai' => 'agent',
        'registry' => 'http',
    ];

    /**
     * Labels accepted in assertParity `surfaces` option (aliases + real callers).
     *
     * @var list<string>
     */
    public const KNOWN_SURFACES = [
        'agent',
        'mcp',
        'http',
        'cli',
        'job',
        'artisan',
        'ai',
        'registry',
    ];

    /**
     * Resolve a surface label to the caller string used by {@see invoke()}.
     *
     * @throws InvalidArgumentException when surface is empty or unknown
     */
    public static function resolveCaller(string $surface): string
    {
        $label = strtolower(trim($surface));
        if ($label === '') {
            throw new InvalidArgumentException('assertParity surfaces must be non-empty strings.');
        }

        if (isset(self::SURFACE_ALIASES[$label])) {
            return self::SURFACE_ALIASES[$label];
        }

        if (! in_array($label, CapabilityContext::CALLERS, true)) {
            throw new InvalidArgumentException(sprintf(
                'assertParity unknown surface "%s"; known: %s',
                $surface,
                implode(', ', self::KNOWN_SURFACES),
            ));
        }

        return $label;
    }

    /**
     * Success vs deny class (approval_required counts as deny).
     */
    public static function resultClass(CapabilityResult $result): string
    {
        return $result->isOk() ? 'success' : 'deny';
    }

    /**
     * Validate and normalize the surfaces list from options.
     *
     * @param  list<string>|null  $surfaces
     * @return list<string>  original labels (not callers) in order
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeSurfaces(?array $surfaces): array
    {
        if ($surfaces === null || $surfaces === []) {
            throw new InvalidArgumentException(
                'assertParity requires a non-empty options.surfaces list (D-020).'
            );
        }

        $out = [];
        foreach ($surfaces as $surface) {
            if (! is_string($surface)) {
                throw new InvalidArgumentException('assertParity surfaces must be non-empty strings.');
            }
            // Validate by resolving; keep original label for error messages.
            self::resolveCaller($surface);
            $out[] = strtolower(trim($surface));
        }

        return $out;
    }
}
