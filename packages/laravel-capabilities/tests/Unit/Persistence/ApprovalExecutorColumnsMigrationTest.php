<?php

// REQ-030 / D-006: capabilities_approvals records which principal executed
// the domain (executor_actor_type / executor_actor_id). Additive migration so
// fresh and existing installs converge. Throwaway SQLite only — no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

function execcolConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

function execcolRun(ConnectionInterface $connection, string $file, string $method = 'up'): void
{
    $path = dirname(__DIR__, 3).'/database/migrations/'.$file;
    expect(is_file($path))->toBeTrue("migration missing: {$path}");

    Schema::swap($connection->getSchemaBuilder());

    try {
        (require $path)->{$method}();
    } finally {
        Facade::clearResolvedInstances();
    }
}

const EXECCOL_CREATE = '2026_07_27_000001_create_capabilities_approvals_table.php';
const EXECCOL_ADD = '2026_09_24_000001_add_executor_actor_to_capabilities_approvals_table.php';

it('catalog lists executor actor columns on approvals', function () {
    expect(MigrationCatalog::columns(MigrationCatalog::TABLE_APPROVALS))
        ->toContain('executor_actor_type')
        ->toContain('executor_actor_id');
});

it('add migration puts nullable executor columns on an existing approvals table', function () {
    $connection = execcolConnection();
    execcolRun($connection, EXECCOL_CREATE);
    $connection->table(MigrationCatalog::TABLE_APPROVALS)->insert([
        'id' => 'legacy-row',
        'capability_name' => 'create-invoice',
        'status' => 'executed',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
    ]);

    execcolRun($connection, EXECCOL_ADD);

    $schema = $connection->getSchemaBuilder();
    expect($schema->hasColumns(MigrationCatalog::TABLE_APPROVALS, ['executor_actor_type', 'executor_actor_id']))->toBeTrue();

    $legacy = (array) $connection->table(MigrationCatalog::TABLE_APPROVALS)->where('id', 'legacy-row')->first();
    expect($legacy['executor_actor_type'])->toBeNull()
        ->and($legacy['executor_actor_id'])->toBeNull();
});

it('add migration is a no-op when the approvals table is missing or already has the columns', function () {
    $missing = execcolConnection();
    execcolRun($missing, EXECCOL_ADD);
    expect($missing->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_APPROVALS))->toBeFalse();

    $twice = execcolConnection();
    execcolRun($twice, EXECCOL_CREATE);
    execcolRun($twice, EXECCOL_ADD);
    execcolRun($twice, EXECCOL_ADD);
    expect($twice->getSchemaBuilder()->hasColumns(MigrationCatalog::TABLE_APPROVALS, ['executor_actor_type', 'executor_actor_id']))->toBeTrue();
});

it('add migration down drops the executor columns', function () {
    $connection = execcolConnection();
    execcolRun($connection, EXECCOL_CREATE);
    execcolRun($connection, EXECCOL_ADD);

    execcolRun($connection, EXECCOL_ADD, 'down');

    $schema = $connection->getSchemaBuilder();
    expect($schema->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'executor_actor_type'))->toBeFalse()
        ->and($schema->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'executor_actor_id'))->toBeFalse()
        ->and($schema->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'result_status'))->toBeTrue();
});
