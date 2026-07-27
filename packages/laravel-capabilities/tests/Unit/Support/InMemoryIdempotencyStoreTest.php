<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;

it('InMemoryIdempotencyStore implements IdempotencyStore and records a completed outcome', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00+00:00'));
    $store = new InMemoryIdempotencyStore($clock);

    expect($store)->toBeInstanceOf(IdempotencyStore::class);

    $record = $store->put([
        'tenant_id' => 'tenant-1',
        'actor_type' => 'user',
        'actor_id' => '42',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'idem-001',
        'request_hash' => 'hash-a',
        'status' => 'completed',
        'result_json' => ['ok' => true, 'invoice_id' => 7],
        'approval_id' => null,
        'expires_at' => '2026-01-16T12:00:00+00:00',
    ]);

    expect($record['status'])->toBe('completed')
        ->and($record['idempotency_key'])->toBe('idem-001');

    $found = $store->find(
        tenantId: 'tenant-1',
        actorType: 'user',
        actorId: '42',
        capabilityName: 'create-invoice',
        key: 'idem-001',
    );

    expect($found)->not->toBeNull()
        ->and($found['status'])->toBe('completed')
        ->and($found['result_json'])->toBe(['ok' => true, 'invoice_id' => 7])
        ->and($found['request_hash'])->toBe('hash-a');
});

it('InMemoryIdempotencyStore requires a Clock and fails loudly without it', function () {
    expect(fn () => new InMemoryIdempotencyStore())
        ->toThrow(ArgumentCountError::class);
});
