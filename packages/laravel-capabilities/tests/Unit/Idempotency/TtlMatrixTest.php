<?php

// Spec-derived unit tests for D-005 TTL matrix. Unit-only, no database.

declare(strict_types=1);

use DateInterval;
use DateTimeImmutable;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

function ttlCase(int $hours): array
{
    $clock = IdempotencyHelpers::clock('2026-01-15T12:00:00+00:00');
    $store = IdempotencyHelpers::store($clock, $hours);
    $config = new IdempotencyConfig(ttlHours: $hours);
    $guard = IdempotencyHelpers::guard($store, $clock, $config);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    $lookup = $guard->lookup($def, $ctx, 'ttl-key', $hash);
    expect($lookup['action'])->toBe('continue');
    $row = $store->find('tenant-1', 'user', '1', 'create-invoice', 'ttl-key');
    expect($row)->not->toBeNull();

    $guard->storeResult(
        $def,
        $ctx,
        'ttl-key',
        $hash,
        CapabilityResult::success(['invoice_id' => 7]),
    );

    return compact('clock', 'store', 'guard', 'def', 'ctx', 'hash', 'hours', 'row');
}

it('edge: ttl_hours=1 applied to store [D-005]', function () {
    $s = ttlCase(1);
    $row = $s['store']->find('tenant-1', 'user', '1', 'create-invoice', 'ttl-key');
    $exp = new DateTimeImmutable($row['expires_at']);
    $created = new DateTimeImmutable($row['created_at']);
    expect($exp->getTimestamp() - $created->getTimestamp())->toBe(3600);
    expect($s['store']->ttlHours())->toBe(1);
});

it('happy: expired after 1h treated as new key [D-005]', function () {
    $s = ttlCase(1);
    $s['clock']->advance(new DateInterval('PT1H1S'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('continue');
});

it('happy: within 1h replay works [D-005]', function () {
    $s = ttlCase(1);
    $s['clock']->advance(new DateInterval('PT30M'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('replay')->and($out['result']->isOk())->toBeTrue();
});

it('edge: ttl_hours=24 applied to store [D-005]', function () {
    $s = ttlCase(24);
    $row = $s['store']->find('tenant-1', 'user', '1', 'create-invoice', 'ttl-key');
    $exp = new DateTimeImmutable($row['expires_at']);
    $created = new DateTimeImmutable($row['created_at']);
    expect($exp->getTimestamp() - $created->getTimestamp())->toBe(24 * 3600);
    expect(IdempotencyConfig::defaults()->ttlHours)->toBe(24);
});

it('happy: expired after 24h treated as new key [D-005]', function () {
    $s = ttlCase(24);
    $s['clock']->advance(new DateInterval('PT24H1S'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('continue');
});

it('happy: within 24h replay works [D-005]', function () {
    $s = ttlCase(24);
    $s['clock']->advance(new DateInterval('PT12H'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('replay');
});

it('edge: ttl_hours=168 applied to store [D-005]', function () {
    $s = ttlCase(168);
    $row = $s['store']->find('tenant-1', 'user', '1', 'create-invoice', 'ttl-key');
    $exp = new DateTimeImmutable($row['expires_at']);
    $created = new DateTimeImmutable($row['created_at']);
    expect($exp->getTimestamp() - $created->getTimestamp())->toBe(168 * 3600);
});

it('happy: expired after 168h treated as new key [D-005]', function () {
    $s = ttlCase(168);
    $s['clock']->advance(new DateInterval('PT168H1S'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('continue');
});

it('happy: within 168h replay works [D-005]', function () {
    $s = ttlCase(168);
    $s['clock']->advance(new DateInterval('PT100H'));
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'ttl-key', $s['hash']);
    expect($out['action'])->toBe('replay')->and($out['result']->isReplay())->toBeTrue();
});
