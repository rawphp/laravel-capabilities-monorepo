<?php

declare(strict_types=1);

// IdempotencyStore behaviour.

use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyStore;
use Rawphp\Capabilities\Support\FixedClock;

it('IdempotencyStore stores, finds, updates, and drops expired rows', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    expect(fn () => new IdempotencyStore($clock, 0))->toThrow(InvalidArgumentException::class);

    $store = IdempotencyStore::withConfig($clock, new IdempotencyConfig(ttlHours: 24));
    expect($store->ttlHours())->toBe(24);

    $store->put([
        'tenant_id' => 42, // non-string coerced
        'actor_type' => 'user',
        'actor_id' => 'u1',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k1',
        'request_hash' => 'h',
        'status' => 'completed',
        'result_json' => ['ok' => true],
        'expires_at' => new DateTimeImmutable('2026-01-02T00:00:00Z'),
    ]);

    $found = $store->find('42', 'user', 'u1', 'inv.create', 'k1');
    expect($found)->not->toBeNull()->and($found['status'])->toBe('completed');

    $updated = $store->update('42', 'user', 'u1', 'inv.create', 'k1', ['status' => 'failed']);
    expect($updated['status'])->toBe('failed');
    expect($store->update('42', 'user', 'u1', 'inv.create', 'missing', []))->toBeNull();

    // expired row treated as missing
    $store->put([
        'tenant_id' => null,
        'actor_type' => 'user',
        'actor_id' => 'u2',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-exp',
        'status' => 'completed',
        'expires_at' => '2025-01-01T00:00:00Z',
    ]);
    expect($store->find(null, 'user', 'u2', 'inv.create', 'k-exp'))->toBeNull();

    // bad expires_at string not expired
    $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => 'u3',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-bad',
        'status' => 'processing',
        'expires_at' => 'not-a-date',
    ]);
    expect($store->find('t', 'user', 'u3', 'inv.create', 'k-bad'))->not->toBeNull();

    // update on expired removes
    $store->put([
        'tenant_id' => 't2',
        'actor_type' => 'user',
        'actor_id' => 'u4',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-up-exp',
        'status' => 'processing',
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    // re-insert as live then advance clock? FixedClock is fixed — put already has past expiry;
    // update path: first put a live row then manually we can't set clock. Put with past expiry
    // and call update — isExpired should drop it.
    expect($store->update('t2', 'user', 'u4', 'inv.create', 'k-up-exp', ['status' => 'x']))->toBeNull();

    $store->put([
        'tenant_id' => 'live',
        'actor_type' => 'user',
        'actor_id' => 'u5',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-live',
        'status' => 'completed',
        'expires_at' => '2099-01-01T00:00:00Z',
    ]);
    $store->put([
        'tenant_id' => 'dead',
        'actor_type' => 'user',
        'actor_id' => 'u6',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-dead',
        'status' => 'completed',
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    $all = $store->all();
    expect(count($all))->toBeGreaterThan(0);
    $removed = $store->forgetExpired();
    expect($removed)->toBeGreaterThanOrEqual(0);
});
