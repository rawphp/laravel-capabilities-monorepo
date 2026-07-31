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

// REQ-069 / L-005: durable approval ids must be unguessable and not process-local sequences.
it('put assigns unguessable random ids unique across two store instances (not sequential approval-n)', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $storeA = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $storeB = new DatabaseApprovalStore(new ArrayTableGateway, $clock);

    $payload = [
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
    ];

    $ids = [];
    for ($i = 0; $i < 5; $i++) {
        $ids[] = $storeA->put($payload)['id'];
        $ids[] = $storeB->put($payload)['id'];
    }

    expect($ids)->toHaveCount(10)
        ->and(count(array_unique($ids)))->toBe(10);

    foreach ($ids as $id) {
        expect($id)->toBeString()
            ->and($id)->not->toMatch('/^approval-\d+$/')
            ->and(strlen($id))->toBeGreaterThanOrEqual(32)
            ->and(ctype_xdigit($id))->toBeTrue();
    }
});

// REQ-069 / L-007: second claim while lease held must fail; path is a single atomic lease-free update.
it('claimLease fails second claim while lease held (atomic lease condition)', function () {
    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore(new ArrayTableGateway, $clock);
    $row = $store->put([
        'capability_name' => 'x',
        'status' => 'approved',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
        'execution_lease_until' => null,
    ]);

    $first = $store->claimLease($row['id'], 'approved', '2026-07-27T12:00:00Z', [
        'execution_lease_until' => '2026-07-27T12:05:00Z',
        'execution_attempt' => 1,
    ]);
    $second = $store->claimLease($row['id'], 'approved', '2026-07-27T12:00:00Z', [
        'execution_lease_until' => '2026-07-27T12:10:00Z',
        'execution_attempt' => 2,
    ]);

    expect($first)->not->toBeNull()
        ->and($first['execution_attempt'])->toBe(1)
        ->and($second)->toBeNull()
        ->and($store->find($row['id'])['execution_attempt'])->toBe(1)
        ->and($store->find($row['id'])['execution_lease_until'])->toBe('2026-07-27T12:05:00Z');
});

it('claimLease uses atomic updateWhereLeaseFree without prior find TOCTOU', function () {
    $gateway = new class implements \Rawphp\Capabilities\Persistence\TableGateway
    {
        public int $findCalls = 0;

        public int $updateWhereCalls = 0;

        public int $updateWhereLeaseFreeCalls = 0;

        /** @var array<string, array<string, mixed>> */
        private array $rows = [];

        public function insert(array $row): array
        {
            $id = (string) ($row['id'] ?? bin2hex(random_bytes(8)));
            $row['id'] = $id;
            $this->rows[$id] = $row;

            return $row;
        }

        public function find(string $id): ?array
        {
            $this->findCalls++;

            return $this->rows[$id] ?? null;
        }

        public function replace(string $id, array $row): ?array
        {
            if (! isset($this->rows[$id])) {
                return null;
            }
            $row['id'] = $id;
            $this->rows[$id] = $row;

            return $row;
        }

        public function updateWhere(array $where, array $attributes): ?array
        {
            $this->updateWhereCalls++;

            return null;
        }

        public function updateWhereLeaseFree(
            array $where,
            string $leaseColumn,
            string $nowIso,
            array $attributes,
        ): ?array {
            $this->updateWhereLeaseFreeCalls++;
            foreach ($this->rows as $id => $row) {
                foreach ($where as $k => $v) {
                    if (($row[$k] ?? null) !== $v) {
                        continue 2;
                    }
                }
                $lease = $row[$leaseColumn] ?? null;
                if (is_string($lease) && $lease !== '' && $lease > $nowIso) {
                    return null;
                }
                $merged = array_merge($row, $attributes);
                $merged['id'] = $id;
                $this->rows[$id] = $merged;

                return $merged;
            }

            return null;
        }

        public function findWhere(array $where): array
        {
            return [];
        }

        public function upsert(array $identity, array $row): array
        {
            return $this->insert(array_merge($row, $identity));
        }
    };

    $clock = new FixedClock(new \DateTimeImmutable('2026-07-27T12:00:00Z'));
    $store = new DatabaseApprovalStore($gateway, $clock);
    $row = $store->put([
        'capability_name' => 'x',
        'status' => 'approved',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
    ]);
    $findAfterPut = $gateway->findCalls;

    $claimed = $store->claimLease($row['id'], 'approved', '2026-07-27T12:00:00Z', [
        'execution_lease_until' => '2026-07-27T12:05:00Z',
        'execution_attempt' => 1,
    ]);

    expect($claimed)->not->toBeNull()
        ->and($gateway->updateWhereLeaseFreeCalls)->toBe(1)
        ->and($gateway->updateWhereCalls)->toBe(0)
        ->and($gateway->findCalls)->toBe($findAfterPut);
});
