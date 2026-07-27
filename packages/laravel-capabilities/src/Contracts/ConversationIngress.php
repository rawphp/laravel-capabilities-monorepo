<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Messaging package implements this; core only defines the shape (D-007).
 *
 * Conversation surfaces feed the agent — they never bypass the registry.
 */
interface ConversationIngress
{
    // public function handle(ConversationMessage $message): ConversationTurnResult;
}
