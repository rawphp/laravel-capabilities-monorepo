<?php

// REQ-014: capabilities_invoke_total labels (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InvokeTelemetry;

it("happy: capabilities_invoke_total labels caller=agent status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "ok", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "ok", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "ok", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=agent status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "validation_failed", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "validation_failed", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "validation_failed", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=agent status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "forbidden", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "forbidden", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "forbidden", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=agent status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "approval_required", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "approval_required", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "approval_required", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=agent status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "rate_limited", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "rate_limited", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "rate_limited", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=agent status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "agent", "domain_error", 12.5);
    $labels = InvokeTelemetry::invokeLabels("agent", "domain_error", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "agent", "status" => "domain_error", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "ok", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "ok", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "ok", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "validation_failed", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "validation_failed", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "validation_failed", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "forbidden", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "forbidden", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "forbidden", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "approval_required", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "approval_required", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "approval_required", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "rate_limited", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "rate_limited", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "rate_limited", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=mcp status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "mcp", "domain_error", 12.5);
    $labels = InvokeTelemetry::invokeLabels("mcp", "domain_error", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "mcp", "status" => "domain_error", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "ok", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "ok", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "ok", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "validation_failed", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "validation_failed", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "validation_failed", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "forbidden", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "forbidden", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "forbidden", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "approval_required", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "approval_required", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "approval_required", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "rate_limited", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "rate_limited", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "rate_limited", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=http status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "http", "domain_error", 12.5);
    $labels = InvokeTelemetry::invokeLabels("http", "domain_error", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "http", "status" => "domain_error", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "ok", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "ok", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "ok", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "validation_failed", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "validation_failed", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "validation_failed", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "forbidden", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "forbidden", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "forbidden", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "approval_required", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "approval_required", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "approval_required", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "rate_limited", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "rate_limited", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "rate_limited", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=cli status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "cli", "domain_error", 12.5);
    $labels = InvokeTelemetry::invokeLabels("cli", "domain_error", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "cli", "status" => "domain_error", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=ok [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "ok", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "ok", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "ok", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=validation_failed [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "validation_failed", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "validation_failed", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "validation_failed", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=forbidden [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "forbidden", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "forbidden", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "forbidden", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=approval_required [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "approval_required", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "approval_required", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "approval_required", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=rate_limited [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "rate_limited", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "rate_limited", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "rate_limited", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

it("happy: capabilities_invoke_total labels caller=job status=domain_error [D-019]", function () {
    $m = new InMemoryMetrics;
    $t = new InvokeTelemetry($m);
    $t->recordInvoke("create-invoice", "job", "domain_error", 12.5);
    $labels = InvokeTelemetry::invokeLabels("job", "domain_error", "create-invoice");
    expect($labels)->toMatchArray(["caller" => "job", "status" => "domain_error", "capability" => "create-invoice"])
        ->and($m->get(InvokeTelemetry::METRIC_INVOKE, $labels))->toBe(1);
});

