<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Tests\Fakes;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Contracts\TurnClaim;
use Rawphp\CapabilitiesAi\Models\Turn;

/**
 * In-memory TurnClaim over the turn rows of an {@see InMemoryConversationStore}:
 * the same compare-and-set rules as the Eloquent claim, no database.
 * Not final: a test may override one transition to simulate a racing worker.
 */
class InMemoryTurnClaim implements TurnClaim
{
    public function __construct(
        private readonly InMemoryConversationStore $store,
    ) {}

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
        return $this->find($turnUlid)?->status === Turn::STATUS_CANCELLED;
    }

    public function complete(string $turnUlid, array $usage): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_RUNNING], ['status' => Turn::STATUS_COMPLETED, 'usage' => $usage]);
    }

    public function fail(string $turnUlid, string $error, array $usage): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_RUNNING], [
            'status' => Turn::STATUS_FAILED,
            'error' => $error,
            'usage' => $usage,
        ]);
    }

    public function recordUsage(string $turnUlid, array $usage): void
    {
        $this->find($turnUlid)?->forceFill(['usage' => $usage, 'updated_at' => Carbon::now()->toDateTimeString()]);
    }

    public function failUnclaimed(string $turnUlid, string $error): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_QUEUED], ['status' => Turn::STATUS_FAILED, 'error' => $error]);
    }

    public function cancel(string $turnUlid): bool
    {
        return $this->finish($turnUlid, [Turn::STATUS_QUEUED, Turn::STATUS_RUNNING], ['status' => Turn::STATUS_CANCELLED]);
    }

    public function failStaleQueued(DateTimeInterface $createdBefore, string $error, DateTimeInterface $now): array
    {
        return $this->failStale(
            static fn (Turn $t): bool => $t->status === Turn::STATUS_QUEUED && $t->created_at->lt($createdBefore),
            $error,
            $now,
        );
    }

    public function failStaleRunning(DateTimeInterface $claimedBefore, string $error, DateTimeInterface $now): array
    {
        return $this->failStale(
            static fn (Turn $t): bool => $t->status === Turn::STATUS_RUNNING
                && $t->claimed_at !== null
                && $t->claimed_at->lt($claimedBefore),
            $error,
            $now,
        );
    }

    /**
     * @param  callable(Turn): bool  $isStale
     * @return list<string>
     */
    private function failStale(callable $isStale, string $error, DateTimeInterface $now): array
    {
        $flipped = [];
        foreach ($this->store->turns as $turn) {
            if ($isStale($turn)) {
                $turn->forceFill(['status' => Turn::STATUS_FAILED, 'error' => $error, 'finished_at' => $now, 'updated_at' => $now]);
                $flipped[] = $turn->ulid;
            }
        }

        return $flipped;
    }

    /**
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
        $turn = $this->find($turnUlid);
        if ($turn === null || ! in_array($turn->status, $from, true)) {
            return false;
        }
        $turn->forceFill($values);

        return true;
    }

    private function find(string $turnUlid): ?Turn
    {
        foreach ($this->store->turns as $turn) {
            if ($turn->ulid === $turnUlid) {
                return $turn;
            }
        }

        return null;
    }
}
