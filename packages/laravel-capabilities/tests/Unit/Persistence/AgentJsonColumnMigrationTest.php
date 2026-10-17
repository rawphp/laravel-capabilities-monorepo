<?php

// capabilities_approvals stores the agent turn a request was held from, so the approved
// run's CapabilityContext::agent() names it. Additive nullable migration; throwaway
// SQLite only — no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Support\FixedClock;

function agentcolConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

function agentcolRun(ConnectionInterface $connection, string $file, string $method = 'up'): void
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

const AGENTCOL_CREATE = '2026_07_27_000001_create_capabilities_approvals_table.php';
const AGENTCOL_ADD = '2026_10_17_000001_add_agent_json_to_capabilities_approvals_table.php';

it('add migration puts a nullable agent_json column on an existing approvals table', function () {
    $connection = agentcolConnection();
    agentcolRun($connection, AGENTCOL_CREATE);
    $connection->table(MigrationCatalog::TABLE_APPROVALS)->insert([
        'id' => 'legacy-row',
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
    ]);

    agentcolRun($connection, AGENTCOL_ADD);

    expect($connection->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'agent_json'))->toBeTrue();
    $legacy = (array) $connection->table(MigrationCatalog::TABLE_APPROVALS)->where('id', 'legacy-row')->first();
    expect($legacy['agent_json'])->toBeNull();
});

it('add migration is a no-op when the table is missing or already has the column, and down drops it', function () {
    $missing = agentcolConnection();
    agentcolRun($missing, AGENTCOL_ADD);
    expect($missing->getSchemaBuilder()->hasTable(MigrationCatalog::TABLE_APPROVALS))->toBeFalse();

    $twice = agentcolConnection();
    agentcolRun($twice, AGENTCOL_CREATE);
    agentcolRun($twice, AGENTCOL_ADD);
    agentcolRun($twice, AGENTCOL_ADD);
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'agent_json'))->toBeTrue();

    agentcolRun($twice, AGENTCOL_ADD, 'down');
    agentcolRun($twice, AGENTCOL_ADD, 'down');
    expect($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'agent_json'))->toBeFalse()
        ->and($twice->getSchemaBuilder()->hasColumn(MigrationCatalog::TABLE_APPROVALS, 'result_status'))->toBeTrue();
});

it('before the add migration runs, approvals without an agent context still insert; agent ones fail', function () {
    $connection = agentcolConnection();
    foreach ([
        AGENTCOL_CREATE,
        '2026_09_24_000001_add_executor_actor_to_capabilities_approvals_table.php',
        '2026_09_29_000001_add_approval_policy_to_capabilities_approvals_table.php',
        '2026_10_01_000001_add_original_surface_to_capabilities_approvals_table.php',
    ] as $migration) {
        agentcolRun($connection, $migration);
    }
    $store = new DatabaseApprovalStore(
        new QueryTableGateway($connection, MigrationCatalog::TABLE_APPROVALS, columnMap: ['scope' => 'scope_json', 'messaging' => 'channel_meta_json']),
        new FixedClock(new DateTimeImmutable('2026-10-17T00:00:00Z')),
    );
    $record = [
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
    ];

    $row = $store->put($record + ['agent_json' => null]);

    expect($store->find($row['id']))->not->toBeNull()
        ->and($store->find($row['id'])['agent_json'] ?? null)->toBeNull()
        ->and(fn () => $store->put($record + ['agent_json' => ['conversation_ulid' => 'c1', 'turn_ulid' => 't1']]))
        ->toThrow(QueryException::class);
});
