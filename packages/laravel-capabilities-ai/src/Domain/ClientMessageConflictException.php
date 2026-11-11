<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use RuntimeException;
use Throwable;

/**
 * Another send already stored this client message key for this owner (HTTP replays it or maps to 409).
 * Nothing from this send was persisted or dispatched.
 */
final class ClientMessageConflictException extends RuntimeException
{
    public function __construct(string $clientMessageId, ?Throwable $previous = null)
    {
        parent::__construct("A message with client_message_id {$clientMessageId} is already stored", 0, $previous);
    }
}
