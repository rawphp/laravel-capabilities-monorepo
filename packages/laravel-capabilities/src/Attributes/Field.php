<?php

namespace Rawphp\Capabilities\Attributes;

use Attribute;

/**
 * Optional field metadata for DTO → JSON Schema (scaffold).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Field
{
    public function __construct(
        public string $description = '',
    ) {}
}
