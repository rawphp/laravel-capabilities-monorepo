<?php

// L-013: a pending row whose execution lease is live is "in progress" — accept must not
// recurse into a hot loop and reject must not flip a row whose run() may have side-effected.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

function leaseHeldPending(array $opts = []): array
{
    $h = ApprovalHelpers::withPending($opts);
    $id = (string) $h['row']['id'];
    // Another worker is mid-run() (Shape B keeps status pending while executing).
    $h['store']->update($id, [
        'execution_lease_until' => $h['clock']->now()->modify('+60 seconds')->format(DATE_ATOM),
        'execution_attempt' => 1,
    ]);

    return [$h, $id];
}

it('atomic accept on a pending row with a live lease returns conflict in_progress without running [D-006]', function () {
    [$h, $id] = leaseHeldPending(['execution' => 'atomic']);

    $result = $h['manager']->accept($id, ApprovalHelpers::requester());

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error['in_progress'] ?? null)->toBeTrue()
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['store']->find($id)['status'])->toBe('pending');
});

it('deferred accept on a pending row with a live lease also reports in_progress [D-006]', function () {
    [$h, $id] = leaseHeldPending(['execution' => 'deferred']);

    $result = $h['manager']->accept($id, ApprovalHelpers::requester());

    expect($result->errorCode())->toBe('conflict')
        ->and($result->error['in_progress'] ?? null)->toBeTrue()
        ->and($h['runCount']->value)->toBe(0);
});

it('reject on a pending row with a live lease is refused and the row stays pending [D-006]', function () {
    [$h, $id] = leaseHeldPending(['execution' => 'atomic']);

    $result = $h['manager']->reject($id, ApprovalHelpers::requester(), 'no');

    expect($result->errorCode())->toBe('conflict')
        ->and($result->error['in_progress'] ?? null)->toBeTrue()
        ->and($h['store']->find($id)['status'])->toBe('pending')
        ->and($h['store']->find($id)['decision_reason'] ?? null)->toBeNull();
});

it('accept that loses the lease race settles from a single re-read instead of recursing [D-006]', function () {
    $clock = ApprovalHelpers::harness()['clock'];
    $inner = new InMemoryApprovalStore($clock);
    // claimLease loses once (a racing worker claimed between find() and the conditional update).
    $racing = new class($inner) implements ApprovalStore
    {
        public int $claims = 0;

        public function __construct(private InMemoryApprovalStore $inner) {}

        public function put(array $record): array
        {
            return $this->inner->put($record);
        }

        public function find(string $id): ?array
        {
            return $this->inner->find($id);
        }

        public function update(string $id, array $attributes): ?array
        {
            return $this->inner->update($id, $attributes);
        }

        public function compareAndUpdate(string $id, string $expectedStatus, array $attributes): ?array
        {
            return $this->inner->compareAndUpdate($id, $expectedStatus, $attributes);
        }

        public function claimLease(string $id, string $expectedStatus, string $nowIso, array $attributes): ?array
        {
            $this->claims++;

            return null;
        }

        public function findByStatus(string $status): array
        {
            return $this->inner->findByStatus($status);
        }
    };

    $h = ApprovalHelpers::withPending(['store' => $racing, 'clock' => $clock, 'execution' => 'atomic']);
    $id = (string) $h['row']['id'];

    $result = $h['manager']->accept($id, ApprovalHelpers::requester());

    expect($result)->toBeInstanceOf(CapabilityResult::class)
        ->and($result->errorCode())->toBe('conflict')
        ->and($racing->claims)->toBe(1)
        ->and($h['runCount']->value)->toBe(0);
});

it('reject that loses the race to an executed row reports the terminal state once [D-006]', function () {
    $h = ApprovalHelpers::withPending(['execution' => 'atomic']);
    $id = (string) $h['row']['id'];
    $h['manager']->accept($id, ApprovalHelpers::requester());

    $result = $h['manager']->reject($id, ApprovalHelpers::requester(), 'late');

    expect($result->errorCode())->toBe('conflict')
        ->and($h['store']->find($id)['status'])->toBe('executed')
        ->and($h['runCount']->value)->toBe(1);
});
