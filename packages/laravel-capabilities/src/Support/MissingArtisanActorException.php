<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Thrown when artisan capability:run mutates without --acting-as or --system (D-002).
 */
final class MissingArtisanActorException extends RuntimeException
{
    public static function missing(): self
    {
        return new self(
            'Artisan capability:run for mutating capabilities requires --acting-as=<user_id> or --system=<name>; null principal is not allowed (D-002).',
        );
    }
}
