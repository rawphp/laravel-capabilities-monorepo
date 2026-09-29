<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Models\TableNames;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;

/**
 * Turn-row status transitions, each an atomic compare-and-set (rows===1 required):
 * claim queued→running, complete/fail running→terminal, failUnclaimed queued→failed.
 * Prefers Laravel DB facade when the container is available; Capsule otherwise.
 */
final class TurnClaim
{
    /**
     * @return Turn|null null when claim lost the race (0 rows)
     */
    public function claim(string $turnUlid, string $owner): ?Turn
    {
        $table = TableNames::turns();
        $now = Carbon::now()->toDateTimeString();
        $payload = [
            'status' => Turn::STATUS_RUNNING,
            'claimed_at' => $now,
            'claim_owner' => $owner,
            'started_at' => $now,
            'updated_at' => $now,
        ];

        $rows = DatabaseConnection::resolve()->table($table)
            ->where('ulid', $turnUlid)
            ->where('status', Turn::STATUS_QUEUED)
            ->update($payload);

        if ($rows !== 1) {
            return null;
        }

        return Turn::query()->where('ulid', $turnUlid)->first();
    }

    /**
     * Cooperative cancel probe: true once TurnService::cancel() flipped the row.
     */
    public function isCancelled(string $turnUlid): bool
    {
        return DatabaseConnection::resolve()->table(TableNames::turns())
            ->where('ulid', $turnUlid)
            ->value('status') === Turn::STATUS_CANCELLED;
    }

    /**
     * Finish a claimed turn: UPDATE … WHERE status=running.
     *
     * @param  list<array<string, int>>  $usage
     * @return bool false when the turn already left running (cancelled, reaped)
     */
    public function complete(string $turnUlid, array $usage): bool
    {
        return $this->finishRunning($turnUlid, [
            'status' => Turn::STATUS_COMPLETED,
            'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Fail a claimed turn: UPDATE … WHERE status=running.
     *
     * @param  list<array<string, int>>  $usage
     * @return bool false when the turn already left running (cancelled, reaped)
     */
    public function fail(string $turnUlid, string $error, array $usage): bool
    {
        return $this->finishRunning($turnUlid, [
            'status' => Turn::STATUS_FAILED,
            'error' => $error,
            'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Keep the rounds' accounting on a turn that ended under the runner (e.g. cancelled).
     *
     * @param  list<array<string, int>>  $usage
     */
    public function recordUsage(string $turnUlid, array $usage): void
    {
        DatabaseConnection::resolve()->table(TableNames::turns())
            ->where('ulid', $turnUlid)
            ->update([
                'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
    }

    /**
     * Fail a turn that was never claimed: UPDATE … WHERE status=queued.
     *
     * @return bool false when the turn is missing or already left queued
     */
    public function failUnclaimed(string $turnUlid, string $error): bool
    {
        $now = Carbon::now()->toDateTimeString();

        $rows = DatabaseConnection::resolve()->table(TableNames::turns())
            ->where('ulid', $turnUlid)
            ->where('status', Turn::STATUS_QUEUED)
            ->update([
                'status' => Turn::STATUS_FAILED,
                'error' => $error,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        return $rows === 1;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function finishRunning(string $turnUlid, array $values): bool
    {
        $now = Carbon::now()->toDateTimeString();

        $rows = DatabaseConnection::resolve()->table(TableNames::turns())
            ->where('ulid', $turnUlid)
            ->where('status', Turn::STATUS_RUNNING)
            ->update($values + ['finished_at' => $now, 'updated_at' => $now]);

        return $rows === 1;
    }
}
