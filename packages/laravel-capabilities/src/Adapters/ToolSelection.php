<?php

namespace Rawphp\Capabilities\Adapters;

/**
 * Profile / groups / only selection for peer tool registration (D-008 / D-011).
 *
 * @param  string|array<string, mixed>|list<string>  $profile
 */
final class ToolSelection
{
    /**
     * @param  string|array<string, mixed>|list<string>  $profile
     */
    public function __construct(
        public readonly string|array $profile,
    ) {}

    /**
     * @param  string|array<string, mixed>|list<string>  $profile
     */
    public static function of(string|array $profile): self
    {
        return new self($profile);
    }
}
