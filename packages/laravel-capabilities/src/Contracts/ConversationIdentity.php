<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Maps external chat identity → product principal before tools may mutate (D-007).
 *
 * Core is channel-agnostic: identity keys are opaque strings, not Bot API types.
 */
interface ConversationIdentity
{
    /**
     * Resolve a linked product actor for the external identity, or null if unlinked.
     *
     * @param  array<string, mixed>  $externalIdentity  e.g. channel + external user id
     * @return object|null  Authenticatable / user principal when linked
     */
    public function resolve(array $externalIdentity): ?object;
}
