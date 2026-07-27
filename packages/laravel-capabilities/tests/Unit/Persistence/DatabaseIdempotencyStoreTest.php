<?php

// REQ-032: Database IdempotencyStore. Unit-only via ArrayTableGateway.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Support\FixedClock;

it('put and find by composite identity', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->put([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '7',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'k1',
        'status' => 'completed',
        'result_json' => ['ok' => true],
    ]);

    $found = $store->find('t1', 'user', '7', 'create-invoice', 'k1');
    expect($found)->not->toBeNull()
        ->and($found['status'])->toBe('completed')
        ->and($found['tenant_id'])->toBe('t1')
        ->and($store->find('t2', 'user', '7', 'create-invoice', 'k1'))->toBeNull();
});

it('treats expired rows as missing', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->put([
        'tenant_id' => null,
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'status' => 'completed',
        'expires_at' => '2026-07-27T11:00:00Z',
    ]);

    expect($store->find(null, 'user', '1', 'x', 'k'))->toBeNull();
});

it('put replaces same identity', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'status' => 'processing',
    ]);
    $second = $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'status' => 'completed',
        'result_json' => ['n' => 1],
    ]);

    expect($second['status'])->toBe('completed')
        ->and($store->find('t', 'user', '1', 'x', 'k')['result_json'])->toBe(['n' => 1]);
});

it('null tenant does not collide with other tenants', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->put([
        'tenant_id' => null,
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'same',
        'status' => 'completed',
        'result_json' => ['from' => 'null'],
    ]);
    $store->put([
        'tenant_id' => 'other',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'same',
        'status' => 'completed',
        'result_json' => ['from' => 'other'],
    ]);

    expect($store->find(null, 'user', '1', 'x', 'same')['result_json'])->toBe(['from' => 'null'])
        ->and($store->find('other', 'user', '1', 'x', 'same')['result_json'])->toBe(['from' => 'other']);
});

it('implements IdempotencyStore contract', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    expect($store)->toBeInstanceOf(IdempotencyStore::class);
});
