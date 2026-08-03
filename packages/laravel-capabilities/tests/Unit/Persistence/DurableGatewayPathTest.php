<?php

// REQ-049: Durable TableGateway path closure. Unit-only.
// Children: REQ-050 (QueryTableGateway) + REQ-051 (bindings) + REQ-052 (host docs).
// Filter: DurableGateway | TableGateway | Persistence

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

/**
 * SQLite :memory: connection with package catalog tables (unit-safe, no network DB).
 */
function req049SqliteConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $connection = $capsule->getConnection();

    $connection->statement(<<<'SQL'
        create table capabilities_approvals (
            id text primary key not null,
            capability_name text,
            status text,
            tenant_id text,
            scope_json text,
            requester_actor_type text,
            requester_actor_id text,
            original_caller text,
            input_json text,
            input_hash text,
            idempotency_key text,
            result_json text,
            result_status text,
            decided_by text,
            decided_at text,
            decision_reason text,
            expires_at text,
            execution_lease_until text,
            execution_attempt integer default 0,
            approved_at text,
            channel_meta_json text,
            created_at text,
            updated_at text
        )
    SQL);

    $connection->statement(<<<'SQL'
        create table capabilities_idempotency (
            id text primary key not null,
            tenant_id text not null,
            actor_type text not null,
            actor_id text not null,
            capability_name text not null,
            idempotency_key text not null,
            request_hash text,
            status text,
            result_json text,
            approval_id text,
            created_at text,
            expires_at text,
            unique (tenant_id, actor_type, actor_id, capability_name, idempotency_key)
        )
    SQL);

    return $connection;
}

function req049StoreGateway(object $store): TableGateway
{
    $prop = (new ReflectionClass($store))->getProperty('table');

    return $prop->getValue($store);
}

it('DurableGateway path: connection available → QueryTableGateway, never silent ArrayTableGateway', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $connection = req049SqliteConnection();

    $registry = ContainerBindings::makeRegistry($config, null, null, null, $connection);
    $approvalGw = req049StoreGateway($registry->approvalStore());
    $idemGw = req049StoreGateway($registry->idempotencyStore());

    expect($registry->approvalStore())->toBeInstanceOf(DatabaseApprovalStore::class)
        ->and($registry->idempotencyStore())->toBeInstanceOf(DatabaseIdempotencyStore::class)
        ->and($approvalGw)->toBeInstanceOf(QueryTableGateway::class)
        ->and($idemGw)->toBeInstanceOf(QueryTableGateway::class)
        ->and($approvalGw)->not->toBeInstanceOf(ArrayTableGateway::class)
        ->and($idemGw)->not->toBeInstanceOf(ArrayTableGateway::class)
        ->and($approvalGw->tableName())->toBe(MigrationCatalog::TABLE_APPROVALS)
        ->and($idemGw->tableName())->toBe(MigrationCatalog::TABLE_IDEMPOTENCY)
        ->and($approvalGw)->not->toBe($idemGw);
});

it('DurableGateway path: factory without gateway or connection fails closed (no Array fallback)', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);

    expect(fn () => ContainerBindings::makeApprovalManager($config, null, null))
        ->toThrow(BootException::class);

    expect(fn () => ContainerBindings::makeIdempotencyStore($config, null, null))
        ->toThrow(BootException::class);

    expect(fn () => ContainerBindings::makeRegistry($config, null, null, null, null))
        ->toThrow(BootException::class);

    try {
        ContainerBindings::makeDatabaseTableGateway(MigrationCatalog::TABLE_APPROVALS, $config);
        expect(false)->toBeTrue();
    } catch (BootException $e) {
        expect($e->getMessage())->toContain('Refusing silent ArrayTableGateway fallback')
            ->and($e->getMessage())->toContain(MigrationCatalog::TABLE_APPROVALS);
    }
});

