<?php

// REQ-050: Illuminate query TableGateway. Unit-only (sqlite :memory: / null connection).

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
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
            execution_lease_until text,
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

// REQ-069 / L-007: atomic lease-free conditional update (SQL-side predicate).
it('updateWhereLeaseFree claims only when lease null empty or expired', function () {
    [, $gateway] = queryTableGatewayFixture();
    $gateway->insert([
        'id' => 'lease-1',
        'status' => 'approved',
        'capability_name' => 'x',
        'execution_lease_until' => null,
        'execution_attempt' => 0,
    ]);
    $gateway->insert([
        'id' => 'lease-2',
        'status' => 'approved',
        'capability_name' => 'x',
        'execution_lease_until' => '2026-07-27T12:05:00Z',
        'execution_attempt' => 0,
    ]);
    $gateway->insert([
        'id' => 'lease-3',
        'status' => 'approved',
        'capability_name' => 'x',
        'execution_lease_until' => '2026-07-27T11:00:00Z',
        'execution_attempt' => 0,
    ]);

    $now = '2026-07-27T12:00:00Z';
    $free = $gateway->updateWhereLeaseFree(
        ['id' => 'lease-1', 'status' => 'approved'],
        'execution_lease_until',
        $now,
        ['execution_lease_until' => '2026-07-27T12:05:00Z', 'execution_attempt' => 1],
    );
    $held = $gateway->updateWhereLeaseFree(
        ['id' => 'lease-2', 'status' => 'approved'],
        'execution_lease_until',
        $now,
        ['execution_lease_until' => '2026-07-27T12:10:00Z', 'execution_attempt' => 1],
    );
    $expired = $gateway->updateWhereLeaseFree(
        ['id' => 'lease-3', 'status' => 'approved'],
        'execution_lease_until',
        $now,
        ['execution_lease_until' => '2026-07-27T12:05:00Z', 'execution_attempt' => 1],
    );
    $secondOnFree = $gateway->updateWhereLeaseFree(
        ['id' => 'lease-1', 'status' => 'approved'],
        'execution_lease_until',
        $now,
        ['execution_lease_until' => '2026-07-27T12:10:00Z', 'execution_attempt' => 2],
    );

    expect($free)->not->toBeNull()
        ->and($free['execution_attempt'])->toBe(1)
        ->and($held)->toBeNull()
        ->and($expired)->not->toBeNull()
        ->and($expired['execution_attempt'])->toBe(1)
        ->and($secondOnFree)->toBeNull()
        ->and($gateway->find('lease-1')['execution_attempt'])->toBe(1)
        ->and($gateway->find('lease-2')['execution_attempt'])->toBe(0);
});

it('insertIfAbsent inserts once and returns null when the unique identity is already held', function () {
    [, $gateway] = queryTableGatewayFixture(table: 'capabilities_idempotency');
    $identity = [
        'tenant_id' => 't1',
        'actor_type' => 'user',
        'actor_id' => '7',
        'capability_name' => 'create-invoice',
        'idempotency_key' => 'k1',
    ];

    $first = $gateway->insertIfAbsent($identity, ['status' => 'processing']);
    $second = $gateway->insertIfAbsent($identity, ['status' => 'completed']);

    expect($first)->not->toBeNull()
        ->and($first['status'])->toBe('processing')
        ->and($second)->toBeNull()
        ->and($gateway->findWhere($identity))->toHaveCount(1)
        ->and($gateway->findWhere($identity)[0]['status'])->toBe('processing');
});

it('insertIfAbsent rethrows database errors other than a unique violation', function () {
    [, $gateway] = queryTableGatewayFixture(table: 'capabilities_idempotency');

    expect(fn () => $gateway->insertIfAbsent(['idempotency_key' => 'k1'], ['no_such_column' => 'x']))
        ->toThrow(QueryException::class);
});

