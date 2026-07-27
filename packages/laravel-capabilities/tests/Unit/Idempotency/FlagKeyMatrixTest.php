<?php

// Spec-derived unit tests for D-005 flag × key × mutating matrix. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

it('happy: key honored when flag=optional key=True mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'optional']);
    $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'opt-1',
    ]));
    $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'opt-1',
    ]));
    expect($a->isOk())->toBeTrue()
        ->and($b->isReplay())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1);
});

it('edge: readOnly ignores when flag=optional key=True mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-x', CapabilityDefinition::IDEMPOTENT_OPTIONAL, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
    $guard = IdempotencyHelpers::guard();
    $out = $guard->lookup($def, IdempotencyHelpers::context(), 'k', 'hash');
    expect($out['action'])->toBe('continue');
});

it('edge: non-idempotent path when flag=optional key=False mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'optional']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($h['runCount']->value)->toBe(2);
});

it('edge: readOnly ignores when flag=optional key=False mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-y', CapabilityDefinition::IDEMPOTENT_OPTIONAL, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
});

it('happy: key honored when flag=required key=True mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'required']);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'req-1',
    ]));
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('edge: readOnly ignores when flag=required key=True mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-z', CapabilityDefinition::IDEMPOTENT_REQUIRED, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
});

it('fail: missing key rejected when flag=required key=False mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'required']);
    $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('validation_failed')
        ->and($h['runCount']->value)->toBe(0);
});

it('edge: readOnly ignores when flag=required key=False mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-w', CapabilityDefinition::IDEMPOTENT_REQUIRED, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
});

it('edge: keys ignored when flag=none key=True mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'none']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'ignored-1',
    ]));
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http', [
        'idempotency_key' => 'ignored-1',
    ]));
    expect($h['runCount']->value)->toBe(2);
});

it('edge: readOnly ignores when flag=none key=True mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-n', CapabilityDefinition::IDEMPOTENT_NONE, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse();
});

it('edge: keys ignored when flag=none key=False mutating=True [D-005]', function () {
    $h = IdempotencyHelpers::harness(['idempotent' => 'none']);
    $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), IdempotencyHelpers::options('http'));
    expect($h['runCount']->value)->toBe(1);
});

it('edge: readOnly ignores when flag=none key=False mutating=False [D-005]', function () {
    $def = IdempotencyHelpers::mutatingDefinition('list-m', CapabilityDefinition::IDEMPOTENT_NONE, readOnly: true);
    expect($def->shouldUseIdempotency())->toBeFalse()
        ->and($def->idempotent)->toBe(CapabilityDefinition::IDEMPOTENT_NONE);
    // silence unused import when CapabilityResult not needed
    expect(CapabilityResult::ok())->toBeInstanceOf(CapabilityResult::class);
});
