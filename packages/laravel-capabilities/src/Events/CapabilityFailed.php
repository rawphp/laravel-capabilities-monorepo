<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted when invoke fails (including output_invalid — D-014).
 */
final class CapabilityFailed
{
    public function __construct(
        public readonly string $capability = '',
        public readonly string $code = '',
        public readonly string $message = '',
        public readonly string $caller = '',
    ) {}
}
