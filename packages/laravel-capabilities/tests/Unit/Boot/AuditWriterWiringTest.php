<?php

// L-006 / D-010: audit.driver=database gets a real writer; strict/required with no writer
// fails at boot; approval audit shares the writer. Unit-only (sqlite :memory: / fakes).

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseAuditWriter;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

function l006Connection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

it('makeAuditWriter builds a DatabaseAuditWriter on the outbox table for driver=database', function () {
    $config = BootHelpers::config(['audit' => ['driver' => 'database']]);

    $viaConnection = ContainerBindings::makeAuditWriter($config, null, l006Connection());
    $viaGateway = ContainerBindings::makeAuditWriter($config, new ArrayTableGateway);

    expect($viaConnection)->toBeInstanceOf(DatabaseAuditWriter::class)
        ->and($viaGateway)->toBeInstanceOf(DatabaseAuditWriter::class);

    $gateway = (new ReflectionClass(DatabaseAuditWriter::class))->getProperty('table')->getValue($viaConnection);
    expect($gateway)->toBeInstanceOf(QueryTableGateway::class)
        ->and($gateway->tableName())->toBe(MigrationCatalog::TABLE_AUDIT_OUTBOX);
});

it('makeAuditWriter returns null for the memory driver and for database without any connection', function () {
    expect(ContainerBindings::makeAuditWriter(BootHelpers::config(['audit' => ['driver' => 'memory']]), new ArrayTableGateway))->toBeNull()
        ->and(ContainerBindings::makeAuditWriter(BootHelpers::config(['audit' => ['driver' => 'database']])))->toBeNull();
});

it('makeRegistry wires the database audit writer into the pipeline and its approval manager', function () {
    $gateway = new ArrayTableGateway;
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'database'],
    ]);

    $registry = ContainerBindings::makeRegistry($config, $gateway);

    expect($registry->audit())->toBeInstanceOf(DatabaseAuditWriter::class);

    $registry->approvals()->request(ApprovalHelpers::pendingRecord());
    expect(array_column($registry->audit()->all(), 'event'))->toContain('approval.requested')
        ->and($gateway->findWhere(['event' => 'approval.requested']))->toHaveCount(1);
});

it('makeRegistry prefers an explicitly supplied AuditWriter', function () {
    $mine = new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $registry = ContainerBindings::makeRegistry(
        BootHelpers::config(['audit' => ['driver' => 'database']]),
        new ArrayTableGateway,
        auditWriter: $mine,
    );

    expect($registry->audit())->toBe($mine);
});

it('fails closed at boot when audit is strict or required but no writer can be built', function () {
    $strict = BootHelpers::config(['audit' => ['driver' => 'memory', 'mode' => 'strict'], 'approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]);
    $required = BootHelpers::config(['audit' => ['driver' => 'database', 'required' => true], 'approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]);
    $disabled = BootHelpers::config(['audit' => ['driver' => 'memory', 'mode' => 'strict', 'enabled' => false], 'approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]);
    $bestEffort = BootHelpers::config(['audit' => ['driver' => 'memory'], 'approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]);

    expect(fn () => ContainerBindings::makeRegistry($strict))->toThrow(BootException::class, 'AuditWriter')
        ->and(fn () => ContainerBindings::makeRegistry($required))->toThrow(BootException::class, 'AuditWriter')
        ->and(ContainerBindings::makeRegistry($disabled)->audit())->toBeNull()
        ->and(ContainerBindings::makeRegistry($bestEffort)->audit())->toBeNull();
});

it('withAuditWriter reaches the registry approval manager and survives withApprovalStore', function () {
    $writer = new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $registry = (new CapabilityRegistry)->withAuditWriter($writer);

    $registry->approvals()->request(ApprovalHelpers::pendingRecord());
    $registry->withApprovalStore(ApprovalHelpers::harness()['store']);
    $registry->approvals()->request(ApprovalHelpers::pendingRecord());

    expect(array_column($writer->all(), 'event'))->toBe(['approval.requested', 'approval.requested']);
});

it('provider: registry and ApprovalManager share one database audit writer by default', function () {
    $app = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]),
        [TableGateway::class => $gateway = new ArrayTableGateway],
    );

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);

    expect($registry->audit())->toBeInstanceOf(DatabaseAuditWriter::class);

    $approval->request(ApprovalHelpers::pendingRecord());
    expect($gateway->findWhere(['event' => 'approval.requested']))->toHaveCount(1)
        ->and($registry->audit()->all())->toHaveCount(1);
});

it('provider: a host-bound AuditWriter wins over the package writer', function () {
    $mine = new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')));
    $app = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory']]),
        [AuditWriter::class => $mine, TableGateway::class => new ArrayTableGateway],
    );

    $app->make(ApprovalManager::class)->request(ApprovalHelpers::pendingRecord());

    expect($app->make(CapabilityRegistry::class)->audit())->toBe($mine)
        ->and(array_column($mine->all(), 'event'))->toBe(['approval.requested']);
});

it('provider: strict audit with no connection and no writer fails boot loudly', function () {
    $app = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory'], 'audit' => ['mode' => 'strict']]),
    );

    expect(fn () => $app->make(CapabilityRegistry::class))->toThrow(BootException::class, 'AuditWriter');
});
