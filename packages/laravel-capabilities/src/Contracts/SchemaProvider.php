<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Escape hatch / DTO contract for JSON Schema + hydration (D-015).
 *
 * Package-native {@see \Rawphp\Capabilities\Support\CapabilityData} implements this.
 * Custom types and optional Spatie bridges may implement it directly.
 */
interface SchemaProvider
{
    /**
     * Portable JSON Schema document (draft 2020-12 preferred).
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array;

    /**
     * Validate and hydrate wire data into a typed object.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validate(array $data): object;
}
