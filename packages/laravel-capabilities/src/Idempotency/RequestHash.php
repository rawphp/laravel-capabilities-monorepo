<?php

namespace Rawphp\Capabilities\Idempotency;

use JsonException;

/**
 * Canonical request body hash for idempotency conflict detection (D-005).
 *
 * Hash of canonical input JSON — same logical payload → same hash.
 */
final class RequestHash
{
    /**
     * @param  array<string, mixed>|list<mixed>|string|int|float|bool|null  $input
     *
     * @throws JsonException
     */
    public static function of(mixed $input): string
    {
        if (is_string($input) && self::looksLikeJson($input)) {
            $decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            $payload = $decoded;
        } else {
            $payload = $input;
        }

        $canonical = self::canonicalize($payload);
        $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

    /**
     * Recursively sort object keys for stable hashing; preserve list order.
     */
    public static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (self::isList($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = self::canonicalize($item);
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private static function looksLikeJson(string $input): bool
    {
        $trim = ltrim($input);

        return $trim !== '' && ($trim[0] === '{' || $trim[0] === '[');
    }
}
