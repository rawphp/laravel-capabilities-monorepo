<?php

// REQ-031: Database ApprovalStore. Unit-only via ArrayTableGateway.

declare(strict_types=1);

use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Support\FixedClock;

it('put and find round-trip approval records', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);

    $row = $store->put([
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '9',
        'original_caller' => 'agent',
        'input_json' => ['amount' => 10],
    ]);

    expect($row['id'])->not->toBe('')
        ->and($store->find($row['id']))->toMatchArray([
            'capability_name' => 'create-invoice',
            'status' => 'pending',
            'tenant_id' => 't1',
        ]);
});

it('compareAndUpdate only mutates when status matches', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $row = $store->put(['capability_name' => 'x', 'status' => 'pending', 'requester_actor_type' => 'user', 'requester_actor_id' => '1', 'original_caller' => 'http']);

    $ok = $store->compareAndUpdate($row['id'], 'pending', ['status' => 'approved', 'decided_by' => 'boss']);
    $fail = $store->compareAndUpdate($row['id'], 'pending', ['status' => 'rejected']);

    expect($ok)->not->toBeNull()
        ->and($ok['status'])->toBe('approved')
        ->and($fail)->toBeNull()
        ->and($store->find($row['id'])['status'])->toBe('approved');
});

it('claimLease returns null when lease still held', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $row = $store->put([
        'capability_name' => 'x',
        'status' => 'approved',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
        'execution_lease_until' => '2026-07-27T12:05:00Z',
    ]);

    $claimed = $store->claimLease($row['id'], 'approved', '2026-07-27T12:01:00Z', [
        'execution_lease_until' => '2026-07-27T12:10:00Z',
        'execution_attempt' => 1,
    ]);

    expect($claimed)->toBeNull();
});

it('claimLease succeeds when lease expired', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $row = $store->put([
        'capability_name' => 'x',
        'status' => 'approved',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
        'execution_lease_until' => '2026-07-27T11:00:00Z',
    ]);

    $claimed = $store->claimLease($row['id'], 'approved', '2026-07-27T12:00:00Z', [
        'execution_lease_until' => '2026-07-27T12:05:00Z',
        'execution_attempt' => 1,
    ]);

    expect($claimed)->not->toBeNull()
        ->and($claimed['execution_attempt'])->toBe(1);
});

it('findByStatus returns matching rows', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $store->put(['capability_name' => 'a', 'status' => 'pending', 'requester_actor_type' => 'user', 'requester_actor_id' => '1', 'original_caller' => 'http']);
    $store->put(['capability_name' => 'b', 'status' => 'approved', 'requester_actor_type' => 'user', 'requester_actor_id' => '1', 'original_caller' => 'http']);

    $pending = $store->findByStatus('pending');
    expect($pending)->toHaveCount(1)
        ->and($pending[0]['capability_name'])->toBe('a');
});

it('implements ApprovalStore contract', function () {
    $store = new DatabaseApprovalStore(new ArrayTableGateway, new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z')));
    expect($store)->toBeInstanceOf(\Rawphp\Capabilities\Contracts\ApprovalStore::class);
});
