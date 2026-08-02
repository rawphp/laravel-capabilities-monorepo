<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use Illuminate\Support\Carbon;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesAi\Models\Proposal;
use RuntimeException;

/**
 * Accept/reject proposals — side effects only via capability bus.
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

        if ($proposal->status !== Proposal::STATUS_PENDING) {
            throw new RuntimeException("Proposal {$proposalUlid} is not pending (status={$proposal->status})");
        }

        $target = (string) ($proposal->target_capability ?? '');
        if ($target === '') {
            throw new RuntimeException("Proposal {$proposalUlid} missing target_capability");
        }

        $payload = is_array($proposal->payload) ? $proposal->payload : [];
        $this->bus->invoke($target, $payload);

        $proposal->status = Proposal::STATUS_ACCEPTED;
        $proposal->accepted_at = Carbon::now();
        $proposal->save();

        return $proposal->refresh();
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
