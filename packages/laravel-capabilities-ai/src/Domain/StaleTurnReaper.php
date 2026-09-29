<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\EloquentTurnClaim;

/**
 * Fail stale queued/running turns by threshold (D-024).
 * Host schedules {@see ReapStaleTurnsCommand}; package does not auto-schedule.
 *
 * Each reaped turn gets the same error + terminal progress events as a
 * TurnRunner failure, so a client replaying its progress sees it end.
 */
final class StaleTurnReaper
{
    public function __construct(
        private readonly ProgressStore $progress,
        private readonly TurnClaim $claim = new EloquentTurnClaim,
    ) {}

    /**
     * @return array{queued: int, running: int}
     */
    public function reap(
        int $staleQueuedMinutes,
        int $claimTtlSeconds,
        int $runningGraceSeconds,
        ?DateTimeInterface $now = null,
    ): array {
        $now = Carbon::instance($now ?? Carbon::now());
        $queuedCutoff = $now->copy()->subMinutes(max(0, $staleQueuedMinutes));
        $runningSeconds = max($claimTtlSeconds, $runningGraceSeconds);
        $runningCutoff = $now->copy()->subSeconds(max(0, $runningSeconds));

        $queuedError = 'reaped: stale queued';
        $queued = $this->announce($this->claim->failStaleQueued($queuedCutoff, $queuedError, $now), $queuedError);

        $runningError = 'reaped: stale running claim';
        $running = $this->announce($this->claim->failStaleRunning($runningCutoff, $runningError, $now), $runningError);

        return ['queued' => $queued, 'running' => $running];
    }

    /**
     * Append progress only for turns the claim actually flipped (a turn claimed or
     * finished between select and guarded update is left alone and gets nothing).
     *
     * @param  list<string>  $reapedUlids
     */
    private function announce(array $reapedUlids, string $error): int
    {
        foreach ($reapedUlids as $ulid) {
            $this->progress->append($ulid, [
                'kind' => 'error',
                'data' => ['message' => $error],
            ]);
            $this->progress->append($ulid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_FAILED],
            ]);
        }

        return count($reapedUlids);
    }
}
