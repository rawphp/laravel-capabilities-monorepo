<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted when a human accepts or rejects a pending approval (D-006 / D-010).
 */
final class CapabilityApprovalDecided
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $capability = '',
        public readonly string $approvalId = '',
        public readonly string $decision = '',
        public readonly string $decidedBy = '',
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}

    public static function listenersShouldUseAfterCommit(): bool
    {
        return true;
    }
}
