<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;

it('InMemoryApprovalStore implements ApprovalStore and records a pending approval', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00+00:00'));
    $store = new InMemoryApprovalStore($clock);

    expect($store)->toBeInstanceOf(ApprovalStore::class);

    $record = $store->put([
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'tenant_id' => 'tenant-1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '42',
        'original_caller' => 'http',
        'input_json' => ['amount_cents' => 2500],
        'input_hash' => 'abc123',
        'idempotency_key' => 'key-1',
        'expires_at' => '2026-01-16T12:00:00+00:00',
    ]);

    expect($record)->toHaveKey('id')
        ->and($record['status'])->toBe('pending')
        ->and($record['capability_name'])->toBe('create-invoice');

    $found = $store->find($record['id']);

    expect($found)->not->toBeNull()
        ->and($found['id'])->toBe($record['id'])
        ->and($found['status'])->toBe('pending')
        ->and($found['tenant_id'])->toBe('tenant-1')
        ->and($found['idempotency_key'])->toBe('key-1');
});

it('InMemoryApprovalStore requires a Clock and fails loudly without it', function () {
    expect(fn () => new InMemoryApprovalStore)
        ->toThrow(ArgumentCountError::class);
});

it('InMemoryApprovalStore keeps a caller-supplied id, updates by compare-and-swap, and misses return null', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-05-03T00:00:00Z'));
    $store = new InMemoryApprovalStore($clock);
    $row = $store->put([
        'id' => 'custom-id',
        'capability_name' => 'c',
        'status' => 'pending',
        'tenant_id' => 't',
    ]);
    expect($row['id'])->toBe('custom-id');
    expect($store->find('custom-id'))->not->toBeNull();
    expect($store->findByStatus('pending'))->not->toBeEmpty();
    $store->compareAndUpdate('custom-id', 'pending', ['status' => 'rejected']);
    expect($store->find('custom-id')['status'])->toBe('rejected');
    // miss paths
    expect($store->compareAndUpdate('nope', 'pending', []))->toBeNull();
    expect($store->claimLease('nope', 'pending', $clock->now()->format(DATE_ATOM), []))->toBeNull();
});
