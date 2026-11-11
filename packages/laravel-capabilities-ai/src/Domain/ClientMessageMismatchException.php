<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use RuntimeException;

/**
 * A stored client message key was sent again with different content (HTTP maps to 422 on client_message_id).
 */
final class ClientMessageMismatchException extends RuntimeException
{
    public function __construct(string $clientMessageId)
    {
        parent::__construct("The client_message_id {$clientMessageId} was already used for different content.");
    }
}
