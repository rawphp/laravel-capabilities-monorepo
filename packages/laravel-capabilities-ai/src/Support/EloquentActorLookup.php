<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\ActorLookup;

/**
 * {@see ActorLookup} over a queryable user model class (`$modelClass::query()->find()`).
 */
final class EloquentActorLookup implements ActorLookup
{
    /**
     * @param  class-string  $modelClass  Checked by {@see ResolveConversationActor::assertQueryableModel()}
     */
    public function __construct(
        private readonly string $modelClass,
    ) {}

    public function find(string $userId): ?object
    {
        $user = $this->modelClass::query()->find($userId);
        // Integer primary keys: retry with an int so strict drivers match the stored key.
        if ($user === null && ctype_digit($userId)) {
            $user = $this->modelClass::query()->find((int) $userId);
        }

        return is_object($user) ? $user : null;
    }
}
