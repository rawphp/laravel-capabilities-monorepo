<?php

declare(strict_types=1);

// InMemoryTracer disabled and hashed-attribute paths.

use Rawphp\Capabilities\Observability\InMemoryTracer;

it('InMemoryTracer skips spans when disabled and hashes sensitive attributes when enabled', function () {
    $trOff = new InMemoryTracer(false);
    expect($trOff->startSpan('n'))->toBe('disabled');
    $trOff->setAttributes('disabled', ['a' => 1]);
    $trOff->endSpan('disabled', 'ok');
    expect($trOff->spans())->toBe([])->and($trOff->lastSpan())->toBeNull();

    $tr = new InMemoryTracer(true, hashSensitive: true);
    $id = $tr->startSpan('invoke', ['tenant_id' => 't-secret', 'other' => 'plain']);
    $tr->setAttributes($id, ['idempotency_key' => 'ik', 'actor_id' => 'u1']);
    $tr->endSpan($id, 'ok');
    $tr->setAttributes('missing', ['x' => 1]);
    $tr->endSpan('missing', 'x');
    $last = $tr->lastSpan();
    expect($last['ended'])->toBeTrue()
        ->and($last['attributes']['tenant_id'])->toStartWith('sha256:')
        ->and($last['attributes']['other'])->toBe('plain');
});
