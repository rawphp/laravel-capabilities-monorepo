<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Rawphp\CapabilitiesAi\Contracts\ConversationStore;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Models\Turn;
use Rawphp\CapabilitiesAi\Support\EloquentConversationStore;
use Rawphp\CapabilitiesAi\Support\EloquentTurnClaim;
use RuntimeException;

/**
 * Turn query + cancel + progress events for HTTP adapters.
 *
 * Every lookup is scoped to the conversation owner: another owner's turn is not found.
 */
final class TurnService
{
    public function __construct(
        private readonly ProgressStore $progress,
        private readonly ConversationStore $store = new EloquentConversationStore,
        private readonly TurnClaim $claim = new EloquentTurnClaim,
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
    public function show(string $turnUlid, string $ownerId): array
    {
        $turn = $this->store->ownedTurn($turnUlid, $ownerId);

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
     * Status flip is an atomic {@see TurnClaim::cancel()}; progress append is best-effort
     * after. If progress fails, the turn remains cancelled and this method throws so
     * callers/subscribers do not assume events were published.
     *
     * @return array{turn_ulid: string, status: string}
     */
    public function cancel(string $turnUlid, string $ownerId): array
    {
        $turn = $this->store->ownedTurn($turnUlid, $ownerId);

        if ($turn->status === Turn::STATUS_CANCELLED) {
            return ['turn_ulid' => $turn->ulid, 'status' => Turn::STATUS_CANCELLED];
        }

        if (in_array($turn->status, [Turn::STATUS_COMPLETED, Turn::STATUS_FAILED], true)) {
            throw new RuntimeException("Turn {$turnUlid} cannot be cancelled (status={$turn->status})");
        }

        if (! $this->claim->cancel($turnUlid)) {
            // Race: re-read
            $fresh = $this->store->turn($turnUlid);
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
    public function events(string $turnUlid, string $ownerId, int $cursor = 0): array
    {
        $this->store->ownedTurn($turnUlid, $ownerId);

        return $this->progress->since($turnUlid, $cursor);
    }
}
