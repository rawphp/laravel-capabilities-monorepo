<?php

// REQ-030: Core persistence migrations. Unit-only — no live DB.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\MigrationCatalog;

it('catalog lists approvals, idempotency, and audit_outbox tables', function () {
    expect(MigrationCatalog::tables())->toBe([
        MigrationCatalog::TABLE_APPROVALS,
        MigrationCatalog::TABLE_IDEMPOTENCY,
        MigrationCatalog::TABLE_AUDIT_OUTBOX,
    ]);
});

it('approvals schema includes lease and status fields for D-006', function () {
    $cols = MigrationCatalog::columns(MigrationCatalog::TABLE_APPROVALS);
    foreach (['id', 'status', 'tenant_id', 'execution_lease_until', 'execution_attempt', 'expires_at', 'input_json', 'idempotency_key'] as $col) {
        expect($cols)->toContain($col);
    }
});

it('idempotency schema has composite unique identity', function () {
    $def = MigrationCatalog::definitions()[MigrationCatalog::TABLE_IDEMPOTENCY];
    expect($def['unique'])->toContain([
        'tenant_id',
        'actor_type',
        'actor_id',
        'capability_name',
        'idempotency_key',
    ]);
    foreach (['request_hash', 'status', 'result_json', 'expires_at'] as $col) {
        expect($def['columns'])->toContain($col);
    }
});

it('audit_outbox schema supports queued audit delivery', function () {
    $cols = MigrationCatalog::columns(MigrationCatalog::TABLE_AUDIT_OUTBOX);
    foreach (['id', 'event', 'payload_json', 'status', 'attempts', 'available_at'] as $col) {
        expect($cols)->toContain($col);
    }
});

it('core catalog never includes messaging/telegram tables', function () {
    $joined = strtolower(implode(' ', MigrationCatalog::tables()));
    foreach (MigrationCatalog::forbiddenNameFragments() as $frag) {
        expect($joined)->not->toContain($frag);
    }
});

it('migration php files exist under package database/migrations', function () {
    $dir = dirname(__DIR__, 3).'/database/migrations';
    expect(is_dir($dir))->toBeTrue();
    $files = glob($dir.'/*.php') ?: [];
    expect($files)->not->toBeEmpty();

    $contents = '';
    foreach ($files as $file) {
        $contents .= file_get_contents($file) ?: '';
    }
    expect($contents)->toContain('MigrationCatalog::TABLE_APPROVALS')
        ->and($contents)->toContain('MigrationCatalog::TABLE_IDEMPOTENCY')
        ->and($contents)->toContain('MigrationCatalog::TABLE_AUDIT_OUTBOX')
        ->and(strtolower($contents))->not->toContain('telegram_user')
        ->and(count($files))->toBeGreaterThanOrEqual(3);
});

it('provider still publishes capabilities-migrations tag', function () {
    expect(ContainerBindings::hasPublishTag('capabilities-migrations'))->toBeTrue()
        ->and(ContainerBindings::PUBLISH_TAGS)->toContain('capabilities-migrations');
});
