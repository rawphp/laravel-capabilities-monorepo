<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Console\ReapStaleTurnsCommand;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Models\Turn;

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

        $queued = $this->failStale(
            static fn (): Builder => Turn::query()
                ->where('status', Turn::STATUS_QUEUED)
                ->where('created_at', '<', $queuedCutoff),
            'reaped: stale queued',
            $now,
        );

        $running = $this->failStale(
            static fn (): Builder => Turn::query()
                ->where('status', Turn::STATUS_RUNNING)
                ->whereNotNull('claimed_at')
                ->where('claimed_at', '<', $runningCutoff),
            'reaped: stale running claim',
            $now,
        );

        return ['queued' => $queued, 'running' => $running];
    }

    /**
     * Flip each stale turn with a guarded per-row update; append progress only
     * for rows this call actually flipped (a turn claimed or finished between
     * select and update is left alone).
     *
     * @param  callable(): Builder<Turn>  $stale
     */
    private function failStale(callable $stale, string $error, Carbon $now): int
    {
        $reaped = 0;
        foreach ($stale()->pluck('ulid') as $ulid) {
            $rows = $stale()->where('ulid', $ulid)->update([
                'status' => Turn::STATUS_FAILED,
                'error' => $error,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
            if ($rows !== 1) {
                continue;
            }

            $reaped++;
            $this->progress->append((string) $ulid, [
                'kind' => 'error',
                'data' => ['message' => $error],
            ]);
            $this->progress->append((string) $ulid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_FAILED],
            ]);
        }

        return $reaped;
    }
}
