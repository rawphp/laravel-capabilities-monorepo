<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted after a successful capability run (and after audit stage).
 */
final class CapabilityInvoked
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $capability = '',
        public readonly string $caller = '',
        public readonly mixed $data = null,
        public readonly array $meta = [],
    ) {}
}
