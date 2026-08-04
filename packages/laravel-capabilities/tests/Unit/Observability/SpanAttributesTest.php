<?php

// REQ-014: Span attributes (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\InvokeTelemetry;

it('happy: span sets capability when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['capability' => 'v-capability']);
    expect($tr->lastSpan()['attributes']['capability'] ?? null)->not->toBeNull();
});

it('edge: span may hash capability when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['capability' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['capability'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('capability', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets caller when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['caller' => 'v-caller']);
    expect($tr->lastSpan()['attributes']['caller'] ?? null)->not->toBeNull();
});

it('edge: span may hash caller when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['caller' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['caller'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('caller', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets surface when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['surface' => 'v-surface']);
    expect($tr->lastSpan()['attributes']['surface'] ?? null)->not->toBeNull();
});

it('edge: span may hash surface when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['surface' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['surface'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('surface', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets tenant_id when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['tenant_id' => 'v-tenant_id']);
    expect($tr->lastSpan()['attributes']['tenant_id'] ?? null)->not->toBeNull();
});

it('edge: span may hash tenant_id when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['tenant_id' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['tenant_id'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('tenant_id', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets actor_type when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['actor_type' => 'v-actor_type']);
    expect($tr->lastSpan()['attributes']['actor_type'] ?? null)->not->toBeNull();
});

it('edge: span may hash actor_type when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['actor_type' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['actor_type'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('actor_type', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets approval_id when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['approval_id' => 'v-approval_id']);
    expect($tr->lastSpan()['attributes']['approval_id'] ?? null)->not->toBeNull();
});

it('edge: span may hash approval_id when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['approval_id' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['approval_id'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('approval_id', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});

it('happy: span sets idempotency_key when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['idempotency_key' => 'v-idempotency_key']);
    expect($tr->lastSpan()['attributes']['idempotency_key'] ?? null)->not->toBeNull();
});

it('edge: span may hash idempotency_key when sensitive [D-019]', function () {
    $tr = new InMemoryTracer(hashSensitive: true);
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 0.0, ['idempotency_key' => 'secret-value']);
    $v = $tr->lastSpan()['attributes']['idempotency_key'] ?? null;
    expect($v)->not->toBeNull();
    if (in_array('idempotency_key', ['tenant_id', 'idempotency_key', 'approval_id'], true)) {
        expect((string) $v)->toStartWith('sha256:');
    }
});
