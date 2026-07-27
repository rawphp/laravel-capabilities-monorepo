<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Machine-readable peer support matrix (D-011).
 *
 * Single source of truth for which laravel/ai and laravel/mcp version
 * constraints this package declares support for. PeerVersionProbe defaults
 * to these constraints — not bare `*` forever.
 *
 * Maintainers: update when contract tests pass against a new peer minor/major,
 * when dropping a version, or when AdapterApi bumps. CI contract jobs must
 * stay in lockstep (see docs/spec.md peer support matrix).
 */
final class PeerSupportMatrix
{
    public const PEER_AI = 'laravel/ai';

    public const PEER_MCP = 'laravel/mcp';

    /**
     * Declared Composer-style version constraints per peer.
     *
     * Placeholders until first release pins real minors in CI — still non-empty
     * and not bare `*`, so compatibility is matrix-driven rather than open-ended.
     *
     * @return array<string, list<string>>
     */
    public static function constraints(): array
    {
        return [
            self::PEER_AI => ['^0.1', '^1.0'],
            self::PEER_MCP => ['^0.1', '^1.0'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function peers(): array
    {
        return [self::PEER_AI, self::PEER_MCP];
    }

    /**
     * @return list<string>
     */
    public static function for(string $peer): array
    {
        return self::constraints()[$peer] ?? [];
    }

    /**
     * Whether an installed version string satisfies any declared constraint.
     *
     * @param  list<string>  $constraints
     */
    public static function versionSatisfies(string $version, array $constraints): bool
    {
        foreach ($constraints as $constraint) {
            if (self::matchesConstraint($version, (string) $constraint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the peer+version pair is within this matrix.
     */
    public static function supports(string $peer, ?string $version): bool
    {
        $allowed = self::for($peer);
        if ($allowed === []) {
            return false;
        }

        if ($version === null || $version === '') {
            // Feature-detect without a version pin: presence only is not enough
            // for a hard deny here — probe treats null version as compatible when
            // installed; matrix support for an unknown version is false only when
            // a concrete version fails constraints.
            return true;
        }

        return self::versionSatisfies($version, $allowed);
    }

    private static function matchesConstraint(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            return self::matchesCaret($version, substr($constraint, 1));
        }

        if (str_starts_with($constraint, '~')) {
            return self::matchesTilde($version, substr($constraint, 1));
        }

        // Exact version (or equality without operator).
        return version_compare(self::normalize($version), self::normalize($constraint), '==');
    }

    private static function matchesCaret(string $version, string $base): bool
    {
        $v = self::normalize($version);
        $b = self::normalize($base);

        if (version_compare($v, $b, '<')) {
            return false;
        }

        [$maj, $min, $pat] = self::parts($b);

        if ($maj > 0) {
            $upper = ($maj + 1).'.0.0';
        } elseif ($min > 0) {
            $upper = '0.'.($min + 1).'.0';
        } else {
            $upper = '0.0.'.($pat + 1);
        }

        return version_compare($v, $upper, '<');
    }

    private static function matchesTilde(string $version, string $base): bool
    {
        $v = self::normalize($version);
        $b = self::normalize($base);

        if (version_compare($v, $b, '<')) {
            return false;
        }

        [$maj, $min] = self::parts($b);
        $upper = $maj.'.'.($min + 1).'.0';

        return version_compare($v, $upper, '<');
    }

    /**
     * Normalize to major.minor.patch for version_compare (strips pre-release / build).
     */
    private static function normalize(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $version, $m) === 1) {
            return sprintf(
                '%d.%d.%d',
                (int) $m[1],
                (int) ($m[2] ?? 0),
                (int) ($m[3] ?? 0),
            );
        }

        return $version;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function parts(string $normalized): array
    {
        $chunks = array_map('intval', explode('.', $normalized.'.0.0'));

        return [$chunks[0], $chunks[1], $chunks[2]];
    }
}
