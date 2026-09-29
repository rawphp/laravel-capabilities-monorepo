<?php

declare(strict_types=1);

// InMemoryIdempotencyStore expiry handling.

use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;

it('InMemoryIdempotencyStore treats expired rows as missing and updates live ones', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-06-01T12:00:00Z'));
    $store = new InMemoryIdempotencyStore($clock);
    $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'c',
        'idempotency_key' => 'k',
        'status' => 'completed',
        'expires_at' => '2026-01-01T00:00:00Z',
    ]);
    expect($store->find('t', 'user', '1', 'c', 'k'))->toBeNull();
    expect($store->update('t', 'user', '1', 'c', 'missing', ['status' => 'x']))->toBeNull();

    $store->put([
        'tenant_id' => 7,
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'c',
        'idempotency_key' => 'k2',
        'status' => 'processing',
        'expires_at' => 'not-date',
    ]);
    expect($store->find('7', 'user', '1', 'c', 'k2'))->not->toBeNull();
    $u = $store->update('7', 'user', '1', 'c', 'k2', ['status' => 'completed']);
    expect($u['status'])->toBe('completed');
});
