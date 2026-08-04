<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Registry\CapabilityRegistry;
use RuntimeException;

/**
 * Thrown by {@see CapabilityRegistry::assertSchemaSnapshot}
 * when a locked schema snapshot is missing or drifts from the live catalog (D-020).
 *
 * Messages name the capability and which side mismatched (`input_schema` / `output_schema`)
 * so Pest/PHPUnit failures stay actionable in CI.
 */
final class SchemaSnapshotException extends RuntimeException
{
    public static function missingFile(string $capability, string $path): self
    {
        return new self(
            "Schema snapshot file missing for capability '{$capability}': {$path}"
        );
    }

    public static function invalidFile(string $capability, string $path, string $reason): self
    {
        return new self(
            "Schema snapshot file invalid for capability '{$capability}' ({$path}): {$reason}"
        );
    }

    /**
     * @param  'input_schema'|'output_schema'  $side
     */
    public static function mismatch(string $capability, string $side): self
    {
        return new self(
            "Schema snapshot mismatch for capability '{$capability}' on {$side}"
        );
    }
}
