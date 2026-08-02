<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Async turn execution payload. Host queue workers resolve handle via container later.
 * TurnRunner wiring lands in ORI-349.
 */
final class RunTurnJob implements ShouldQueue
{
    public function __construct(
        public readonly string $turnUlid,
    ) {}

    public function handle(): void
    {
        // Claim + TurnRunner in ORI-349
    }
}
