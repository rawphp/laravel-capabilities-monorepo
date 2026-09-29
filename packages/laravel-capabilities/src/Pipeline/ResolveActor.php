<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\SystemActor;
use RuntimeException;

/**
 * Pipeline step: resolve User or SystemActor — never null principal (D-002 / PIPE-010).
 */
final class ResolveActor
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function resolve(string $caller, array $options): object
    {
        if (array_key_exists('actor', $options) && $options['actor'] === null) {
            throw new RuntimeException('Actor principal is required; null principal is not allowed (D-002).');
        }

        if (isset($options['context']) && $options['context'] instanceof CapabilityContext) {
            return $options['context']->actor();
        }

        if (isset($options['actor']) && is_object($options['actor'])) {
            return $options['actor'];
        }

        // No implicit principal on any surface (D-002): a missing actor is refused, never
        // replaced by a fabricated user. Jobs get the more specific message.
        if ($caller === 'job') {
            throw new RuntimeException('Job invokes require an explicit SystemActor (or User) principal (D-002).');
        }

        throw new RuntimeException('Actor principal is required; pass options[\'actor\'] (User or SystemActor) or a CapabilityContext (D-002).');
    }

    public static function isSystemActor(object $actor): bool
    {
        return $actor instanceof SystemActor;
    }

    public static function actorType(object $actor): string
    {
        return $actor instanceof SystemActor ? 'system' : 'user';
    }

    public static function actorId(object $actor): string
    {
        if ($actor instanceof SystemActor) {
            return $actor->name;
        }

        if (isset($actor->id)) {
            return (string) $actor->id;
        }

        if (method_exists($actor, 'getAuthIdentifier')) {
            return (string) $actor->getAuthIdentifier();
        }

        return 'unknown';
    }
}
