<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Models\Conversation;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\DatabaseConnection;
use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
 *
 * Accept state machine (strong fail-closed SM → AcceptOutcome wire results):
 * - pending → accepting (atomic CAS claim) before bus invoke
 * - accepting → accepted on success / idempotent replay (clear last_error)
 * - accepting → failed + last_error on hard non-retryable (or missing target)
 * - accepting stays accepting on isRetryable() / isApprovalRequired() (D-005 resume)
 * - Bus invoke always passes idempotency_key=proposal:{ulid} (D-005)
 * - Live IdempotencyReadiness probe (fail closed) — not a constructor stamp
 * - Rejected / expired / unknown status → AcceptOutcome::refuse (no throw-as-API)
 *
 * Reject state machine:
 * - pending → rejected (atomic CAS only)
 * - rejected re-entry is idempotent
 * - accepting / accepted / failed / expired refuse (RuntimeException for HTTP 409)
 */
final class ProposalService
{
    public function __construct(
        private readonly CapabilityBus $bus,
        private readonly IdempotencyReadiness $idempotency,
        private readonly ResolveConversationActor $actors = new ResolveConversationActor,
    ) {}

    public function accept(string $proposalUlid): AcceptOutcome
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        return match ($proposal->status) {
            Proposal::STATUS_ACCEPTED => AcceptOutcome::accepted($proposal),
            Proposal::STATUS_FAILED => AcceptOutcome::failed(
                $proposal,
                message: $proposal->last_error
                    ? "Proposal {$proposalUlid} accept already failed ({$proposal->last_error})"
                    : "Proposal {$proposalUlid} accept already failed",
                httpStatus: 422,
                error: [
                    'code' => 'domain_error',
                    'message' => $proposal->last_error ?? 'accept already failed',
                    'retryable' => false,
                ],
            ),
            Proposal::STATUS_ACCEPTING => $this->executeAccept($proposal),
            Proposal::STATUS_PENDING => $this->claimPendingThenAccept($proposalUlid, $proposal),
            Proposal::STATUS_REJECTED => AcceptOutcome::refuse(
                $proposal,
                message: "Proposal {$proposalUlid} is rejected",
                httpStatus: 409,
                error: [
                    'code' => 'conflict',
                    'message' => "Proposal {$proposalUlid} is rejected",
                    'retryable' => false,
                ],
            ),
            Proposal::STATUS_EXPIRED => AcceptOutcome::refuse(
                $proposal,
                message: "Proposal {$proposalUlid} is expired",
                httpStatus: 410,
                error: [
                    'code' => 'expired',
                    'message' => "Proposal {$proposalUlid} is expired",
                    'retryable' => false,
                ],
            ),
            default => AcceptOutcome::refuse(
                $proposal,
                message: "Proposal {$proposalUlid} has unknown status ({$proposal->status})",
                httpStatus: 409,
                error: [
                    'code' => 'conflict',
                    'message' => "Proposal {$proposalUlid} has unknown status ({$proposal->status})",
                    'retryable' => false,
                ],
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

        $claimed = $this->casStatus($proposalUlid, Proposal::STATUS_PENDING, [
            'status' => Proposal::STATUS_REJECTED,
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

    private function claimPendingThenAccept(string $proposalUlid, Proposal $proposal): AcceptOutcome
    {
        if (! $this->idempotency->isReady()) {
            return $this->idempotencyNotReady($proposal);
        }

        $claimed = $this->casStatus($proposalUlid, Proposal::STATUS_PENDING, [
            'status' => Proposal::STATUS_ACCEPTING,
        ]);

        if ($claimed !== 1) {
            // Lost race: re-enter single status path (no duplicated policy / double-invoke).
            return $this->accept($proposalUlid);
        }

        $claimedProposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        return $this->executeAccept($claimedProposal);
    }

    private function executeAccept(Proposal $proposal): AcceptOutcome
    {
        if (! $this->idempotency->isReady()) {
            return $this->idempotencyNotReady($proposal);
        }

        $target = (string) ($proposal->target_capability ?? '');
        if ($target === '') {
            $this->markFailed($proposal, 'not_configured', 'missing target_capability');

            return AcceptOutcome::refuse(
                Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail(),
                message: "Proposal {$proposal->ulid} missing target_capability",
                httpStatus: 422,
                error: [
                    'code' => 'validation_failed',
                    'message' => "Proposal {$proposal->ulid} missing target_capability",
                    'retryable' => false,
                ],
            );
        }

        $payload = is_array($proposal->payload) ? $proposal->payload : [];
        $conversation = Conversation::query()->findOrFail($proposal->conversation_id);
        // Same principal shape as TurnRunner tool invokes (caller=job + conversation user).
        $actor = $this->actors->resolve($conversation->user_id);
        $result = $this->bus->invoke(
            $target,
            $payload,
            $this->actors->invokeOptions($actor, [
                'idempotency_key' => 'proposal:'.$proposal->ulid,
            ]),
        );

        return $this->mapBusResult($proposal, $result);
    }

    private function mapBusResult(Proposal $proposal, CapabilityResult $result): AcceptOutcome
    {
        if ($result->isOk()) {
            return AcceptOutcome::accepted($this->markAccepted($proposal));
        }

        // Branch approval_required *before* isRetryable so governance stays resumeable.
        if ($result->isApprovalRequired()) {
            return AcceptOutcome::approvalRequired(
                Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail(),
                approvalId: $result->approvalId(),
                message: is_string($result->error['message'] ?? null)
                    ? (string) $result->error['message']
                    : null,
                error: $result->error,
            );
        }

        $code = $result->errorCode() ?? 'domain_error';
        $message = is_array($result->error)
            ? (string) ($result->error['message'] ?? $code)
            : $code;
        $error = $result->error ?? ['code' => $code, 'message' => $message];
        $http = isset($error['http_status']) ? (int) $error['http_status'] : null;

        // Hard refuse (auth/profile) → terminal failed + refuse wire shape
        if ($result->isHardRefuse()) {
            $this->markFailed($proposal, $code, $message);

            return AcceptOutcome::refuse(
                Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail(),
                message: $message,
                httpStatus: $http ?? 403,
                error: $error,
            );
        }

        // Primary policy: CapabilityResult::isRetryable() (not ad-hoc error array dig)
        if ($result->isRetryable()) {
            return AcceptOutcome::retryable(
                Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail(),
                message: $message,
                httpStatus: $http ?? 409,
                error: $error,
            );
        }

        $this->markFailed($proposal, $code, $message);

        return AcceptOutcome::failed(
            Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail(),
            message: $message,
            httpStatus: $http ?? 422,
            error: $error,
        );
    }

    private function markAccepted(Proposal $proposal): Proposal
    {
        $updated = $this->casStatus($proposal->ulid, Proposal::STATUS_ACCEPTING, [
            'status' => Proposal::STATUS_ACCEPTED,
            'accepted_at' => Carbon::now()->toDateTimeString(),
            'last_error' => null,
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
        $updated = $this->casStatus($proposal->ulid, Proposal::STATUS_ACCEPTING, [
            'status' => Proposal::STATUS_FAILED,
            'last_error' => "{$code}: {$message}",
        ]);

        if ($updated !== 1) {
            $fresh = Proposal::query()->where('ulid', $proposal->ulid)->firstOrFail();
            if ($fresh->status === Proposal::STATUS_FAILED) {
                return;
            }

            throw new RuntimeException(
                "Proposal {$proposal->ulid} lost fail claim (status={$fresh->status})"
            );
        }
    }

    private function idempotencyNotReady(Proposal $proposal): AcceptOutcome
    {
        return AcceptOutcome::failed(
            $proposal,
            message: 'Idempotency store not ready',
            httpStatus: 503,
            error: [
                'code' => 'not_configured',
                'message' => 'Idempotency store not ready',
                'retryable' => true,
            ],
        );
    }

    /**
     * Atomic status transition: UPDATE … WHERE ulid + status = $fromStatus.
     *
     * @param  array<string, mixed>  $attrs  Columns to set (status usually included)
     */
    private function casStatus(string $ulid, string $fromStatus, array $attrs): int
    {
        $payload = $attrs;
        $payload['updated_at'] = Carbon::now()->toDateTimeString();

        return (int) DatabaseConnection::resolve()->table((new Proposal)->getTable())
            ->where('ulid', $ulid)
            ->where('status', $fromStatus)
            ->update($payload);
    }
}
