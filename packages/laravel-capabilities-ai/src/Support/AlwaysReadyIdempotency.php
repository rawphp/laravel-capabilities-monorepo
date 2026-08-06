<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;

/**
 * Always-ready probe for **unit tests only**.
 * Production SP default is {@see StoreBoundIdempotencyReadiness} (fail closed when store unbound).
 */
final class AlwaysReadyIdempotency implements IdempotencyReadiness
{
    public function isReady(): bool
    {
        return true;
    }
}
