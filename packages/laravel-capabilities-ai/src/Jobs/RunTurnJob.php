<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Package;
use Throwable;

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

    public function handle(TurnRunner $runner, TurnClaim $claim, ProgressStore $progress): void
    {
        try {
            $runner->run($this->turnUlid);
        } catch (Throwable $e) {
            // Failed before claim (e.g. host seams unbound): with one try nothing will claim it,
            // so fail it now with the real reason instead of leaving it to the stale-queued reaper.
            if ($claim->failUnclaimed($this->turnUlid, $e->getMessage())) {
                $progress->append($this->turnUlid, ['kind' => 'error', 'data' => ['message' => $e->getMessage()]]);
                $progress->append($this->turnUlid, ['kind' => 'terminal', 'data' => ['status' => Turn::STATUS_FAILED]]);
            }

            throw $e;
        }
    }
}
