<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Rawphp\CapabilitiesAi\Models\Proposal;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
 *
 * Accept claim: pending|accepting → accepting, then map bus result to AcceptOutcome.
 * Stuck `accepting` is intentional limbo (approval_required / retryable / crash mid-accept);
 * the package does not auto-expire or reclaim — host must re-drive accept (or resume).
 */
final class ProposalService
{
    public function __construct(
        private readonly CapabilityBus $bus,
        private readonly IdempotencyReadiness $idempotency,
    ) {}

    public function accept(string $proposalUlid): AcceptOutcome
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        if ($proposal->status === Proposal::STATUS_ACCEPTED) {
            return AcceptOutcome::accepted($proposal);
        }

        if (! in_array($proposal->status, [Proposal::STATUS_PENDING, Proposal::STATUS_ACCEPTING], true)) {
            throw new RuntimeException(
                "Proposal {$proposalUlid} is not pending/accepting (status={$proposal->status})"
            );
        }

        // Live accept-time readiness (not frozen at construct)
        if (! $this->idempotency->isReady()) {
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

        // Claim: pending → accepting (accepting re-drive keeps status)
        if ($proposal->status === Proposal::STATUS_PENDING) {
            $proposal->status = Proposal::STATUS_ACCEPTING;
            $proposal->save();
            $proposal = $proposal->refresh();
        }

        $target = (string) ($proposal->target_capability ?? '');
        if ($target === '') {
            $proposal->status = Proposal::STATUS_FAILED;
            $proposal->save();

            return AcceptOutcome::refuse(
                $proposal->refresh(),
                message: "Proposal {$proposalUlid} missing target_capability",
                httpStatus: 422,
                error: [
                    'code' => 'validation_failed',
                    'message' => "Proposal {$proposalUlid} missing target_capability",
                    'retryable' => false,
                ],
            );
        }

        $payload = is_array($proposal->payload) ? $proposal->payload : [];
        $result = $this->bus->invoke($target, $payload);

        return $this->mapBusResult($proposal, $result);
    }

    private function mapBusResult(Proposal $proposal, CapabilityResult $result): AcceptOutcome
    {
        if ($result->isOk()) {
            $proposal->status = Proposal::STATUS_ACCEPTED;
            $proposal->accepted_at = Carbon::now();
            $proposal->save();

            return AcceptOutcome::accepted($proposal->refresh());
        }

        if ($result->isApprovalRequired()) {
            // Stay accepting — host re-drive / approval resume (D-005)
            return AcceptOutcome::approvalRequired(
                $proposal->refresh(),
                approvalId: $result->approvalId(),
                message: is_string($result->error['message'] ?? null)
                    ? (string) $result->error['message']
                    : null,
                error: $result->error,
            );
        }

        $error = $result->error ?? ['code' => 'domain_error', 'message' => 'Accept failed'];
        $code = (string) ($error['code'] ?? 'domain_error');
        $message = is_string($error['message'] ?? null) ? (string) $error['message'] : 'Accept failed';
        $retryable = (bool) ($error['retryable'] ?? false);
        $http = isset($error['http_status']) ? (int) $error['http_status'] : null;

        // Hard refuse: forbidden / capability not in profile / not runnable
        if (in_array($code, ['forbidden', 'capability_not_in_profile', 'not_runnable', 'unauthenticated'], true)) {
            $proposal->status = Proposal::STATUS_FAILED;
            $proposal->save();

            return AcceptOutcome::refuse(
                $proposal->refresh(),
                message: $message,
                httpStatus: $http ?? 403,
                error: $error,
            );
        }

        if ($retryable) {
            // Stay accepting for host re-drive
            return AcceptOutcome::retryable(
                $proposal->refresh(),
                message: $message,
                httpStatus: $http ?? 409,
                error: $error,
            );
        }

        $proposal->status = Proposal::STATUS_FAILED;
        $proposal->save();

        return AcceptOutcome::failed(
            $proposal->refresh(),
            message: $message,
            httpStatus: $http ?? 422,
            error: $error,
        );
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
}
