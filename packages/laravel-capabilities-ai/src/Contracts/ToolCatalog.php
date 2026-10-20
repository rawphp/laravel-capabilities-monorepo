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
     * @return list<array{name: string, description?: string, parameters?: array<string, mixed>, terminal?: bool}>
     */
    public function toolsForTurn(string $conversationUlid, string $turnUlid): array;
}
