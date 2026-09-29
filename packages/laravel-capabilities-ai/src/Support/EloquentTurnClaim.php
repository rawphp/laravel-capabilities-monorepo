<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * {@see TurnClaim} over the package's turns table: every transition is one
 * `UPDATE … WHERE ulid AND status = <from>` and succeeds only when it touched one row.
 */
final class EloquentTurnClaim implements TurnClaim
{
    public function claim(string $turnUlid, string $owner): bool
    {
        $now = Carbon::now()->toDateTimeString();

        return $this->transition($turnUlid, [Turn::STATUS_QUEUED], [
            'status' => Turn::STATUS_RUNNING,
            'claimed_at' => $now,
            'claim_owner' => $owner,
            'started_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function isCancelled(string $turnUlid): bool
    {
        return $this->turns()->where('ulid', $turnUlid)->value('status') === Turn::STATUS_CANCELLED;
    }

    public function complete(string $turnUlid, array $usage): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_RUNNING], [
            'status' => Turn::STATUS_COMPLETED,
            'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
        ]);
    }

    public function fail(string $turnUlid, string $error, array $usage): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_RUNNING], [
            'status' => Turn::STATUS_FAILED,
            'error' => $error,
            'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
        ]);
    }

    public function recordUsage(string $turnUlid, array $usage): void
    {
        $this->turns()
            ->where('ulid', $turnUlid)
            ->update([
                'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
    }

    public function failUnclaimed(string $turnUlid, string $error): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_QUEUED], [
            'status' => Turn::STATUS_FAILED,
            'error' => $error,
        ]);
    }

    public function cancel(string $turnUlid): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING], [
            'status' => Turn::STATUS_CANCELLED,
        ]);
    }

    public function failStaleQueued(DateTimeInterface $createdBefore, string $error, DateTimeInterface $now): array
    {
        return $this->failStale(
            fn (): Builder => $this->turns()
                ->where('status', Turn::STATUS_QUEUED)
                ->where('created_at', '<', $createdBefore),
            $error,
            $now,
        );
    }

    public function failStaleRunning(DateTimeInterface $claimedBefore, string $error, DateTimeInterface $now): array
    {
        return $this->failStale(
            fn (): Builder => $this->turns()
                ->where('status', Turn::STATUS_RUNNING)
                ->whereNotNull('claimed_at')
                ->where('claimed_at', '<', $claimedBefore),
            $error,
            $now,
        );
    }

    /**
     * Select the stale ulids, then flip each with the same stale predicate as its guard,
     * so a turn claimed or finished between select and update is left alone.
     *
     * @param  callable(): Builder  $stale
     * @return list<string>
     */
    private function failStale(callable $stale, string $error, DateTimeInterface $now): array
    {
        $flipped = [];
        foreach ($stale()->pluck('ulid') as $ulid) {
            $rows = $stale()->where('ulid', $ulid)->update([
                'status' => Turn::STATUS_FAILED,
                'error' => $error,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);
            if ($rows === 1) {
                $flipped[] = (string) $ulid;
            }
        }

        return $flipped;
    }

    /**
     * Terminal transition: also stamps finished_at.
     *
     * @param  list<string>  $from
     * @param  array<string, mixed>  $values
     */
    private function finish(string $turnUlid, array $from, array $values): bool
    {
        $now = Carbon::now()->toDateTimeString();

        return $this->transition($turnUlid, $from, $values + ['finished_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param  list<string>  $from
     * @param  array<string, mixed>  $values
     */
    private function transition(string $turnUlid, array $from, array $values): bool
    {
        return $this->turns()
            ->where('ulid', $turnUlid)
            ->whereIn('status', $from)
            ->update($values) === 1;
    }

    /**
     * Base query on the turns table through the model's connection (Laravel DB or Capsule).
     */
    private function turns(): Builder
    {
        return Turn::query()->toBase();
    }
}
