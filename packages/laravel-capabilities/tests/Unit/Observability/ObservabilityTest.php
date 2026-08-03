<?php

// REQ-014: Observability metrics and spans (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\InvokeTelemetry;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;

it('happy: metric capabilities_invoke_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordInvoke('c', 'http', 'ok');
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels('http', 'ok', 'c')))->toBe(1);
});

it('happy: metric approval_required_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordInvoke('c', 'http', 'approval_required');
    expect($m->get(InvokeTelemetry::METRIC_APPROVAL_REQUIRED, ['caller' => 'http']))->toBe(1);
});

it('happy: metric authz_deny_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordInvoke('c', 'cli', 'forbidden');
    expect($m->get(InvokeTelemetry::METRIC_AUTHZ_DENY, ['caller' => 'cli']))->toBe(1);
});

it('happy: metric rate_limited_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordInvoke('c', 'agent', 'rate_limited');
    expect($m->get(InvokeTelemetry::METRIC_RATE_LIMITED, ['caller' => 'agent']))->toBe(1);
});

it('happy: metric idempotent_replay_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordIdempotentReplay('c', 'http');
    expect($m->get(InvokeTelemetry::METRIC_IDEMPOTENT_REPLAY, ['capability' => 'c', 'caller' => 'http']))->toBe(1);
});

it('happy: metric approvals_stuck_approved_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordStuckApproved(2);
    expect($m->get(InvokeTelemetry::METRIC_STUCK_APPROVED))->toBe(2);
});

it('happy: metric approvals_resume_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordResume('executed_ok');
    expect($m->get(InvokeTelemetry::METRIC_RESUME, ['result' => 'executed_ok']))->toBe(1);
});

it('happy: metric approvals_accept_total incremented or sampled on matching condition [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('replay');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'replay']))->toBe(1);
});

it('happy: latency histogram recorded [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordInvoke('c', 'http', 'ok', 42.0);
    expect($m->histogramSamples(InvokeTelemetry::METRIC_LATENCY, ['capability' => 'c', 'caller' => 'http']))->toBe([42.0]);
});

it('happy: span capabilities.invoke attributes set [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'http', 'ok', 1.0, [
        'surface' => 'http',
        'tenant_id' => 't1',
        'actor_type' => 'user',
    ]);
    $span = $tr->lastSpan();
    expect($span['name'])->toBe(InvokeTelemetry::SPAN_INVOKE)
        ->and($span['attributes']['capability'])->toBe('c')
        ->and($span['attributes']['caller'])->toBe('http')
        ->and($span['ended'])->toBeTrue();
});

it('edge: span attribute capability present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['capability' => 'val-capability', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('capability');
    expect(in_array('capability', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute caller present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['caller' => 'val-caller', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('caller');
    expect(in_array('caller', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute surface present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['surface' => 'val-surface', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('surface');
    expect(in_array('surface', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute tenant_id present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['tenant_id' => 'val-tenant_id', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('tenant_id');
    expect(in_array('tenant_id', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute actor_type present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['actor_type' => 'val-actor_type', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('actor_type');
    expect(in_array('actor_type', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute approval_id present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['approval_id' => 'val-approval_id', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('approval_id');
    expect(in_array('approval_id', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: span attribute idempotency_key present when applicable [D-019]', function () {
    $tr = new InMemoryTracer;
    (new InvokeTelemetry(new InMemoryMetrics, $tr))->recordInvoke('c', 'mcp', 'ok', 0.0, ['idempotency_key' => 'val-idempotency_key', 'surface' => 'mcp']);
    expect($tr->lastSpan()['attributes'])->toHaveKey('idempotency_key');
    expect(in_array('idempotency_key', InvokeTelemetry::SPAN_ATTRIBUTES, true))->toBeTrue();
});

it('edge: Metrics Tracer contracts fall back to log when no driver [D-019]', function () {
    $m = new LogFallbackMetrics(true);
    $m->increment('capabilities_invoke_total', 1, ['caller' => 'http', 'status' => 'ok']);
    expect($m->logLines())->not->toBeEmpty()
        ->and($m->get('capabilities_invoke_total', ['caller' => 'http', 'status' => 'ok']))->toBe(1);
});

it('edge: observability metrics false disables metric emission [D-019]', function () {
    $m = new InMemoryMetrics(false);
    $m->increment('capabilities_invoke_total', 1, ['caller' => 'http', 'status' => 'ok']);
    expect($m->enabled())->toBeFalse()
        ->and($m->get('capabilities_invoke_total', ['caller' => 'http', 'status' => 'ok']))->toBe(0);
});

it('edge: observability tracing false disables spans [D-019]', function () {
    $tr = new InMemoryTracer(false);
    $id = $tr->startSpan('capabilities.invoke', ['capability' => 'c']);
    expect($tr->enabled())->toBeFalse()->and($id)->toBe('disabled')->and($tr->spans())->toBeEmpty();
});
