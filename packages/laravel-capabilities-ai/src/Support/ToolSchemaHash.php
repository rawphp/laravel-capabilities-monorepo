<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

/**
 * Fingerprint of a tool definition's input schema (`parameters`), stamped on a proposal
 * at creation and re-checked at accept so schema drift is not reported as a bad payload.
 */
final class ToolSchemaHash
{
    /**
     * @param  array<string, mixed>  $tool  ToolCatalog entry
     */
    public static function of(array $tool): string
    {
        return hash('sha256', json_encode(
            self::canonical($tool['parameters'] ?? []),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
