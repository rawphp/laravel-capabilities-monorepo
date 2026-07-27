<?php

// Spec-derived unit tests for D-005 status × hash matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

function statusHashSetup(string $status, ?string $storedHash): array
{
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hashA = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    $hashB = IdempotencyHelpers::hash(IdempotencyHelpers::inputB());

    IdempotencyHelpers::seedRow($store, [
        'status' => $status,
        'request_hash' => $storedHash === 'same' ? $hashA : ($storedHash === 'different' ? $hashB : null),
        'result_json' => $status === 'failed'
            ? CapabilityResult::failure('validation_failed', 'bad')->toArray()
            : ($status === 'completed'
                ? CapabilityResult::success(['invoice_id' => 1])->toArray()
                : null),
        'actor_id' => '1',
    ]);

    return compact('guard', 'def', 'ctx', 'hashA', 'hashB', 'store');
}

it('edge: status=processing hash=same too early or conflict [D-005]', function () {
    $s = statusHashSetup('processing', 'same');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('busy')
        ->and($out['result']->errorCode())->toBe('conflict');
});

it('edge: status=processing hash=different too early or conflict [D-005]', function () {
    $s = statusHashSetup('processing', 'different');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('busy');
});

it('edge: status=processing hash=n/a too early or conflict [D-005]', function () {
    $s = statusHashSetup('processing', 'n/a');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('busy');
});

it('happy: status=completed hash=same replays [D-005]', function () {
    $s = statusHashSetup('completed', 'same');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('replay')
        ->and($out['result']->isOk())->toBeTrue()
        ->and($out['result']->isReplay())->toBeTrue();
});

it('fail: status=completed hash=different conflicts [D-005]', function () {
    $s = statusHashSetup('completed', 'different');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('conflict')
        ->and($out['result']->errorCode())->toBe('conflict');
});

it('edge: status=completed hash=n/a [D-005]', function () {
    $s = statusHashSetup('completed', 'n/a');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    // null stored hash → treat as same identity, replay completed
    expect($out['action'])->toBe('replay')
        ->and($out['result']->isOk())->toBeTrue();
});

it('edge: status=failed hash=same replays failure by default [D-005]', function () {
    $s = statusHashSetup('failed', 'same');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('replay')
        ->and($out['result']->isFailed())->toBeTrue()
        ->and($out['result']->isReplay())->toBeTrue();
});

it('edge: status=failed hash=different replays failure by default [D-005]', function () {
    $s = statusHashSetup('failed', 'different');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('replay')
        ->and($out['result']->isFailed())->toBeTrue();
});

it('edge: status=failed hash=n/a replays failure by default [D-005]', function () {
    $s = statusHashSetup('failed', 'n/a');
    $out = $s['guard']->lookup($s['def'], $s['ctx'], 'key-1', $s['hashA']);
    expect($out['action'])->toBe('replay')
        ->and($out['result']->isFailed())->toBeTrue();
});

it('happy: status=missing hash=same runs non-idempotent or first insert [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $def = IdempotencyHelpers::mutatingDefinition();
    $ctx = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
    $out = $guard->lookup($def, $ctx, 'fresh-key', $hash);
    expect($out['action'])->toBe('continue');
    $row = $store->find('tenant-1', 'user', '1', 'create-invoice', 'fresh-key');
    expect($row)->not->toBeNull()->and($row['status'])->toBe('processing')
        ->and($row['request_hash'])->toBe($hash);
});

it('happy: status=missing hash=different runs non-idempotent or first insert [D-005]', function () {
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $out = $guard->lookup(
        IdempotencyHelpers::mutatingDefinition(),
        IdempotencyHelpers::context(),
        'fresh-key-2',
        IdempotencyHelpers::hash(IdempotencyHelpers::inputB()),
    );
    expect($out['action'])->toBe('continue');
});

it('happy: status=missing hash=n/a runs non-idempotent or first insert [D-005]', function () {
    $guard = IdempotencyHelpers::guard();
    // No key → non-idempotent continue without store write
    $out = $guard->lookup(
        IdempotencyHelpers::mutatingDefinition(),
        IdempotencyHelpers::context(),
        null,
        IdempotencyHelpers::hash(IdempotencyHelpers::inputA()),
    );
    expect($out['action'])->toBe('continue');
});
