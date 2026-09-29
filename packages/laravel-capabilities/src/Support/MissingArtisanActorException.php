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

    public static function unresolvableUser(int|string $id): self
    {
        return new self(sprintf(
            'Artisan --acting-as user id "%s" requires a user_resolver (the registry requester resolver) to load a real user; fabricated actors are not allowed (D-002).',
            (string) $id,
        ));
    }

    public static function userNotFound(int|string $id): self
    {
        return new self(sprintf('User id "%s" not found for artisan --acting-as (D-002).', (string) $id));
    }
}
