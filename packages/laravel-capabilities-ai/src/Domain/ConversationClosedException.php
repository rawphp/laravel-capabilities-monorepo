<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use RuntimeException;

/**
 * The conversation was closed (destroy); it accepts no new messages or turns (HTTP maps to 409 conflict).
 */
final class ConversationClosedException extends RuntimeException
{
    public function __construct(string $conversationUlid)
    {
        parent::__construct("Conversation {$conversationUlid} is closed");
    }
}
