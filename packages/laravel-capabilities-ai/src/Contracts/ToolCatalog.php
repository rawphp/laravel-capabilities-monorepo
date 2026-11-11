<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Host seam: tools the model may call (names map to capability bus).
 */
interface ToolCatalog
{
    /**
     * `terminal` (default false) marks a fire-and-forget tool: a round whose text is non-empty
     * and whose calls are all terminal and all ok is the turn's reply, with no further round.
     *
     * `defer_loading` (default false) keeps the tool out of the model's context until it finds
     * the tool by searching. The Anthropic client sends it as Anthropic's `defer_loading`; turn
     * on `llm.anthropic.tool_search` so the model can search, and leave at least one tool
     * undeferred (a request whose tools are all deferred is refused). A deferred tool is still
     * offered: TurnRunner runs a call to it like any other.
     *
     * @return list<array{name: string, description?: string, parameters?: array<string, mixed>, terminal?: bool, defer_loading?: bool}>
     */
    public function toolsForTurn(string $conversationUlid, string $turnUlid): array;
}
