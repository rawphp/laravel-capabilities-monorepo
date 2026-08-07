<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * Fail stale queued/running turns by threshold (D-024).
 * Host schedules {@see ReapStaleTurnsCommand}; package does not auto-schedule.
 */
final class StaleTurnReaper
{
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

        $queued = Turn::query()
            ->where('status', Turn::STATUS_QUEUED)
            ->where('created_at', '<', $queuedCutoff)
            ->update([
                'status' => Turn::STATUS_FAILED,
                'error' => 'reaped: stale queued',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        $running = Turn::query()
            ->where('status', Turn::STATUS_RUNNING)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', $runningCutoff)
            ->update([
                'status' => Turn::STATUS_FAILED,
                'error' => 'reaped: stale running claim',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        return ['queued' => (int) $queued, 'running' => (int) $running];
    }
}