it('DurableGateway path: QueryTableGateway-backed stores survive process-restart simulation', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $connection = req049SqliteConnection();
    $clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z'));

    $manager = ContainerBindings::makeApprovalManager($config, null, $connection);
    $idem = ContainerBindings::makeIdempotencyStore($config, null, $connection);

    expect(req049StoreGateway($manager->store()))->toBeInstanceOf(QueryTableGateway::class)
        ->and(req049StoreGateway($idem))->toBeInstanceOf(QueryTableGateway::class);

    $approval = $manager->store()->put([
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'http',
        'tenant_id' => 't1',
    ]);
    $idem->put([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'retry-key-1',
        'status' => 'completed',
        'result_json' => ['ok' => true, 'invoice_id' => 42],
    ]);

    // New store instances on the same connection (new process, same durable backend).
    $reloadedApproval = new DatabaseApprovalStore(
        ContainerBindings::makeDatabaseTableGateway(
            MigrationCatalog::TABLE_APPROVALS,
            $config,
            null,
            $connection,
            ContainerBindings::APPROVAL_COLUMN_MAP,
        ),
        $clock,
    );
    $reloadedIdem = new DatabaseIdempotencyStore(
        ContainerBindings::makeDatabaseTableGateway(
            MigrationCatalog::TABLE_IDEMPOTENCY,
            $config,
            null,
            $connection,
        ),
        $clock,
    );

    expect($reloadedApproval->find($approval['id']))->not->toBeNull()
        ->and($reloadedApproval->find($approval['id'])['status'] ?? null)->toBe('pending')
        ->and($reloadedIdem->find('t1', 'user', '1', 'create-invoice', 'retry-key-1')['status'] ?? null)
        ->toBe('completed')
        ->and($reloadedIdem->find('t1', 'user', '1', 'create-invoice', 'retry-key-1')['result_json'] ?? null)
        ->toBe(['ok' => true, 'invoice_id' => 42]);
});

it('DurableGateway path: host TableGateway override remains unit-safe for database drivers', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $host = new ArrayTableGateway;

    $manager = ContainerBindings::makeApprovalManager($config, $host);
    $idem = ContainerBindings::makeIdempotencyStore($config, $host);
    $registry = ContainerBindings::makeRegistry($config, $host);

    expect(req049StoreGateway($manager->store()))->toBe($host)
        ->and(req049StoreGateway($idem))->toBe($host)
        ->and(req049StoreGateway($registry->approvalStore()))->toBe($host)
        ->and(req049StoreGateway($registry->idempotencyStore()))->toBe($host)
        ->and($host)->toBeInstanceOf(ArrayTableGateway::class);

    $approval = $manager->store()->put([
        'capability_name' => 'void-sub',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '9',
        'original_caller' => 'agent',
    ]);
    expect($manager->store()->find($approval['id']))->not->toBeNull();
});

it('DurableGateway path: memory drivers keep in-memory stores (Array only via host override)', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);

    $manager = ContainerBindings::makeApprovalManager($config);
    $idem = ContainerBindings::makeIdempotencyStore($config);
    $plan = ContainerBindings::plan($config);

    expect($manager->store())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($idem)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($plan[TableGateway::class] ?? null)->toBe(ArrayTableGateway::class);
});

it('DurableGateway path: plan advertises QueryTableGateway when any database driver is set', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    $plan = ContainerBindings::plan($config);

    expect($plan[TableGateway::class] ?? null)->toBe(QueryTableGateway::class);
});

it('DurableGateway path: host override docs name real symbols and config keys', function () {
    // tests/Unit/Persistence → package root (3) → monorepo root (5)
    $packageRoot = dirname(__DIR__, 3);
    $monorepoRoot = dirname(__DIR__, 5);
    $roots = [
        $monorepoRoot.'/docs/tutorials/first-capability.md',
        $monorepoRoot.'/docs/versioning.md',
        $packageRoot.'/README.md',
        $monorepoRoot.'/README.md',
    ];

    $combined = '';
    foreach ($roots as $path) {
        expect(is_file($path))->toBeTrue("missing doc: {$path}");
        $combined .= (string) file_get_contents($path)."\n";
    }

    expect($combined)->toContain('QueryTableGateway')
        ->and($combined)->toContain('TableGateway')
        ->and($combined)->toContain('approval.store')
        ->and($combined)->toContain('idempotency.driver')
        ->and($combined)->toContain('ArrayTableGateway')
        ->and($combined)->toContain('singleton(TableGateway::class');
});