// L-012: SQL shape must be valid on MySQL/Postgres, not only SQLite. Proven on the
// query log (bindings + predicate text) — no external database.
it('L-012: scalar values bound to JSON columns are valid JSON documents', function () {
    [$connection, $gateway] = queryTableGatewayFixture(columnMap: ['scope' => 'scope_json']);
    $connection->enableQueryLog();

    $row = $gateway->insert(['id' => 'j-1', 'status' => 'pending', 'capability_name' => 'x', 'scope' => 'acme']);

    $insert = collect($connection->getQueryLog())->first(fn (array $q) => str_starts_with($q['query'], 'insert'));
    $jsonBindings = array_filter($insert['bindings'], fn ($b) => is_string($b) && str_contains($b, 'acme'));

    expect($jsonBindings)->toHaveCount(1)
        ->and(array_values($jsonBindings)[0])->toBe('"acme"')
        ->and(json_decode(array_values($jsonBindings)[0], flags: JSON_THROW_ON_ERROR))->toBe('acme')
        ->and($row['scope'])->toBe('acme')
        ->and($gateway->find('j-1')['scope'])->toBe('acme');
});

it('L-012: lease-free predicate never compares the timestamp column with an empty string', function () {
    [$connection, $gateway] = queryTableGatewayFixture();
    $gateway->insert(['id' => 'l-1', 'status' => 'approved', 'capability_name' => 'x']);
    $connection->enableQueryLog();

    $gateway->updateWhereLeaseFree(
        ['id' => 'l-1', 'status' => 'approved'],
        'execution_lease_until',
        '2026-07-27T12:00:00+00:00',
        ['execution_attempt' => 1],
    );

    $update = collect($connection->getQueryLog())->first(fn (array $q) => str_starts_with($q['query'], 'update'));

    expect($update['query'])->toContain('is null')
        ->and($update['query'])->toContain('<=')
        ->and($update['query'])->not->toMatch('/"execution_lease_until" = \?/')
        ->and($update['bindings'])->not->toContain('');
});

it('L-012: timestamps are written in portable Y-m-d H:i:s form and read back as DATE_ATOM', function () {
    [$connection, $gateway] = queryTableGatewayFixture();
    $connection->enableQueryLog();
    $tz = new DateTimeZone(date_default_timezone_get());
    $expected = (new DateTimeImmutable('2026-07-27T12:05:00+02:00'))->setTimezone($tz);

    $row = $gateway->insert([
        'id' => 't-1',
        'status' => 'approved',
        'capability_name' => 'x',
        'execution_lease_until' => '2026-07-27T12:05:00+02:00',
    ]);

    $insert = collect($connection->getQueryLog())->first(fn (array $q) => str_starts_with($q['query'], 'insert'));

    expect($insert['bindings'])->toContain($expected->format('Y-m-d H:i:s'))
        ->and($insert['bindings'])->not->toContain('2026-07-27T12:05:00+02:00')
        ->and($row['execution_lease_until'])->toBe($expected->format(DATE_ATOM))
        ->and($gateway->find('t-1')['execution_lease_until'])->toBe($expected->format(DATE_ATOM));
});

it('L-012: lease comparison operand uses the same portable timestamp form as the column', function () {
    [$connection, $gateway] = queryTableGatewayFixture();
    $gateway->insert([
        'id' => 'l-2',
        'status' => 'approved',
        'capability_name' => 'x',
        'execution_lease_until' => '2026-07-27T12:05:00+00:00',
    ]);
    $connection->enableQueryLog();

    $held = $gateway->updateWhereLeaseFree(['id' => 'l-2', 'status' => 'approved'], 'execution_lease_until', '2026-07-27T12:00:00+00:00', ['execution_attempt' => 1]);
    $free = $gateway->updateWhereLeaseFree(['id' => 'l-2', 'status' => 'approved'], 'execution_lease_until', '2026-07-27T12:06:00+00:00', ['execution_attempt' => 2]);

    $updates = collect($connection->getQueryLog())->filter(fn (array $q) => str_starts_with($q['query'], 'update'))->values();
    $tz = new DateTimeZone(date_default_timezone_get());

    expect($held)->toBeNull()
        ->and($free)->not->toBeNull()
        ->and($updates[0]['bindings'])->toContain((new DateTimeImmutable('2026-07-27T12:00:00+00:00'))->setTimezone($tz)->format('Y-m-d H:i:s'));
});
