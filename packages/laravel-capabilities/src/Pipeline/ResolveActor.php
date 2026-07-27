<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\SystemActor;
use RuntimeException;
use stdClass;

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

        // Explicit null is always refused (D-002). Omitting actor falls back to a
        // default user principal so schema/in-process paths stay usable; adapters
        // must set actor (job → SystemActor) in production.
        if ($caller === 'job' && ($options['require_actor'] ?? false) === true) {
            throw new RuntimeException('Job invokes require an explicit SystemActor (or User) principal (D-002).');
        }

        // Default user principal for in-process / schema unit paths when omitted.
        return self::defaultUser();
    }

    public static function defaultUser(int|string $id = 1): object
    {
        $user = new stdClass;
        $user->id = $id;
        $user->name = 'default-user';

        return $user;
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
