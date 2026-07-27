<?php

namespace Rawphp\Capabilities\Support;

/**
 * Pure helpers for D-020 schema snapshot lock/compare (unit-test friendly, no IO except file load).
 *
 * Snapshot document shape:
 * ```json
 * {
 *   "input_schema": { ... JSON Schema or null ... },
 *   "output_schema": { ... JSON Schema or null ... }
 * }
 * ```
 */
final class SchemaSnapshot
{
    public const FILE_SUFFIX = '.schema.json';

    /**
     * Conventional path: `{directory}/{capability-name}.schema.json`
     * (slashes in names are replaced so path stays a single file segment).
     */
    public static function conventionalPath(string $directory, string $capabilityName): string
    {
        $safe = str_replace(['/', '\\'], '_', $capabilityName);

        return rtrim($directory, "/\\").DIRECTORY_SEPARATOR.$safe.self::FILE_SUFFIX;
    }

    /**
     * @param  array<string, mixed>|null  $inputSchema
     * @param  array<string, mixed>|null  $outputSchema
     * @return array{input_schema: array<string, mixed>|null, output_schema: array<string, mixed>|null}
     */
    public static function document(?array $inputSchema, ?array $outputSchema): array
    {
        return [
            'input_schema' => $inputSchema,
            'output_schema' => $outputSchema,
        ];
    }

    /**
     * Normalize an in-memory expected value into a full snapshot document.
     *
     * Accepts the envelope `{input_schema, output_schema}`. If only one side is present,
     * the other is treated as unconstrained (null expected side is skipped on compare only
     * when the key is absent — see {@see compare()}).
     *
     * @param  array<string, mixed>  $expected
     * @return array{input_schema?: array<string, mixed>|null, output_schema?: array<string, mixed>|null}
     */
    public static function normalizeExpectedArray(array $expected): array
    {
        $hasEnvelope = array_key_exists('input_schema', $expected)
            || array_key_exists('output_schema', $expected);

        if (! $hasEnvelope) {
            // Treat bare JSON Schema as input-only lock (legacy unit convenience).
            return ['input_schema' => $expected];
        }

        $out = [];
        if (array_key_exists('input_schema', $expected)) {
            $out['input_schema'] = self::schemaOrNull($expected['input_schema']);
        }
        if (array_key_exists('output_schema', $expected)) {
            $out['output_schema'] = self::schemaOrNull($expected['output_schema']);
        }

        return $out;
    }

    /**
     * Load a snapshot document from a JSON file path.
     *
     * @return array{input_schema?: array<string, mixed>|null, output_schema?: array<string, mixed>|null}
     */
    public static function loadFile(string $capability, string $path): array
    {
        if (! is_file($path)) {
            throw SchemaSnapshotException::missingFile($capability, $path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw SchemaSnapshotException::invalidFile($capability, $path, 'unreadable');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw SchemaSnapshotException::invalidFile($capability, $path, 'invalid JSON: '.$e->getMessage());
        }

        if (! is_array($decoded)) {
            throw SchemaSnapshotException::invalidFile($capability, $path, 'root must be a JSON object');
        }

        return self::normalizeExpectedArray($decoded);
    }

    /**
     * Compare live catalog schemas against a locked snapshot.
     *
     * @param  array{input_schema?: array<string, mixed>|null, output_schema?: array<string, mixed>|null}  $expected
     * @param  array<string, mixed>|null  $actualInput
     * @param  array<string, mixed>|null  $actualOutput
     */
    public static function compare(
        string $capability,
        array $expected,
        ?array $actualInput,
        ?array $actualOutput,
    ): void {
        if (array_key_exists('input_schema', $expected)) {
            if (! self::schemasEqual($expected['input_schema'], $actualInput)) {
                throw SchemaSnapshotException::mismatch($capability, 'input_schema');
            }
        }

        if (array_key_exists('output_schema', $expected)) {
            if (! self::schemasEqual($expected['output_schema'], $actualOutput)) {
                throw SchemaSnapshotException::mismatch($capability, 'output_schema');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $a
     * @param  array<string, mixed>|null  $b
     */
    public static function schemasEqual(?array $a, ?array $b): bool
    {
        return self::canonicalJson($a) === self::canonicalJson($b);
    }

    public static function canonicalJson(mixed $value): string
    {
        return json_encode(self::ksortRecursive($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function ksortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::ksortRecursive($v);
        }
        if (! $isList) {
            ksort($out);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function schemaOrNull(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            throw new SchemaSnapshotException('Schema snapshot side must be a JSON object or null.');
        }

        return $value;
    }
}
