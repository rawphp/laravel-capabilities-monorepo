<?php

// REQ-030: capabilities_idempotency.id must be a string primary key.
// Gateway ids are 32-char hex (QueryTableGateway::newId); a BIGINT
// auto-increment id column rejects them (MySQL strict-mode 1264).
// Regression: create migration used bigIncrements; ArrayTableGateway and
// hand-rolled SQLite schemas in older tests masked the mismatch.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;

/**
 * Fresh throwaway SQLite connection (unit-safe, no external DB).
 */
function idemfixConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    return $capsule->getConnection();
}

/**
 * Point the Schema facade at the connection's builder so real migration
 * files execute against the throwaway connection. Restores facade state after.
 */
function idemfixSwapSchema(ConnectionInterface $connection, Closure $run): mixed
{
    Schema::swap($connection->getSchemaBuilder());

    try {
        return $run();
    } finally {
        Facade::clearResolvedInstances();
    }
}

function idemfixCreateMigration(): object
{
    $path = dirname(__DIR__, 3).'/database/migrations/2026_07_27_000002_create_capabilities_idempotency_table.php';
    expect(is_file($path))->toBeTrue("create migration missing: {$path}");

    return require $path;
}

function idemfixAlterMigration(): object
{
    $path = dirname(__DIR__, 3).'/database/migrations/2026_08_27_000001_alter_capabilities_idempotency_id_column.php';
    expect(is_file($path))->toBeTrue("alter migration missing: {$path}");

    return require $path;
}

/**
 * Portable schema snapshot: declared column types/nullability/pk plus named
 * (non-autoindex) index names — enough to prove two install paths converge.
 *
 * @return array{columns: array<string, array{type: string, notnull: int, pk: int, default: string}>, indexes: list<string>}
 */
function idemfixSnapshot(ConnectionInterface $connection): array
{
    $columns = [];
    foreach ($connection->select("pragma table_info('".MigrationCatalog::TABLE_IDEMPOTENCY."')") as $column) {
        $column = (array) $column;
        $columns[(string) $column['name']] = [
            'type' => strtolower((string) $column['type']),
            'notnull' => (int) $column['notnull'],
            'pk' => (int) $column['pk'],
            'default' => strtolower((string) $column['dflt_value']),
        ];
    }
    ksort($columns);

    $indexes = [];
    foreach ($connection->select("pragma index_list('".MigrationCatalog::TABLE_IDEMPOTENCY."')") as $index) {
        $name = strtolower((string) ((array) $index)['name']);
        if (! str_starts_with($name, 'sqlite_autoindex')) {
            $indexes[] = $name;
        }
    }
    sort($indexes);

    return ['columns' => $columns, 'indexes' => $indexes];
}

it('idempotency create migration declares id as a string primary key (not BIGINT auto-increment)', function () {
    $connection = idemfixConnection();

    idemfixSwapSchema($connection, fn () => idemfixCreateMigration()->up());

    expect($connection->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_IDEMPOTENCY))->toBeTrue()
        ->and($connection->getSchemaBuilder()->getColumnType(MigrationCatalog::TABLE_IDEMPOTENCY, 'id'))
        ->toBeIn(['string', 'varchar']);

    $id = null;
    foreach ($connection->select("pragma table_info('".MigrationCatalog::TABLE_IDEMPOTENCY."')") as $column) {
        if (((array) $column)['name'] === 'id') {
            $id = (array) $column;
        }
    }

    expect($id)->not->toBeNull()
        // SQLite drops varchar lengths; string-family + pk + notnull is the
        // portable contract (MySQL compiles the same definition as varchar(64)).
        ->and(strtolower((string) $id['type']))->toStartWith('varchar')
        ->and((int) $id['pk'])->toBe(1)
        ->and((int) $id['notnull'])->toBe(1);
});

it('QueryTableGateway hex ids round-trip through the real create-migration schema', function () {
    $connection = idemfixConnection();

    idemfixSwapSchema($connection, fn () => idemfixCreateMigration()->up());

    $gateway = new QueryTableGateway($connection, MigrationCatalog::TABLE_IDEMPOTENCY);
    $stored = $gateway->insert([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'run-key-1',
        'status' => 'completed',
        'result_json' => ['ok' => true],
    ]);

    expect($stored['id'])->toMatch('/^[0-9a-f]{32}$/')
        ->and($gateway->find((string) $stored['id'])['idempotency_key'] ?? null)->toBe('run-key-1');
});

it('alter migration converges a legacy BIGINT-id table onto the fresh-install schema', function () {
    // Fresh install path: amended create migration.
    $fresh = idemfixConnection();
    idemfixSwapSchema($fresh, fn () => idemfixCreateMigration()->up());
    $freshSnapshot = idemfixSnapshot($fresh);

    // Legacy install path: the shipped 2026_07_27_000002 shape (BIGINT
    // auto-increment id) plus a garbage row from a non-strict install.
    $legacy = idemfixConnection();
    idemfixSwapSchema($legacy, function () use ($legacy): void {
        $legacy->getSchemaBuilder()->create(MigrationCatalog::TABLE_IDEMPOTENCY, function (Blueprint $blueprint): void {
            $blueprint->bigIncrements('id');
            $blueprint->string('tenant_id', 191)->default('');
            $blueprint->string('idempotency_key', 191);
        });
        $legacy->table(MigrationCatalog::TABLE_IDEMPOTENCY)->insert([
            'id' => 2496,
            'tenant_id' => 't1',
            'idempotency_key' => 'truncated-garbage',
        ]);
    });

    idemfixSwapSchema($legacy, fn () => idemfixAlterMigration()->up());

    expect($legacy->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_IDEMPOTENCY))->toBeTrue()
        ->and($legacy->getSchemaBuilder()->getColumnType(MigrationCatalog::TABLE_IDEMPOTENCY, 'id'))
        ->toBeIn(['string', 'varchar'])
        ->and(idemfixSnapshot($legacy))->toBe($freshSnapshot)
        ->and($legacy->table(MigrationCatalog::TABLE_IDEMPOTENCY)->count())->toBe(0);
});

it('alter migration is a no-op when the idempotency table is missing (fresh install)', function () {
    $connection = idemfixConnection();

    idemfixSwapSchema($connection, fn () => idemfixAlterMigration()->up());

    expect($connection->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_IDEMPOTENCY))->toBeFalse();
});

it('alter migration preserves rows when id is already a string column', function () {
    $connection = idemfixConnection();

    idemfixSwapSchema($connection, fn () => idemfixCreateMigration()->up());

    $gateway = new QueryTableGateway($connection, MigrationCatalog::TABLE_IDEMPOTENCY);
    $kept = $gateway->insert([
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'already-correct',
        'status' => 'completed',
    ]);
    $before = idemfixSnapshot($connection);

    idemfixSwapSchema($connection, fn () => idemfixAlterMigration()->up());

    expect(idemfixSnapshot($connection))->toBe($before)
        ->and($gateway->find((string) $kept['id']))->not->toBeNull();
});
