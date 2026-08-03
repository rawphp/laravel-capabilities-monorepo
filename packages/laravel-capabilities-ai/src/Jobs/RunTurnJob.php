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
    /** Finite attempts; worker claim TTL is separate (config claim_ttl). */
    public int $tries = 1;

    /**
     * Queue worker kill timeout (seconds).
     * Default mirrors config claim_ttl default (120) but is not live-bound to config —
     * pass an explicit timeout at construction (see ConversationService / host dispatch)
     * when claim_ttl is customized.
     */
    public int $timeout;

    public function __construct(
        public readonly string $turnUlid,
        ?int $timeout = null,
    ) {
        $this->timeout = $timeout ?? 120;
    }

    public function handle(TurnRunner $runner): void
    {
        $runner->run($this->turnUlid);
    }
}
