<?php

namespace Rawphp\Capabilities\Events;

/**
 * Emitted after a successful capability run (and after audit stage).
 *
 * Listeners that touch DB should use afterCommit() (D-010).
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

    /**
     * Guidance for app listeners that touch the database (D-010).
     */
    public static function listenersShouldUseAfterCommit(): bool
    {
        return true;
    }
}
