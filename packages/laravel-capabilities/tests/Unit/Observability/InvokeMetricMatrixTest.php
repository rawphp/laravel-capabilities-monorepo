<?php

// REQ-014: Invoke metric matrix (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InvokeTelemetry;

it("happy: metric invoke caller=agent status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "ok", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "ok", "cap-x")))->toBe(1);
    expect(in_array("ok", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "validation_failed", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "validation_failed", "cap-x")))->toBe(1);
    expect(in_array("validation_failed", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "forbidden", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "forbidden", "cap-x")))->toBe(1);
    expect(in_array("forbidden", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=unauthenticated [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "unauthenticated", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "unauthenticated", "cap-x")))->toBe(1);
    expect(in_array("unauthenticated", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "approval_required", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "approval_required", "cap-x")))->toBe(1);
    expect(in_array("approval_required", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "rate_limited", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "rate_limited", "cap-x")))->toBe(1);
    expect(in_array("rate_limited", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "domain_error", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "domain_error", "cap-x")))->toBe(1);
    expect(in_array("domain_error", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=conflict [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "conflict", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "conflict", "cap-x")))->toBe(1);
    expect(in_array("conflict", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=not_found [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "not_found", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "not_found", "cap-x")))->toBe(1);
    expect(in_array("not_found", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=output_invalid [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "output_invalid", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "output_invalid", "cap-x")))->toBe(1);
    expect(in_array("output_invalid", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=agent status=internal [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "agent", "internal", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("agent", "internal", "cap-x")))->toBe(1);
    expect(in_array("internal", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "ok", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "ok", "cap-x")))->toBe(1);
    expect(in_array("ok", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "validation_failed", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "validation_failed", "cap-x")))->toBe(1);
    expect(in_array("validation_failed", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "forbidden", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "forbidden", "cap-x")))->toBe(1);
    expect(in_array("forbidden", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=unauthenticated [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "unauthenticated", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "unauthenticated", "cap-x")))->toBe(1);
    expect(in_array("unauthenticated", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "approval_required", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "approval_required", "cap-x")))->toBe(1);
    expect(in_array("approval_required", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "rate_limited", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "rate_limited", "cap-x")))->toBe(1);
    expect(in_array("rate_limited", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "domain_error", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "domain_error", "cap-x")))->toBe(1);
    expect(in_array("domain_error", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=conflict [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "conflict", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "conflict", "cap-x")))->toBe(1);
    expect(in_array("conflict", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=not_found [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "not_found", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "not_found", "cap-x")))->toBe(1);
    expect(in_array("not_found", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=output_invalid [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "output_invalid", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "output_invalid", "cap-x")))->toBe(1);
    expect(in_array("output_invalid", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=mcp status=internal [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "mcp", "internal", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("mcp", "internal", "cap-x")))->toBe(1);
    expect(in_array("internal", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "ok", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "ok", "cap-x")))->toBe(1);
    expect(in_array("ok", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "validation_failed", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "validation_failed", "cap-x")))->toBe(1);
    expect(in_array("validation_failed", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "forbidden", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "forbidden", "cap-x")))->toBe(1);
    expect(in_array("forbidden", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=unauthenticated [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "unauthenticated", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "unauthenticated", "cap-x")))->toBe(1);
    expect(in_array("unauthenticated", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "approval_required", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "approval_required", "cap-x")))->toBe(1);
    expect(in_array("approval_required", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "rate_limited", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "rate_limited", "cap-x")))->toBe(1);
    expect(in_array("rate_limited", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "domain_error", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "domain_error", "cap-x")))->toBe(1);
    expect(in_array("domain_error", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=conflict [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "conflict", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "conflict", "cap-x")))->toBe(1);
    expect(in_array("conflict", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=not_found [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "not_found", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "not_found", "cap-x")))->toBe(1);
    expect(in_array("not_found", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=output_invalid [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "output_invalid", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "output_invalid", "cap-x")))->toBe(1);
    expect(in_array("output_invalid", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=http status=internal [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "http", "internal", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("http", "internal", "cap-x")))->toBe(1);
    expect(in_array("internal", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "ok", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "ok", "cap-x")))->toBe(1);
    expect(in_array("ok", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "validation_failed", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "validation_failed", "cap-x")))->toBe(1);
    expect(in_array("validation_failed", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "forbidden", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "forbidden", "cap-x")))->toBe(1);
    expect(in_array("forbidden", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=unauthenticated [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "unauthenticated", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "unauthenticated", "cap-x")))->toBe(1);
    expect(in_array("unauthenticated", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "approval_required", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "approval_required", "cap-x")))->toBe(1);
    expect(in_array("approval_required", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "rate_limited", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "rate_limited", "cap-x")))->toBe(1);
    expect(in_array("rate_limited", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "domain_error", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "domain_error", "cap-x")))->toBe(1);
    expect(in_array("domain_error", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=conflict [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "conflict", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "conflict", "cap-x")))->toBe(1);
    expect(in_array("conflict", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=not_found [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "not_found", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "not_found", "cap-x")))->toBe(1);
    expect(in_array("not_found", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=output_invalid [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "output_invalid", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "output_invalid", "cap-x")))->toBe(1);
    expect(in_array("output_invalid", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=cli status=internal [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "cli", "internal", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("cli", "internal", "cap-x")))->toBe(1);
    expect(in_array("internal", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "ok", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "ok", "cap-x")))->toBe(1);
    expect(in_array("ok", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "validation_failed", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "validation_failed", "cap-x")))->toBe(1);
    expect(in_array("validation_failed", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "forbidden", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "forbidden", "cap-x")))->toBe(1);
    expect(in_array("forbidden", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=unauthenticated [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "unauthenticated", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "unauthenticated", "cap-x")))->toBe(1);
    expect(in_array("unauthenticated", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "approval_required", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "approval_required", "cap-x")))->toBe(1);
    expect(in_array("approval_required", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "rate_limited", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "rate_limited", "cap-x")))->toBe(1);
    expect(in_array("rate_limited", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "domain_error", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "domain_error", "cap-x")))->toBe(1);
    expect(in_array("domain_error", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=conflict [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "conflict", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "conflict", "cap-x")))->toBe(1);
    expect(in_array("conflict", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=not_found [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "not_found", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "not_found", "cap-x")))->toBe(1);
    expect(in_array("not_found", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=output_invalid [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "output_invalid", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "output_invalid", "cap-x")))->toBe(1);
    expect(in_array("output_invalid", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

it("happy: metric invoke caller=job status=internal [D-019]", function () {
    $m = new InMemoryMetrics;
    $tel = new InvokeTelemetry($m);
    $tel->recordInvoke("cap-x", "job", "internal", 3.0);
    expect($m->get(InvokeTelemetry::METRIC_INVOKE, InvokeTelemetry::invokeLabels("job", "internal", "cap-x")))->toBe(1);
    expect(in_array("internal", InvokeTelemetry::INVOKE_STATUSES, true))->toBeTrue();
});

