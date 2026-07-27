<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;

/**
 * Thrown when artisan flags are contradictory (e.g. both --acting-as and --system) (D-002).
 */
final class InvalidArtisanFlagsException extends InvalidArgumentException
{
    public static function bothActorFlags(): self
    {
        return new self(
            'Artisan capability:run accepts exactly one of --acting-as or --system, not both (D-002).',
        );
    }
}
