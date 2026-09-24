<?php

namespace Rawphp\Capabilities\Support;

use RuntimeException;

/**
 * Thrown when a job dispatch omits actingAs (D-002).
 */
final class MissingJobActorException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('Job dispatch requires actingAs (User id or SystemActor); null principal is not allowed (D-002).');
    }

    public static function unresolvableUser(int|string $id): self
    {
        return new self(sprintf(
            'Job actingAs user id "%s" requires a user_resolver callable to load a real user; fabricated actors are not allowed (D-002).',
            (string) $id,
        ));
    }
}
