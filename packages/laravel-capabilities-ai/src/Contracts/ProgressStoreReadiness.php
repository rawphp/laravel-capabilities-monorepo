<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Live probe: can the turn progress store be reached right now?
 *
 * Evaluated on each call — do not freeze at singleton resolve.
 * Fail closed when not ready / unproven.
 */
interface ProgressStoreReadiness
{
    public function isReady(): bool;
}
