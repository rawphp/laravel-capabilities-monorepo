<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
 *
 * Accept state machine:
 * - pending → accepting (atomic claim) before bus invoke
 * - accepting → accepted on success (outcome recorded for local crash resume)
 * - accepting → failed on non-retryable bus error (or missing target)
 * - accepting stays accepting on retryable bus error (resume under same idempotency key)
 *
 * Crash after successful bus invoke but before accepted: resume reads accept_outcome
 * and marks accepted without a second side effect (local, not “hope the store is on”).
 * Concurrent workers still need the core idempotency store; accept fails closed when
 * that store is not configured (D-005 spirit).
 */
final class ProposalService
{
    /**
     * @param  bool  $idempotencyStoreReady  Fail closed when false — proposal accept requires a real core idempotency store for concurrent resume safety.
     */
    public function __construct(
        private readonly CapabilityBus $bus,
        private readonly bool $idempotencyStoreReady = true,
    ) {}

    public function accept(string $proposalUlid): Proposal
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        if ($proposal->status === Proposal::STATUS_ACCEPTED) {
            // Idempotent re-accept: no second bus invoke
            return $proposal;
        }

        if ($proposal->status === Proposal::STATUS_FAILED) {
            throw new RuntimeException(
                "Proposal {$proposalUlid} accept already failed".
                ($proposal->last_error ? " ({$proposal->last_error})" : '')
            );
        }

        if ($proposal->status === Proposal::STATUS_ACCEPTING) {
            return $this->executeAccept($proposal);
        }

        if ($proposal->status !== Proposal::STATUS_PENDING) {
            throw new RuntimeException("Proposal {$proposalUlid} is not pending (status={$proposal->status})");
        }

        if (! $this->idempotencyStoreReady) {
            throw new RuntimeException(
                'Proposal accept requires capabilities.idempotency store (fail closed; D-005)'
            );
        }

        $claimed = DatabaseConnection::resolve()->table($proposal->getTable())
            ->where('ulid', $proposalUlid)
            ->where('status', Proposal::STATUS_PENDING)
            ->update([
                'status' => Proposal::STATUS_ACCEPTING,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

        if ($claimed !== 1) {
            $fresh = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();
            if ($fresh->status === Proposal::STATUS_ACCEPTED) {
                return $fresh;
            }
            if ($fresh->status === Proposal::STATUS_ACCEPTING) {
                return $this->executeAccept($fresh);
            }
            if ($fresh->status === Proposal::STATUS_FAILED) {
                throw new RuntimeException(
                    "Proposal {$proposalUlid} accept already failed".
                    ($fresh->last_error ? " ({$fresh->last_error})" : '')
                );
            }
            throw new RuntimeException("Proposal {$proposalUlid} is not pending (status={$fresh->status})");
        }

        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        return $this->executeAccept($proposal);
    }

    public function reject(string $proposalUlid): Proposal
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        if ($proposal->status === Proposal::STATUS_REJECTED) {
            return $proposal;
        }

        $proposal->status = Proposal::STATUS_REJECTED;
        $proposal->save();

        return $proposal->refresh();
    }

    private function executeAccept(Proposal $proposal): Proposal
    {
        // Local crash resume: outcome already recorded — mark accepted without re-invoke.
        if ($this->hasSuccessfulOutcome($proposal)) {
            return $this->markAccepted($proposal);
        }

        if (! $this->idempotencyStoreReady) {
            throw new RuntimeException(
                'Proposal accept requires capabilities.idempotency store (fail closed; D-005)'
            );
        }

        $target = (string) ($proposal->target_capability ?? '');
        if ($target === '') {
            $this->markFailed($proposal, 'not_configured', 'missing target_capability');
            throw new RuntimeException("Proposal {$proposal->ulid} missing target_capability");
        }

        $payload = is_array($proposal->payload) ? $proposal->payload : [];

        $result = $this->bus->invoke($target, $payload, [
            'idempotency_key' => 'proposal:'.$proposal->ulid,
        ]);

        if ($result->ok) {
            // Phase 1: record outcome while still accepting so a crash before accepted
            // is resume-local (no second side effect even without replaying the bus).
            $proposal->accept_outcome = $result->toArray();
            $proposal->last_error = null;
            $proposal->save();

            // Phase 2: terminal accepted
            return $this->markAccepted($proposal->refresh());
        }

        $code = $result->errorCode() ?? 'failed';
        $message = is_array($result->error) ? (string) ($result->error['message'] ?? $code) : $code;
        $retryable = is_array($result->error) && array_key_exists('retryable', $result->error)
            ? (bool) $result->error['retryable']
            : false;

        if (! $retryable) {
            $this->markFailed($proposal, $code, $message);
        }
        // Retryable: leave status=accepting for resume under the same idempotency key.

        throw new RuntimeException(
            "Proposal {$proposal->ulid} bus invoke failed (code={$code}): {$message}"
        );
    }

    private function hasSuccessfulOutcome(Proposal $proposal): bool
    {
        $outcome = $proposal->accept_outcome;

        return is_array($outcome) && ($outcome['ok'] ?? false) === true;
    }

    private function markAccepted(Proposal $proposal): Proposal
    {
        $proposal->status = Proposal::STATUS_ACCEPTED;
        $proposal->accepted_at = Carbon::now();
        $proposal->last_error = null;
        $proposal->save();

        return $proposal->refresh();
    }

    private function markFailed(Proposal $proposal, string $code, string $message): void
    {
        $proposal->status = Proposal::STATUS_FAILED;
        $proposal->last_error = "{$code}: {$message}";
        $proposal->accept_outcome = CapabilityResult::failure($code, $message)->toArray();
        $proposal->save();
    }
}
