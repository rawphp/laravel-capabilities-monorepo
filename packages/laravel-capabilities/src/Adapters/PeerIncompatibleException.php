<?php

namespace Rawphp\Capabilities\Adapters;

use RuntimeException;

/**
 * Peer missing or incompatible while surface is enabled (D-011).
 */
final class PeerIncompatibleException extends RuntimeException
{
    public static function missing(string $peer, string $surface): self
    {
        return new self(sprintf(
            'Surface "%s" requires peer package "%s" but it is not installed (D-011).',
            $surface,
            $peer,
        ));
    }

    public static function incompatible(string $peer, string $surface, ?string $installed = null): self
    {
        return new self(sprintf(
            'Surface "%s" peer "%s" is installed but incompatible%s (D-011). Upgrade rawphp/laravel-capabilities or pin the peer.',
            $surface,
            $peer,
            $installed !== null ? ' (installed='.$installed.')' : '',
        ));
    }
}
