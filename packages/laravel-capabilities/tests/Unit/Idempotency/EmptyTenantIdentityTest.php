<?php

// D-005 / D-003: a literal '' tenant must never share the null-tenant idempotency row. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Idempotency\IdempotencyStore as DomainIdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;

$drivers = [
    'domain' => fn (FixedClock $clock): IdempotencyStore => new DomainIdempotencyStore($clock),
    'memory' => fn (FixedClock $clock): IdempotencyStore => new InMemoryIdempotencyStore($clock),
    'database' => fn (FixedClock $clock): IdempotencyStore => new DatabaseIdempotencyStore(new ArrayTableGateway, $clock),
];

function emptyTenantStore(Closure $make): IdempotencyStore
{
    return $make(new FixedClock(new DateTimeImmutable('2026-09-24T12:00:00Z')));
}

foreach ($drivers as $driver => $make) {
    it("fail: {$driver} store rejects put with empty-string tenant [D-005]", function () use ($make) {
        $store = emptyTenantStore($make);

        expect(fn () => $store->put([
            'tenant_id' => '',
            'actor_type' => 'system',
            'actor_id' => 'scheduler',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
            'status' => 'completed',
        ]))->toThrow(InvalidArgumentException::class, 'tenant');
    });

    it("fail: {$driver} store rejects find with empty-string tenant so it cannot read the null-tenant row [D-005]", function () use ($make) {
        $store = emptyTenantStore($make);
        $store->put([
            'tenant_id' => null,
            'actor_type' => 'system',
            'actor_id' => 'scheduler',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
            'status' => 'completed',
            'result_json' => ['global' => true],
        ]);

        expect($store->find(null, 'system', 'scheduler', 'create-invoice', 'k1'))->not->toBeNull()
            ->and(fn () => $store->find('', 'system', 'scheduler', 'create-invoice', 'k1'))
            ->toThrow(InvalidArgumentException::class, 'tenant');
    });

    it("fail: {$driver} store rejects update with empty-string tenant so it cannot overwrite the null-tenant row [D-005]", function () use ($make) {
        $store = emptyTenantStore($make);
        $store->put([
            'tenant_id' => null,
            'actor_type' => 'system',
            'actor_id' => 'scheduler',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
            'status' => 'processing',
        ]);

        expect(fn () => $store->update('', 'system', 'scheduler', 'create-invoice', 'k1', ['status' => 'completed']))
            ->toThrow(InvalidArgumentException::class, 'tenant')
            ->and($store->find(null, 'system', 'scheduler', 'create-invoice', 'k1')['status'])->toBe('processing');
    });
}
