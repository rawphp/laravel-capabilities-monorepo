<?php

// D-005: two concurrent first requests under one key must not both run. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

/**
 * Store whose first $staleReads find() calls miss: models request B reading
 * before request A's claim lands. null = every read misses.
 */
function staleReadStore(IdempotencyStore $inner, ?int $staleReads): IdempotencyStore
{
    return new class($inner, $staleReads) implements IdempotencyStore
    {
        public function __construct(private IdempotencyStore $inner, private ?int $staleReads) {}

        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            if ($this->staleReads === null) {
                return null;
            }
            if ($this->staleReads > 0) {
                $this->staleReads--;

                return null;
            }

            return $this->inner->find($tenantId, $actorType, $actorId, $capabilityName, $key);
        }

        public function put(array $record): array
        {
            return $this->inner->put($record);
        }

        public function claim(array $record): bool
        {
            return $this->inner->claim($record);
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return $this->inner->update($tenantId, $actorType, $actorId, $capabilityName, $key, $attributes);
        }
    };
}

dataset('claim stores', [
    'domain' => fn () => IdempotencyHelpers::store(),
    'in-memory' => fn () => IdempotencyHelpers::inMemoryStore(),
    'database' => fn () => new DatabaseIdempotencyStore(new ArrayTableGateway, IdempotencyHelpers::clock()),
]);

it('lets only one of two racing first requests continue; the other is busy on the winner row', function (IdempotencyStore $store) {
    // Both requests' initial find() miss; only the loser's re-read sees the row.
    $guard = IdempotencyHelpers::guard(staleReadStore($store, 2), IdempotencyHelpers::clock());
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $a = $guard->lookup($def, $ctx, 'race-key', $hash);
    $b = $guard->lookup($def, $ctx, 'race-key', $hash);

    expect($a['action'])->toBe('continue')
        ->and($b['action'])->toBe('busy')
        ->and($b['result']->toArray()['error']['code'])->toBe('conflict')
        ->and($b['record']['status'])->toBe('processing')
        ->and($b['record']['request_hash'])->toBe($hash);
})->with('claim stores');

it('answers busy when a lost claim cannot re-read the winner row', function () {
    $store = new DatabaseIdempotencyStore(new ArrayTableGateway, IdempotencyHelpers::clock());
    $guard = IdempotencyHelpers::guard(staleReadStore($store, null), IdempotencyHelpers::clock());
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $guard->lookup($def, $ctx, 'race-key', $hash);
    $b = $guard->lookup($def, $ctx, 'race-key', $hash);

    expect($b['action'])->toBe('busy')
        ->and($b['result']->toArray()['error'])->toMatchArray(['code' => 'conflict', 'retryable' => true])
        ->and($b)->not->toHaveKey('record');
});

it('claim returns false and does not overwrite an unexpired holder', function (IdempotencyStore $store) {
    $record = [
        'tenant_id' => null,
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'request_hash' => 'h-a',
        'status' => 'processing',
        'expires_at' => '2026-01-16T12:00:00+00:00',
    ];

    expect($store->claim($record))->toBeTrue()
        ->and($store->claim(array_merge($record, ['request_hash' => 'h-b'])))->toBeFalse()
        ->and($store->find(null, 'user', '1', 'x', 'k')['request_hash'])->toBe('h-a');
})->with('claim stores');

it('claim takes over an expired holder', function (IdempotencyStore $store) {
    $record = [
        'tenant_id' => 'tenant-1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'x',
        'idempotency_key' => 'k',
        'request_hash' => 'h-old',
        'status' => 'completed',
        'expires_at' => '2026-01-15T11:00:00+00:00',
    ];
    $store->put($record);

    $claimed = $store->claim(array_merge($record, [
        'request_hash' => 'h-new',
        'status' => 'processing',
        'expires_at' => '2026-01-16T12:00:00+00:00',
    ]));

    expect($claimed)->toBeTrue()
        ->and($store->find('tenant-1', 'user', '1', 'x', 'k')['request_hash'])->toBe('h-new');
})->with('claim stores');
