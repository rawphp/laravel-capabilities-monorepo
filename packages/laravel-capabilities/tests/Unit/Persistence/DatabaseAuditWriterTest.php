<?php

// L-006 / D-010: first-party durable AuditWriter over capabilities_audit_outbox. Unit-only.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseAuditWriter;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Support\FixedClock;

it('writes one outbox row per entry with the migration column shape', function () {
    $gateway = new ArrayTableGateway;
    $clock = new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00+00:00'));
    $writer = new DatabaseAuditWriter($gateway, $clock);

    $writer->write([
        'event' => 'capability.invoked',
        'capability_name' => 'create-invoice',
        'tenant_id' => 'acme',
        'caller' => 'http',
        'result' => ['ok' => true],
    ]);

    $rows = $gateway->findWhere(['event' => 'capability.invoked']);

    expect($writer)->toBeInstanceOf(AuditWriter::class)
        ->and($rows)->toHaveCount(1)
        ->and(array_keys($rows[0]))->toEqualCanonicalizing(MigrationCatalog::columns(MigrationCatalog::TABLE_AUDIT_OUTBOX))
        ->and($rows[0]['capability_name'])->toBe('create-invoice')
        ->and($rows[0]['tenant_id'])->toBe('acme')
        ->and($rows[0]['status'])->toBe('pending')
        ->and($rows[0]['attempts'])->toBe(0)
        ->and($rows[0]['available_at'])->toBe('2026-07-27T12:00:00+00:00')
        ->and($rows[0]['created_at'])->toBe('2026-07-27T12:00:00+00:00')
        ->and($rows[0]['payload_json']['caller'])->toBe('http')
        ->and($rows[0]['payload_json']['recorded_at'])->toBe('2026-07-27T12:00:00+00:00');
});

it('maps approval-manager entries (capability key) and tolerates missing tenant', function () {
    $gateway = new ArrayTableGateway;
    $writer = new DatabaseAuditWriter($gateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00+00:00')));

    $writer->write(['event' => 'approval.requested', 'approval_id' => 'a-1', 'capability' => 'void-invoice']);

    $row = $gateway->findWhere(['event' => 'approval.requested'])[0];
    expect($row['capability_name'])->toBe('void-invoice')
        ->and($row['tenant_id'])->toBeNull()
        ->and($row['payload_json']['approval_id'])->toBe('a-1');
});

it('all() returns the written entries oldest first', function () {
    $writer = new DatabaseAuditWriter(new ArrayTableGateway, new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00+00:00')));
    $writer->write(['event' => 'one']);
    $writer->write(['event' => 'two']);

    expect(array_column($writer->all(), 'event'))->toBe(['one', 'two']);
});

it('produces valid SQL for the real outbox table through QueryTableGateway (payload_json is JSON)', function () {
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $connection = $capsule->getConnection();
    $connection->statement(<<<'SQL'
        create table capabilities_audit_outbox (
            id text primary key not null,
            event text not null,
            capability_name text,
            tenant_id text,
            payload_json text not null,
            status text not null default 'pending',
            attempts integer not null default 0,
            available_at text,
            created_at text,
            updated_at text
        )
    SQL);
    $connection->enableQueryLog();

    $writer = new DatabaseAuditWriter(
        new QueryTableGateway($connection, MigrationCatalog::TABLE_AUDIT_OUTBOX),
        new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00+00:00')),
    );
    $writer->write(['event' => 'capability.failed', 'capability_name' => 'x', 'tenant_id' => 't1', 'payload' => ['code' => 'forbidden']]);

    $insert = collect($connection->getQueryLog())->first(fn (array $q) => str_starts_with($q['query'], 'insert'));
    $json = array_values(array_filter($insert['bindings'], fn ($b) => is_string($b) && str_starts_with($b, '{')));

    expect($json)->toHaveCount(1)
        ->and(json_decode($json[0], true, flags: JSON_THROW_ON_ERROR)['payload']['code'])->toBe('forbidden')
        ->and($insert['bindings'])->toContain('2026-07-27 12:00:00')
        ->and($writer->all())->toHaveCount(1)
        ->and($writer->all()[0]['event'])->toBe('capability.failed');
});
