<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Registry\CapabilityRegistry;
use RuntimeException;

/**
 * Thrown by {@see CapabilityRegistry::assertParity}
 * when listed surfaces disagree on success vs deny class (D-020).
 *
 * Messages name the capability, surfaces, and result classes so Pest/PHPUnit
 * failures stay actionable in CI.
 */
final class ParityAssertionException extends RuntimeException
{
    /**
     * @param  array<string, string>  $classesBySurface  surface label => success|deny
     */
    public static function mismatch(string $capability, array $classesBySurface): self
    {
        $parts = [];
        foreach ($classesBySurface as $surface => $class) {
            $parts[] = "{$surface}={$class}";
        }

        return new self(sprintf(
            "assertParity parity mismatch for capability '%s': %s",
            $capability,
            implode(', ', $parts),
        ));
    }
}
