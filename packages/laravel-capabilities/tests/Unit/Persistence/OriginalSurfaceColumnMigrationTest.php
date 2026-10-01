<?php

// D-022: capabilities_approvals stores the surface the request came through so the
// approved run re-gates on it. Additive nullable migration; throwaway SQLite only —
// no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

function surfacecolConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

function surfacecolRun(ConnectionInterface $connection, string $file, string $method = 'up'): void
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

const SURFACECOL_CREATE = '2026_07_27_000001_create_capabilities_approvals_table.php';
const SURFACECOL_ADD = '2026_10_01_000001_add_original_surface_to_capabilities_approvals_table.php';

it('add migration puts a nullable original_surface column on an existing approvals table', function () {
    $connection = surfacecolConnection();
    surfacecolRun($connection, SURFACECOL_CREATE);
    $connection->table(MigrationCatalog::TABLE_APPROVALS)->insert([
        'id' => 'legacy-row',
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
    ]);

    surfacecolRun($connection, SURFACECOL_ADD);

    expect($connection->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'original_surface'))->toBeTrue();
    $legacy = (array) $connection->table(MigrationCatalog::TABLE_APPROVALS)->where('id', 'legacy-row')->first();
    expect($legacy['original_surface'])->toBeNull();
});

it('add migration is a no-op when the table is missing or already has the column, and down drops it', function () {
    $missing = surfacecolConnection();
    surfacecolRun($missing, SURFACECOL_ADD);
    expect($missing->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_APPROVALS))->toBeFalse();

    $twice = surfacecolConnection();
    surfacecolRun($twice, SURFACECOL_CREATE);
    surfacecolRun($twice, SURFACECOL_ADD);
    surfacecolRun($twice, SURFACECOL_ADD);
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'original_surface'))->toBeTrue();

    surfacecolRun($twice, SURFACECOL_ADD, 'down');
    surfacecolRun($twice, SURFACECOL_ADD, 'down');
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'original_surface'))->toBeFalse()
        ->and($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'result_status'))->toBeTrue();
});
