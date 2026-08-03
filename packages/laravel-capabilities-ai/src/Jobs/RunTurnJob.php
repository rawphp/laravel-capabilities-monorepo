<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;

/**
 * Async turn execution — thin adapter over TurnRunner (claim lives in the runner only).
 */
final class RunTurnJob implements ShouldQueue
{
    /** Finite attempts; claim_ttl is the worker heartbeat window (config default 120). */
    public int $tries = 1;

    /** Seconds; aligned with capabilities-ai.claim_ttl default (120). */
    public int $timeout = 120;

    public function __construct(
        public readonly string $turnUlid,
    ) {}

    public function handle(TurnRunner $runner): void
    {
        $runner->run($this->turnUlid);
    }
}
