<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;

/**
 * Package default: readiness proven ready (host may rebind a live probe).
 */
final class AlwaysReadyIdempotency implements IdempotencyReadiness
{
    public function isReady(): bool
    {
        return true;
    }
}
