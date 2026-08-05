<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Host seam: build LLM message list for a conversation turn.
 *
 * Multimodal: user `content` may be a string or a list of provider content blocks
 * (e.g. text + base64 image). Hosts supply image bytes; the package does not store
 * or fetch attachment files.
 */
interface ConversationContextProvider
{
    /**
     * @return list<array{
     *     role: string,
     *     content: string|list<array<string, mixed>>,
     *     tool_call_id?: string,
     *     id?: string,
     *     tool_calls?: list<array<string, mixed>>
     * }>
     */
    public function messagesForTurn(string $conversationUlid, string $turnUlid): array;
}
