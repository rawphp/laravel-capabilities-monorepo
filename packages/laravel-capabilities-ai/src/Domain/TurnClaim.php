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
}
