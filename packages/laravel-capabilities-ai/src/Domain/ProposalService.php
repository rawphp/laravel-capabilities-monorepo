<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
 *
 * Accept state machine (claim + core D-005 idempotency; no local outcome cache):
 * - pending → accepting (atomic claim) before bus invoke
 * - accepting → accepted on success (single terminal write)
 * - accepting → failed on non-retryable bus error (or missing target)
 * - accepting stays accepting on retryable bus error (resume under same key)
 *
 * Crash after successful bus invoke but before accepted: re-invoke with
 * idempotency_key=proposal:{ulid}. Accept **requires** a real core IdempotencyStore
 * (fail closed). Resume is not a second safety system — it is D-005.
 *
 * Reject state machine:
 * - pending → rejected (atomic claim only)
 * - rejected re-entry is idempotent
 * - accepting / accepted / failed / expired refuse
 */
final class ProposalService
{
    /**
     * @param  bool  $idempotencyStoreReady  Must be proven true (container-bound core store). Default false = fail closed.
     */
    public function __construct(
        private readonly CapabilityBus $bus,
        private readonly bool $idempotencyStoreReady = false,
    ) {}

    public function accept(string $proposalUlid): Proposal
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        return match ($proposal->status) {
            Proposal::STATUS_ACCEPTED => $proposal,
            Proposal::STATUS_FAILED => throw new RuntimeException(
                "Proposal {$proposalUlid} accept already failed".
                ($proposal->last_error ? " ({$proposal->last_error})" : '')
            ),
            Proposal::STATUS_ACCEPTING => $this->executeAccept($proposal),
            Proposal::STATUS_PENDING => $this->claimPendingThenAccept($proposalUlid, $proposal),
            default => throw new RuntimeException(
                "Proposal {$proposalUlid} is not pending (status={$proposal->status})"
            ),
        };
    }

    public function reject(string $proposalUlid): Proposal
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        if ($proposal->status === Proposal::STATUS_REJECTED) {
            return $proposal;
        }

        if ($proposal->status !== Proposal::STATUS_PENDING) {
            throw new RuntimeException(
                "Proposal {$proposalUlid} cannot be rejected (status={$proposal->status})"
            );
        }

        $claimed = DatabaseConnection::resolve()->table($proposal->getTable())
            ->where('ulid', $proposalUlid)
            ->where('status', Proposal::STATUS_PENDING)
            ->update([
                'status' => Proposal::STATUS_REJECTED,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

        if ($claimed !== 1) {
            $fresh = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();
            if ($fresh->status === Proposal::STATUS_REJECTED) {
                return $fresh;
            }

            throw new RuntimeException(
                "Proposal {$proposalUlid} cannot be rejected (status={$fresh->status})"
            );
        }

        return Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();
    }

    private function claimPendingThenAccept(string $proposalUlid, Proposal $proposal): Proposal
    {
        $this->requireIdempotencyStoreReady();

        $claimed = DatabaseConnection::resolve()->table($proposal->getTable())
            ->where('ulid', $proposalUlid)
            ->where('status', Proposal::STATUS_PENDING)
            ->update([
                'status' => Proposal::STATUS_ACCEPTING,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

        if ($claimed !== 1) {
            // Lost race: re-enter single status path (no duplicated policy).
            return $this->accept($proposalUlid);
        }

        $claimedProposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        return $this->executeAccept($claimedProposal);
    }

    private function executeAccept(Proposal $proposal): Proposal
    {
        $this->requireIdempotencyStoreReady();

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
            return $this->markAccepted($proposal);
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

    private function requireIdempotencyStoreReady(): void
    {
        if (! $this->idempotencyStoreReady) {
            throw new RuntimeException(
                'Proposal accept requires a bound core IdempotencyStore (fail closed; D-005)'
            );
        }
    }

    private function markAccepted(Proposal $proposal): Proposal
    {
        $updated = DatabaseConnection::resolve()->table($proposal->getTable())
            ->where('ulid', $proposal->ulid)
            ->where('status', Proposal::STATUS_ACCEPTING)
            ->update([
                'status' => Proposal::STATUS_ACCEPTED,
                'accepted_at' => Carbon::now()->toDateTimeString(),
                'last_error' => null,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

        if ($updated !== 1) {
            $fresh = Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail();
            if ($fresh->status === Proposal::STATUS_ACCEPTED) {
                return $fresh;
            }

            throw new RuntimeException(
                "Proposal {$proposal->ulid} lost accept claim (status={$fresh->status})"
            );
        }

        return Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail();
    }

    private function markFailed(Proposal $proposal, string $code, string $message): void
    {
        DatabaseConnection::resolve()->table($proposal->getTable())
            ->where('ulid', $proposal->ulid)
            ->where('status', Proposal::STATUS_ACCEPTING)
            ->update([
                'status' => Proposal::STATUS_FAILED,
                'last_error' => "{$code}: {$message}",
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
    }
}
