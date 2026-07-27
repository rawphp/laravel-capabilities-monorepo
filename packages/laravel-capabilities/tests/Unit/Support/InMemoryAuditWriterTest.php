<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;

it('InMemoryAuditWriter implements AuditWriter and records entries for read-back', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00+00:00'));
    $writer = new InMemoryAuditWriter($clock);

    expect($writer)->toBeInstanceOf(AuditWriter::class);

    $writer->write([
        'event' => 'capability.invoked',
        'capability_name' => 'create-invoice',
        'tenant_id' => 'tenant-1',
        'actor_type' => 'user',
        'actor_id' => '42',
        'payload' => ['invoice_id' => 7],
    ]);

    $entries = $writer->all();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['event'])->toBe('capability.invoked')
        ->and($entries[0]['capability_name'])->toBe('create-invoice')
        ->and($entries[0])->toHaveKey('recorded_at');
});

it('InMemoryAuditWriter requires a Clock and fails loudly without it', function () {
    expect(fn () => new InMemoryAuditWriter())
        ->toThrow(ArgumentCountError::class);
});
