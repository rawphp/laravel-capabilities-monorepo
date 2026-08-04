<?php

// REQ-029: Production persistence path closure. Unit-only.
// REQ-049: durable path uses QueryTableGateway when a connection is available
// (ArrayTableGateway remains explicit host/unit override only).

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('path: database config constructs durable store types for approval and idempotency', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $resolved = ContainerBindings::resolve($config);

    expect($resolved['drivers']['approval_store']['resolved'])->toBe('database')
        ->and($resolved['drivers']['approval_store']['package_default'])->toBeFalse()
        ->and($resolved['drivers']['idempotency']['resolved'])->toBe('database')
        ->and($resolved['drivers']['idempotency']['package_default'])->toBeFalse();

    // Explicit host/unit gateway override (ArrayTableGateway) — still supported.
    $gateway = new ArrayTableGateway;
    $manager = ContainerBindings::makeApprovalManager($config, $gateway);
    $idem = ContainerBindings::makeIdempotencyStore($config, $gateway);

    expect($manager->store())->toBeInstanceOf(DatabaseApprovalStore::class)
        ->and($idem)->toBeInstanceOf(DatabaseIdempotencyStore::class);

    // Survive "process restart" simulation: second store on same gateway sees rows.
    $approval = $manager->store()->put([
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '1',
        'original_caller' => 'agent',
    ]);
    $idem->put([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'retry-1',
        'status' => 'completed',
        'result_json' => ['ok' => true],
    ]);

    $reloadedApproval = new DatabaseApprovalStore($gateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));
    $reloadedIdem = new DatabaseIdempotencyStore($gateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')));

    expect($reloadedApproval->find($approval['id']))->not->toBeNull()
        ->and($reloadedIdem->find('t1', 'user', '1', 'create-invoice', 'retry-1')['status'] ?? null)->toBe('completed');
});

it('path: database + connection builds QueryTableGateway (not silent ArrayTableGateway)', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $connection = $capsule->getConnection();

    $manager = ContainerBindings::makeApprovalManager($config, null, $connection);
    $idem = ContainerBindings::makeIdempotencyStore($config, null, $connection);

    $approvalTable = (new ReflectionClass($manager->store()))->getProperty('table');
    $idemTable = (new ReflectionClass($idem))->getProperty('table');
    $approvalGw = $approvalTable->getValue($manager->store());
    $idemGw = $idemTable->getValue($idem);

    expect($approvalGw)->toBeInstanceOf(QueryTableGateway::class)
        ->and($idemGw)->toBeInstanceOf(QueryTableGateway::class)
        ->and($approvalGw)->not->toBeInstanceOf(ArrayTableGateway::class)
        ->and($idemGw)->not->toBeInstanceOf(ArrayTableGateway::class)
        ->and($approvalGw->tableName())->toBe(MigrationCatalog::TABLE_APPROVALS)
        ->and($idemGw->tableName())->toBe(MigrationCatalog::TABLE_IDEMPOTENCY);
});

it('path: memory config keeps in-memory stores for unit tests', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    expect(ContainerBindings::makeIdempotencyStore($config))->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and(ContainerBindings::makeApprovalManager($config)->store())->toBeInstanceOf(InMemoryApprovalStore::class);
});

it('path: migrations catalog covers approvals and idempotency tables', function () {
    expect(MigrationCatalog::hasTable(MigrationCatalog::TABLE_APPROVALS))->toBeTrue()
        ->and(MigrationCatalog::hasTable(MigrationCatalog::TABLE_IDEMPOTENCY))->toBeTrue()
        ->and(is_dir(dirname(__DIR__, 3).'/database/migrations'))->toBeTrue();
});
