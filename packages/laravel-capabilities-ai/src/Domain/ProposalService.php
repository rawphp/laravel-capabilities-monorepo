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
 * Accept uses claim-style transition pending → accepting before bus invoke,
 * then accepting → accepted. Crash recovery resumes from accepting with a
 * stable bus idempotency_key (proposal:{ulid}) so re-accept does not double-fire
 * when the core store is configured (D-005 spirit).
 */
final class ProposalService
{
    public function __construct(
        private readonly CapabilityBus $bus,
    ) {}

    public function accept(string $proposalUlid): Proposal
    {
        $proposal = Proposal::query()->where('ulid', $proposalUlid)->firstOrFail();

        if ($proposal->status === Proposal::STATUS_ACCEPTED) {
            // Idempotent re-accept: no second bus invoke
            return $proposal;
        }

        if ($proposal->status === Proposal::STATUS_ACCEPTING) {
            // Crash resume: claim already held; re-invoke under stable idempotency key
            return $this->executeAccept($proposal);
        }

        if ($proposal->status !== Proposal::STATUS_PENDING) {
            throw new RuntimeException("Proposal {$proposalUlid} is not pending (status={$proposal->status})");
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
        $target = (string) ($proposal->target_capability ?? '');
        if ($target === '') {
            throw new RuntimeException("Proposal {$proposal->ulid} missing target_capability");
        }

        $payload = is_array($proposal->payload) ? $proposal->payload : [];

        $result = $this->bus->invoke($target, $payload, [
            'idempotency_key' => 'proposal:'.$proposal->ulid,
        ]);

        if (! $result->ok) {
            // Leave status=accepting so a retry resumes under the same idempotency key.
            $code = $result->errorCode() ?? 'failed';
            $message = is_array($result->error) ? (string) ($result->error['message'] ?? $code) : $code;
            throw new RuntimeException(
                "Proposal {$proposal->ulid} bus invoke failed (code={$code}): {$message}"
            );
        }

        $proposal->status = Proposal::STATUS_ACCEPTED;
        $proposal->accepted_at = Carbon::now();
        $proposal->save();

        return $proposal->refresh();
    }
}
