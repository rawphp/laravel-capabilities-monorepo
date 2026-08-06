<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Package;

/**
 * Async turn execution — thin adapter over TurnRunner (claim lives in the runner only).
 */
final class RunTurnJob implements ShouldQueue
{
    /** Finite attempts; claim_ttl is the worker heartbeat window. */
    public int $tries = 1;

    /** Seconds; default from Package::DEFAULT_CLAIM_TTL; cheap-create may override from config. */
    public int $timeout = Package::DEFAULT_CLAIM_TTL;

    /** Laravel bus / queue worker read these public props (no Queueable trait required). */
    public ?string $queue = null;

    public ?string $connection = null;

    public function __construct(
        public readonly string $turnUlid,
    ) {}

    public function handle(TurnRunner $runner): void
    {
        $runner->run($this->turnUlid);
    }
}
