<?php

// REQ-050: Illuminate query TableGateway. Unit-only (sqlite :memory: / null connection).

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Persistence\TableGateway;

/**
 * @return array{0: ConnectionInterface, 1: QueryTableGateway}
 */
function queryTableGatewayFixture(
    string $table = 'capabilities_approvals',
    ?array $jsonColumns = null,
    array $columnMap = [],
): array {
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
            input_json text,
            result_json text,
            messaging text,
            scope text,
            channel_meta_json text,
            execution_attempt integer default 0
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
            status text,
            result_json text,
            unique (tenant_id, actor_type, actor_id, capability_name, idempotency_key)
        )
    SQL);

    $gateway = new QueryTableGateway(
        $connection,
        $table,
        $jsonColumns,
        $columnMap,
    );

    return [$connection, $gateway];
}

it('implements TableGateway for a named table', function () {
    [, $gateway] = queryTableGatewayFixture();

    expect($gateway)->toBeInstanceOf(TableGateway::class)
        ->and($gateway)->toBeInstanceOf(QueryTableGateway::class);
});

it('insert and find round-trip scalar columns', function () {
    [, $gateway] = queryTableGatewayFixture();

    $row = $gateway->insert([
        'id' => 'a-1',
        'capability_name' => 'create-invoice',
        'status' => 'pending',
        'tenant_id' => 't1',
    ]);

    expect($row['id'])->toBe('a-1')
        ->and($gateway->find('a-1'))->toMatchArray([
            'id' => 'a-1',
            'capability_name' => 'create-invoice',
            'status' => 'pending',
            'tenant_id' => 't1',
        ]);
});

it('encodes and decodes JSON/array columns for store row shapes', function () {
    [, $gateway] = queryTableGatewayFixture(
        columnMap: [
            'scope' => 'scope_json',
            'messaging' => 'channel_meta_json',
        ],
    );

    $row = $gateway->insert([
        'id' => 'a-json',
        'capability_name' => 'x',
        'status' => 'pending',
        'input_json' => ['amount' => 10, 'currency' => 'USD'],
        'result_json' => ['ok' => true, 'data' => ['id' => 9]],
        'scope' => ['tenant' => 't1'],
        'messaging' => ['channel' => 'telegram', 'chat_id' => '42'],
    ]);

    expect($row['input_json'])->toBe(['amount' => 10, 'currency' => 'USD'])
        ->and($row['result_json'])->toBe(['ok' => true, 'data' => ['id' => 9]])
        ->and($row['scope'])->toBe(['tenant' => 't1'])
        ->and($row['messaging'])->toBe(['channel' => 'telegram', 'chat_id' => '42']);

    $found = $gateway->find('a-json');
    expect($found['input_json'])->toBe(['amount' => 10, 'currency' => 'USD'])
        ->and($found['result_json'])->toBe(['ok' => true, 'data' => ['id' => 9]])
        ->and($found['scope'])->toBe(['tenant' => 't1'])
        ->and($found['messaging'])->toBe(['channel' => 'telegram', 'chat_id' => '42']);
});

it('replace updates an existing row and returns null when missing', function () {
    [, $gateway] = queryTableGatewayFixture();
    $gateway->insert(['id' => 'r-1', 'status' => 'pending', 'capability_name' => 'x']);

    $replaced = $gateway->replace('r-1', [
        'id' => 'r-1',
        'status' => 'approved',
        'capability_name' => 'x',
        'tenant_id' => 't9',
    ]);
    $missing = $gateway->replace('nope', ['id' => 'nope', 'status' => 'x']);

    expect($replaced)->not->toBeNull()
        ->and($replaced['status'])->toBe('approved')
        ->and($replaced['tenant_id'])->toBe('t9')
        ->and($missing)->toBeNull()
        ->and($gateway->find('r-1')['status'])->toBe('approved');
});

it('updateWhere is conditional and returns null on miss (compareAndUpdate semantics)', function () {
    [, $gateway] = queryTableGatewayFixture();
    $gateway->insert(['id' => 'u-1', 'status' => 'pending', 'capability_name' => 'x']);

    $ok = $gateway->updateWhere(
        ['id' => 'u-1', 'status' => 'pending'],
        ['status' => 'approved', 'execution_attempt' => 1],
    );
    $miss = $gateway->updateWhere(
        ['id' => 'u-1', 'status' => 'pending'],
        ['status' => 'rejected'],
    );

    expect($ok)->not->toBeNull()
        ->and($ok['status'])->toBe('approved')
        ->and($ok['execution_attempt'])->toBe(1)
        ->and($miss)->toBeNull()
        ->and($gateway->find('u-1')['status'])->toBe('approved');
});

it('findWhere returns matching rows', function () {
    [, $gateway] = queryTableGatewayFixture();
    $gateway->insert(['id' => 'f-1', 'status' => 'pending', 'capability_name' => 'a']);
    $gateway->insert(['id' => 'f-2', 'status' => 'approved', 'capability_name' => 'b']);
    $gateway->insert(['id' => 'f-3', 'status' => 'pending', 'capability_name' => 'c']);

    $pending = $gateway->findWhere(['status' => 'pending']);

    $ids = array_column($pending, 'id');
    sort($ids);

    expect($pending)->toHaveCount(2)
        ->and($ids)->toBe(['f-1', 'f-3']);
});

it('upsert inserts then updates by composite identity', function () {
    [, $gateway] = queryTableGatewayFixture(table: 'capabilities_idempotency');

    $first = $gateway->upsert(
        [
            'tenant_id' => 't1',
            'actor_type' => 'user',
            'actor_id' => '7',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
        ],
        [
            'id' => 'idem-1',
            'status' => 'processing',
            'result_json' => null,
        ],
    );

    $second = $gateway->upsert(
        [
            'tenant_id' => 't1',
            'actor_type' => 'user',
            'actor_id' => '7',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
        ],
        [
            'status' => 'completed',
            'result_json' => ['ok' => true],
        ],
    );

    expect($first['status'])->toBe('processing')
        ->and($second['status'])->toBe('completed')
        ->and($second['result_json'])->toBe(['ok' => true])
        ->and($second['id'])->toBe($first['id'])
        ->and($gateway->findWhere([
            'tenant_id' => 't1',
            'actor_type' => 'user',
            'actor_id' => '7',
            'capability_name' => 'create-invoice',
            'idempotency_key' => 'k1',
        ]))->toHaveCount(1);
});

it('generates a primary key when insert omits id', function () {
    [, $gateway] = queryTableGatewayFixture();

    $row = $gateway->insert(['status' => 'pending', 'capability_name' => 'x']);

    expect($row['id'])->toBeString()->not->toBe('')
        ->and($gateway->find($row['id']))->not->toBeNull();
});

it('missing connection fails closed with a clear exception (no ArrayTableGateway fallback)', function () {
    expect(fn () => new QueryTableGateway(null, 'capabilities_approvals'))
        ->toThrow(InvalidArgumentException::class, 'ConnectionInterface');
});

it('empty table name fails closed', function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    expect(fn () => new QueryTableGateway($capsule->getConnection(), ''))
        ->toThrow(InvalidArgumentException::class, 'table');
});

it('has no dependency on messaging package', function () {
    $src = file_get_contents(dirname(__DIR__, 3).'/src/Persistence/QueryTableGateway.php');

    expect($src)->not->toContain('CapabilitiesMessaging')
        ->and($src)->not->toContain('Rawphp\\CapabilitiesMessaging');
});
