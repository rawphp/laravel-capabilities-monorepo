<?php

namespace Rawphp\Capabilities\Attributes;

use Attribute;

/**
 * Marks a class as a product capability (canonical discovery form — D-017).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Capability
{
    /**
     * @param  list<string>  $surfaces
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $surfaces = ['agent', 'mcp', 'http', 'cli'],
        public ?string $input = null,
        public ?string $output = null,
        public array $aliases = [],
        public bool $deprecated = false,
        public ?string $successor = null,
        public ?string $sunset_at = null,
    ) {}
}
