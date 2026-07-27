<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Client attempted to upgrade caller via X-Capabilities-Caller when reject_upgrade_attempts is true (D-022).
 */
final class CallerClaimRejectedException extends RuntimeException
{
    public static function upgrade(string $derived, string $claimed): self
    {
        return new self(sprintf(
            'Caller claim rejected: derived=%s claimed=%s (D-022).',
            $derived,
            $claimed,
        ));
    }
}
