<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Models\TableNames;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;

/**
 * Atomic claim: UPDATE … WHERE status=queued; rows===1 required.
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
}
