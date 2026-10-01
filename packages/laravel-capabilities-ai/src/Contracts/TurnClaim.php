<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

use DateTimeInterface;
use Rawphp\CapabilitiesAi\Support\EloquentTurnClaim;

/**
 * Turn-row status transitions, each an atomic compare-and-set: a transition only
 * happens from the stated status, so a racing worker, cancel, or reaper that moved
 * the turn first wins and the losing call reports false (or leaves the ulid out).
 *
 * Production: {@see EloquentTurnClaim}. Unit tests bind an in-memory fake.
 */
interface TurnClaim
{
    /**
     * queued → running, stamped with $owner.
     *
     * @return bool false when the turn is missing or already left queued (claim lost)
     */
    public function claim(string $turnUlid, string $owner): bool;

    /**
     * True only while this worker still owns a running turn.
     * A reaper that marked it failed, or a cancel, makes this false.
     */
    public function isRunning(string $turnUlid): bool;

    /**
     * running → completed.
     *
     * @param  list<array<string, int>>  $usage
     * @return bool false when the turn already left running (cancelled, reaped)
     */
    public function complete(string $turnUlid, array $usage): bool;

    /**
     * running → failed.
     *
     * @param  list<array<string, int>>  $usage
     * @return bool false when the turn already left running (cancelled, reaped)
     */
    public function fail(string $turnUlid, string $error, array $usage): bool;

    /**
     * Keep the rounds' accounting on a turn that ended under the runner (e.g. cancelled).
     *
     * @param  list<array<string, int>>  $usage
     */
    public function recordUsage(string $turnUlid, array $usage): void;

    /**
     * queued → failed, for a turn that was never claimed.
     *
     * @return bool false when the turn is missing or already left queued
     */
    public function failUnclaimed(string $turnUlid, string $error): bool;

    /**
     * queued|running → cancelled.
     *
     * @return bool false when the turn is missing or already terminal
     */
    public function cancel(string $turnUlid): bool;

    /**
     * queued turns created before $createdBefore → failed, each with its own guarded update.
     *
     * @return list<string> ulids this call flipped (a turn claimed in between is left alone)
     */
    public function failStaleQueued(DateTimeInterface $createdBefore, string $error, DateTimeInterface $now): array;

    /**
     * running turns claimed before $claimedBefore → failed, each with its own guarded update.
     *
     * @return list<string> ulids this call flipped (a turn finished in between is left alone)
     */
    public function failStaleRunning(DateTimeInterface $claimedBefore, string $error, DateTimeInterface $now): array;
}
