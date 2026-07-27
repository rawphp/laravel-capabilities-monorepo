<?php

namespace Rawphp\Capabilities\Attributes;

use Attribute;

/**
 * Field metadata for DTO → JSON Schema (D-015).
 *
 * Portable constraints (min/max/enum/format/…) appear in {@see \Rawphp\Capabilities\Support\CapabilityData::jsonSchema()}.
 * Server-only rules stay on {@see \Rawphp\Capabilities\Support\CapabilityData::rules()}.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Field
{
    /**
     * @param  list<string|int|float|bool>|null  $enum
     * @param  class-string|null  $items  SchemaProvider / CapabilityData class for array items
     * @param  class-string|null  $of  Nested object SchemaProvider class (when property is object)
     */
    public function __construct(
        public string $description = '',
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?array $enum = null,
        public ?string $format = null,
        public ?string $items = null,
        public ?string $of = null,
    ) {}
}
