<?php

namespace Rawphp\CapabilitiesMessaging\Identity;

use RuntimeException;

/**
 * Default IdentityLinker user factory: loads the linked id from the host user model (D-002/D-003),
 * so capability authorize()/policies see the same principal type as on HTTP or AI turns.
 *
 * Fails closed when no model is configured, the class is unusable, or the id does not resolve.
 * Checked on use (first linked-user resolve), not at boot (D-021).
 */
final class ModelUserFactory
{
    /**
     * @param  class-string|string|null  $modelClass  capabilities-messaging.user_model, else auth.providers.users.model
     */
    public function __construct(
        private readonly ?string $modelClass,
    ) {}

    public function __invoke(string $userId, ?string $tenantId): object
    {
        $model = $this->modelClass;
        if ($model === null || $model === '') {
            throw new RuntimeException(
                'No user model configured for chat identities (set capabilities-messaging.user_model or auth.providers.users.model).'
            );
        }
        if (! class_exists($model)) {
            throw new RuntimeException("Configured user model [{$model}] does not exist; refusing chat identity.");
        }
        if (! method_exists($model, 'query')) {
            throw new RuntimeException("Configured user model [{$model}] is not queryable; refusing chat identity.");
        }

        $user = $model::query()->find($userId);
        if (! is_object($user)) {
            throw new RuntimeException("Linked user id [{$userId}] does not resolve to a {$model}; refusing chat identity.");
        }

        return $user;
    }
}
