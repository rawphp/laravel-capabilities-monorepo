<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Live accept-time probe: is the host idempotency store ready?
 *
 * Evaluated on each accept — do not freeze at singleton resolve.
 * Fail closed when not ready / unproven.
 */
interface IdempotencyReadiness
{
    public function isReady(): bool;
}
