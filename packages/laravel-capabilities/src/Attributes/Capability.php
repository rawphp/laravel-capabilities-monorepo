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
     * @param  list<string>  $groups
     * @param  list<string>  $tags
     * @param  bool|list<string>  $allowSystemCallers  empty/false = deny; true = any; list = named only
     * @param  array<string, mixed>|null  $rateLimit
     * @param  bool|string|null  $idempotent  true|'required'|'optional'|false|'none'
     * @param  array<string, mixed>|bool|null  $audit
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
        public array $groups = [],
        public array $tags = [],
        public bool $readOnly = false,
        public bool|array $allowSystemCallers = false,
        public bool $globalSystem = false,
        public ?string $approvalPolicy = null,
        public ?int $approvalTtlHours = null,
        public ?array $rateLimit = null,
        public bool|string|null $idempotent = null,
        public array|bool|null $audit = null,
    ) {}
}
