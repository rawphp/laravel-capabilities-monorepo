<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Host seam: build LLM message list for a conversation turn.
 */
interface ConversationContextProvider
{
    /**
     * @return list<array{role: string, content: string}>
     */
    public function messagesForTurn(string $conversationUlid, string $turnUlid): array;
}
