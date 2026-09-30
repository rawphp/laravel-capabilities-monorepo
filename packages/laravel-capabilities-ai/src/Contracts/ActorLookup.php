<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

use Rawphp\CapabilitiesAi\Support\EloquentActorLookup;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;

/**
 * Finds the host user behind a conversation's user_id for {@see ResolveConversationActor}.
 *
 * Production: {@see EloquentActorLookup} over the configured user model.
 * Unit tests pass an in-memory lookup, so principal rules run without a database.
 */
interface ActorLookup
{
    /**
     * The user whose key is $userId, or null when there is none.
     */
    public function find(string $userId): ?object;
}
