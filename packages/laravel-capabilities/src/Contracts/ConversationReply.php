<?php

namespace Rawphp\Capabilities\Contracts;

/**
 * Outbound reply contract for conversation channels (D-007).
 *
 * Implementations live in the messaging package; core has no Bot API dependency.
 */
interface ConversationReply
{
    /**
     * Send a reply on the originating channel/thread.
     *
     * @param  array<string, mixed>|object  $message
     */
    public function reply(array|object $message): void;
}
