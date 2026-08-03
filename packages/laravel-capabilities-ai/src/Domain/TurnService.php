<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Models\TableNames;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;
use RuntimeException;

/**
 * Turn query + cancel + progress events for HTTP adapters.
 */
final class TurnService
{
    public function __construct(
        private readonly ProgressStore $progress,
    ) {}

    /**
     * @return array{
     *     turn_ulid: string,
     *     conversation_ulid: string,
     *     status: string,
     *     error: ?string,
     *     claimed_at: ?string,
     *     started_at: ?string,
     *     finished_at: ?string
     * }
     */
    public function show(string $turnUlid): array
    {
        $turn = Turn::query()->where('ulid', $turnUlid)->with('conversation')->first();
        if ($turn === null) {
            throw (new ModelNotFoundException)->setModel(Turn::class, [$turnUlid]);
        }

        return [
            'turn_ulid' => $turn->ulid,
            'conversation_ulid' => (string) ($turn->conversation?->ulid ?? ''),
            'status' => (string) $turn->status,
            'error' => $turn->error,
            'claimed_at' => $turn->claimed_at?->toIso8601String(),
            'started_at' => $turn->started_at?->toIso8601String(),
            'finished_at' => $turn->finished_at?->toIso8601String(),
        ];
    }

    /**
     * Cancel for queued|running. Idempotent if already cancelled.
     *
     * DB status flip is atomic; progress append is best-effort after. If progress
     * fails, the turn remains cancelled and this method throws so callers/subscribers
     * do not assume events were published.
     *
     * @return array{turn_ulid: string, status: string}
     */
    public function cancel(string $turnUlid): array
    {
        $turn = Turn::query()->where('ulid', $turnUlid)->first();
        if ($turn === null) {
            throw (new ModelNotFoundException)->setModel(Turn::class, [$turnUlid]);
        }

        if ($turn->status === Turn::STATUS_CANCELLED) {
            return ['turn_ulid' => $turn->ulid, 'status' => Turn::STATUS_CANCELLED];
        }

        if (in_array($turn->status, [Turn::STATUS_COMPLETED, Turn::STATUS_FAILED], true)) {
            throw new RuntimeException("Turn {$turnUlid} cannot be cancelled (status={$turn->status})");
        }

        $now = Carbon::now()->toDateTimeString();
        $rows = DatabaseConnection::resolve()->table(TableNames::turns())
            ->where('ulid', $turnUlid)
            ->whereIn('status', [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING])
            ->update([
                'status' => Turn::STATUS_CANCELLED,
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows !== 1) {
            // Race: re-read
            $fresh = Turn::query()->where('ulid', $turnUlid)->firstOrFail();
            if ($fresh->status === Turn::STATUS_CANCELLED) {
                return ['turn_ulid' => $fresh->ulid, 'status' => Turn::STATUS_CANCELLED];
            }
            throw new RuntimeException("Turn {$turnUlid} cannot be cancelled (status={$fresh->status})");
        }

        try {
            $this->progress->append($turnUlid, [
                'kind' => 'status',
                'data' => ['status' => Turn::STATUS_CANCELLED],
            ]);
            $this->progress->append($turnUlid, [
                'kind' => 'terminal',
                'data' => ['status' => Turn::STATUS_CANCELLED],
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Turn {$turnUlid} cancelled in DB but progress append failed: ".$e->getMessage(),
                0,
                $e
            );
        }

        return ['turn_ulid' => $turnUlid, 'status' => Turn::STATUS_CANCELLED];
    }

    /**
     * @return list<array{kind: string, data?: mixed, at?: string, index: int}>
     */
    public function events(string $turnUlid, int $cursor = 0): array
    {
        $exists = Turn::query()->where('ulid', $turnUlid)->exists();
        if (! $exists) {
            throw (new ModelNotFoundException)->setModel(Turn::class, [$turnUlid]);
        }

        return $this->progress->since($turnUlid, $cursor);
    }
}
