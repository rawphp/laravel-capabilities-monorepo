<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted after approved work has run exactly once (D-006 / D-010).
 */
final class CapabilityApprovalExecuted
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $capability = '',
        public readonly string $approvalId = '',
        public readonly string $via = 'accept',
        public readonly bool $replay = false,
        public readonly mixed $result = null,
        public readonly array $meta = [],
    ) {}

    public static function listenersShouldUseAfterCommit(): bool
    {
        return true;
    }
}
