<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Closure;
use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
 *
 * Accept state machine (claim + core D-005 idempotency; no local outcome cache):
 * - pending → accepting (atomic claim) before bus invoke
 * - accepting → accepted on success / idempotent replay
 * - accepting → failed on hard non-retryable bus error (or missing target)
 * - accepting stays accepting on retryable bus error (resume under same key)
 * - accepting stays accepting on approval_required (governance pending; resume after approve)
 *
 * Crash after successful bus invoke but before accepted: re-invoke with
 * idempotency_key=proposal:{ulid}. Accept **requires** a live core IdempotencyStore
 * readiness probe (fail closed). Resume is not a second safety system — it is D-005.
 *
 * Reject state machine:
 * - pending → rejected (atomic claim only)
 * - rejected re-entry is idempotent
 * - accepting / accepted / failed / expired refuse
 */
final class ProposalService
{
    /**
     * @param  ?Closure(): bool  $idempotencyStoreReady  Live probe at accept time.
     *                                                   null / false = fail closed. Not a constructor stamp.
     */
    public function __construct(
        private readonly CapabilityBus $bus,
        private readonly ?Closure $idempotencyStoreReady = null,
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
            Proposal::STATUS_REJECTED => throw new RuntimeException(
                "Proposal {$proposalUlid} is rejected"
            ),
            Proposal::STATUS_EXPIRED => throw new RuntimeException(
                "Proposal {$proposalUlid} is expired"
            ),
            default => throw new RuntimeException(
                "Proposal {$proposalUlid} has unknown status ({$proposal->status})"
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

        return $this->handleNonOkAcceptResult($proposal, $result);
    }

    private function handleNonOkAcceptResult(Proposal $proposal, CapabilityResult $result): never
    {
        $code = $result->errorCode() ?? 'failed';
        $message = is_array($result->error) ? (string) ($result->error['message'] ?? $code) : $code;

        // Core governance: approval_required is not a hard deny. Keep accepting so
        // hosts can approve then re-accept under the same D-005 key.
        if ($result->isApprovalRequired()) {
            $approvalId = $result->approvalId();
            $suffix = $approvalId !== null ? " approval_id={$approvalId}" : '';
            throw new RuntimeException(
                "Proposal {$proposal->ulid} bus invoke requires approval (code={$code}): {$message}{$suffix}"
            );
        }

        // Transient / retryable: leave accepting for resume under the same key.
        if ($result->isRetryable()) {
            throw new RuntimeException(
                "Proposal {$proposal->ulid} bus invoke failed (code={$code}): {$message}"
            );
        }

        // Hard failure (forbidden, validation, domain_error, …): terminal failed.
        $this->markFailed($proposal, $code, $message);

        throw new RuntimeException(
            "Proposal {$proposal->ulid} bus invoke failed (code={$code}): {$message}"
        );
    }

    private function requireIdempotencyStoreReady(): void
    {
        $ready = $this->idempotencyStoreReady !== null && (bool) ($this->idempotencyStoreReady)();
        if (! $ready) {
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
