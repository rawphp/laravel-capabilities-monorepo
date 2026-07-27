<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted when invoke fails (including output_invalid — D-014).
 *
 * Listeners that touch DB should use afterCommit() (D-010).
 */
final class CapabilityFailed
{
    public function __construct(
        public readonly string $capability = '',
        public readonly string $code = '',
        public readonly string $message = '',
        public readonly string $caller = '',
    ) {}

    public static function listenersShouldUseAfterCommit(): bool
    {
        return true;
    }
}
