<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Models\TableNames;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * Atomic claim: UPDATE … WHERE status=queued; rows===1 required.
 * Prefers Laravel DB facade when the container is available; Capsule otherwise.
 */
final class TurnClaim
{
    /**
     * @return Turn|null  null when claim lost the race (0 rows)
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

        $rows = $this->connection()->table($table)
            ->where('ulid', $turnUlid)
            ->where('status', Turn::STATUS_QUEUED)
            ->update($payload);

        if ($rows !== 1) {
            return null;
        }

        return Turn::query()->where('ulid', $turnUlid)->first();
    }

    /**
     * @return \Illuminate\Database\Connection
     */
    private function connection()
    {
        if (
            function_exists('app')
            && class_exists(\Illuminate\Support\Facades\DB::class)
        ) {
            try {
                if (app()->bound('db')) {
                    return \Illuminate\Support\Facades\DB::connection();
                }
            } catch (\Throwable) {
                // fall through to Capsule
            }
        }

        return \Illuminate\Database\Capsule\Manager::connection();
    }
}
