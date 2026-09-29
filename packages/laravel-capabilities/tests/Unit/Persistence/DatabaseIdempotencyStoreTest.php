<?php

// REQ-032: Database IdempotencyStore. Unit-only via ArrayTableGateway.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\TableGateway;
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

/**
 * @return array<string, mixed>
 */
function claimRecord(string $hash, string $expiresAt = '2026-07-28T12:00:00Z'): array
{
    return [
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'request_hash' => $hash,
        'status' => 'processing',
        'expires_at' => $expiresAt,
    ];
}

it('claim writes a processing row and returns true when the identity is free', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));

    expect($store->claim(claimRecord('h-a')))->toBeTrue()
        ->and($store->find('t', 'user', '1', 'x', 'k'))->toMatchArray([
            'status' => 'processing',
            'request_hash' => 'h-a',
        ]);
});

it('claim returns false and leaves the holder untouched when the identity is taken', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->claim(claimRecord('h-a'));

    expect($store->claim(claimRecord('h-b')))->toBeFalse()
        ->and($store->find('t', 'user', '1', 'x', 'k')['request_hash'])->toBe('h-a');
});

it('claim takes over an expired row with a compare-and-swap on its expiry', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $store->put(claimRecord('h-old', '2026-07-27T11:00:00Z'));

    expect($store->claim(claimRecord('h-new')))->toBeTrue()
        ->and($store->find('t', 'user', '1', 'x', 'k'))->toMatchArray([
            'request_hash' => 'h-new',
            'expires_at' => '2026-07-28T12:00:00Z',
        ]);
});

it('claim loses when another request takes over the expired row first', function () {
    $inner = new ArrayTableGateway;
    $clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z'));
    $rival = new DatabaseIdempotencyStore($inner, $clock);
    $rival->put(claimRecord('h-old', '2026-07-27T11:00:00Z'));

    // Rival's takeover lands between our read of the expired row and our CAS.
    $racing = racingTableGateway($inner, function () use ($rival): void {
        $rival->put(claimRecord('h-rival'));
    });
    $store = new DatabaseIdempotencyStore($racing, $clock);

    expect($store->claim(claimRecord('h-mine')))->toBeFalse()
        ->and($store->find('t', 'user', '1', 'x', 'k')['request_hash'])->toBe('h-rival');
});

/**
 * ArrayTableGateway wrapper that runs $beforeUpdateWhere once, just before the first updateWhere.
 */
function racingTableGateway(ArrayTableGateway $inner, Closure $beforeUpdateWhere): TableGateway
{
    return new class($inner, $beforeUpdateWhere) implements TableGateway
    {
        public function __construct(private ArrayTableGateway $inner, private ?Closure $beforeUpdateWhere) {}

        public function insert(array $row): array
        {
            return $this->inner->insert($row);
        }

        public function insertIfAbsent(array $identity, array $row): ?array
        {
            return $this->inner->insertIfAbsent($identity, $row);
        }

        public function find(string $id): ?array
        {
            return $this->inner->find($id);
        }

        public function replace(string $id, array $row): ?array
        {
            return $this->inner->replace($id, $row);
        }

        public function updateWhere(array $where, array $attributes): ?array
        {
            if ($this->beforeUpdateWhere !== null) {
                ($this->beforeUpdateWhere)();
                $this->beforeUpdateWhere = null;
            }

            return $this->inner->updateWhere($where, $attributes);
        }

        public function updateWhereLeaseFree(array $where, string $leaseColumn, string $nowIso, array $attributes): ?array
        {
            return $this->inner->updateWhereLeaseFree($where, $leaseColumn, $nowIso, $attributes);
        }

        public function findWhere(array $where): array
        {
            return $this->inner->findWhere($where);
        }

        public function upsert(array $identity, array $row): array
        {
            return $this->inner->upsert($identity, $row);
        }
    };
}
