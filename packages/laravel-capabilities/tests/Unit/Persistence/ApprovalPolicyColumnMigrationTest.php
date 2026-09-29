<?php

// L-002 / D-006: capabilities_approvals stores the capability's approvalPolicy so
// accept / reject enforce the declared rule. Additive nullable migration; throwaway
// SQLite only — no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

function policycolConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

function policycolRun(ConnectionInterface $connection, string $file, string $method = 'up'): void
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

const POLICYCOL_CREATE = '2026_07_27_000001_create_capabilities_approvals_table.php';
const POLICYCOL_ADD = '2026_09_29_000001_add_approval_policy_to_capabilities_approvals_table.php';

it('add migration puts a nullable approval_policy column on an existing approvals table', function () {
    $connection = policycolConnection();
    policycolRun($connection, POLICYCOL_CREATE);
    $connection->table(MigrationCatalog::TABLE_APPROVALS)->insert([
        'id' => 'legacy-row',
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
    ]);

    policycolRun($connection, POLICYCOL_ADD);

    expect($connection->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'approval_policy'))->toBeTrue();
    $legacy = (array) $connection->table(MigrationCatalog::TABLE_APPROVALS)->where('id', 'legacy-row')->first();
    expect($legacy['approval_policy'])->toBeNull();
});

it('add migration is a no-op when the table is missing or already has the column, and down drops it', function () {
    $missing = policycolConnection();
    policycolRun($missing, POLICYCOL_ADD);
    expect($missing->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_APPROVALS))->toBeFalse();

    $twice = policycolConnection();
    policycolRun($twice, POLICYCOL_CREATE);
    policycolRun($twice, POLICYCOL_ADD);
    policycolRun($twice, POLICYCOL_ADD);
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'approval_policy'))->toBeTrue();

    policycolRun($twice, POLICYCOL_ADD, 'down');
    policycolRun($twice, POLICYCOL_ADD, 'down');
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'approval_policy'))->toBeFalse()
        ->and($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'result_status'))->toBeTrue();
});
