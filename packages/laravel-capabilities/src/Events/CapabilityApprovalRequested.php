<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted when run is gated behind approval (D-006).
 */
final class CapabilityApprovalRequested
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $capability = '',
        public readonly string $approvalId = '',
        public readonly string $caller = '',
        public readonly array $meta = [],
    ) {}

    public static function listenersShouldUseAfterCommit(): bool
    {
        return true;
    }
}
