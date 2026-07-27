<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Messaging package implements this; core only defines the shape (D-007).
 *
 * Conversation surfaces feed the agent — they never bypass the registry.
 * No Bot API types belong here; channel payloads stay in the messaging package.
 */
interface ConversationIngress
{
    /**
     * Accept an inbound conversation message (package-native DTO/array).
     *
     * @param  array<string, mixed>|object  $message
     * @return array<string, mixed>|object
     */
    public function handle(array|object $message): array|object;
}
