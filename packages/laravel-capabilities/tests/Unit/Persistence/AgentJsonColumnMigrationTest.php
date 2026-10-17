<?php

// capabilities_approvals stores the agent turn a request was held from, so the approved
// run's CapabilityContext::agent() names it. Additive nullable migration; throwaway
// SQLite only — no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Fixtures\RecordingExceptionHandler;

afterEach(fn () => RecordingExceptionHandler::unbind());

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

it('before the add migration runs, every approval still queues; an agent one drops its turn and is reported', function () {
    $handler = RecordingExceptionHandler::bind();
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

    $plain = $store->put($record + ['agent_json' => null]);
    $agent = $store->put(['original_caller' => 'agent', 'agent_json' => ['conversation_ulid' => 'c1', 'turn_ulid' => 't1']] + $record);

    expect($store->find($plain['id']))->not->toBeNull()
        ->and($store->find($agent['id']))->not->toBeNull()
        ->and($store->find($agent['id'])['agent_json'] ?? null)->toBeNull()
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->reported[0]->getMessage())->toContain('add_agent_json_to_capabilities_approvals_table');
});

it('after the add migration runs, an agent approval keeps its turn and reports nothing', function () {
    $handler = RecordingExceptionHandler::bind();
    $connection = agentcolConnection();
    foreach ([
        AGENTCOL_CREATE,
        '2026_09_24_000001_add_executor_actor_to_capabilities_approvals_table.php',
        '2026_09_29_000001_add_approval_policy_to_capabilities_approvals_table.php',
        '2026_10_01_000001_add_original_surface_to_capabilities_approvals_table.php',
        AGENTCOL_ADD,
    ] as $migration) {
        agentcolRun($connection, $migration);
    }
    $store = new DatabaseApprovalStore(
        new QueryTableGateway($connection, MigrationCatalog::TABLE_APPROVALS, columnMap: ['scope' => 'scope_json', 'messaging' => 'channel_meta_json']),
        new FixedClock(new DateTimeImmutable('2026-10-17T00:00:00Z')),
    );

    $row = $store->put([
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'agent',
        'agent_json' => ['conversation_ulid' => 'c1', 'turn_ulid' => 't1'],
    ]);

    expect($store->find($row['id'])['agent_json'] ?? null)->toBe(['conversation_ulid' => 'c1', 'turn_ulid' => 't1'])
        ->and($handler->reported)->toBe([]);
});

it('a gateway whose connection has no schema builder assumes the column is there', function () {
    $gateway = new QueryTableGateway($this->createMock(ConnectionInterface::class), MigrationCatalog::TABLE_APPROVALS);

    expect($gateway->hasColumn('agent_json'))->toBeTrue();
});
