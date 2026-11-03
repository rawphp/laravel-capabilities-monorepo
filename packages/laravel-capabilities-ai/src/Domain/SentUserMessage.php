<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

/**
 * {@see ConversationService::sendUserMessage()} result: the send's ids, and whether this call
 * created them (HTTP 201) or replayed a stored client message key (HTTP 200).
 */
final class SentUserMessage
{
    /**
     * @param  array{conversation_ulid: string, message_ulid: string, turn_ulid: string}  $ids
     */
    public function __construct(
        public readonly array $ids,
        public readonly bool $created,
    ) {}
}
