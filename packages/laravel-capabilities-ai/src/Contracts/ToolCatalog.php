<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Host seam: tools the model may call (names map to capability bus).
 */
interface ToolCatalog
{
    /**
     * @return list<array{name: string, description?: string, parameters?: array<string, mixed>}>
     */
    public function toolsForTurn(string $conversationUlid, string $turnUlid): array;
}
