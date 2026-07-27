<?php

// REQ-014: Bus events correlation + afterCommit guidance (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Events\EventPayload;

it("happy: CapabilityInvoked carries capability name and correlation ids [D-010]", function () {
    $meta = EventPayload::meta([
        "name" => "create-invoice",
        "invocation_id" => "inv-1",
        "request_id" => "req-1",
        "caller" => "http",
    ]);
    $e = new CapabilityInvoked(capability: "create-invoice", caller: "http", data: ["ok" => true], meta: $meta);
    expect($e->capability)->toBe("create-invoice")->and($e->meta["invocation_id"])->toBe("inv-1")->and($e->meta["request_id"])->toBe("req-1");
});

it("fail: CapabilityInvoked is not dispatched before domain commit on success path by default [D-010]", function () {
    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityInvoked::class))->toBeTrue()
        ->and(CapabilityInvoked::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: CapabilityFailed carries capability name and correlation ids [D-010]", function () {
    $meta = EventPayload::meta([
        "name" => "create-invoice",
        "invocation_id" => "inv-1",
        "request_id" => "req-1",
        "caller" => "http",
    ]);
    $e = new CapabilityFailed(capability: "create-invoice", code: "forbidden", message: "no", caller: "http");
    expect($e->capability)->toBe("create-invoice")->and($e->code)->toBe("forbidden");
    expect($meta["invocation_id"])->toBe("inv-1");
});

it("fail: CapabilityFailed is not dispatched before domain commit on success path by default [D-010]", function () {
    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityFailed::class))->toBeTrue()
        ->and(CapabilityFailed::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: CapabilityApprovalRequested carries capability name and correlation ids [D-010]", function () {
    $meta = EventPayload::meta([
        "name" => "create-invoice",
        "invocation_id" => "inv-1",
        "request_id" => "req-1",
        "caller" => "http",
    ]);
    $e = new CapabilityApprovalRequested(capability: "create-invoice", approvalId: "a1", caller: "http", meta: $meta);
    expect($e->capability)->toBe("create-invoice")->and($e->approvalId)->toBe("a1")->and($e->meta["request_id"])->toBe("req-1");
});

it("fail: CapabilityApprovalRequested is not dispatched before domain commit on success path by default [D-010]", function () {
    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityApprovalRequested::class))->toBeTrue()
        ->and(CapabilityApprovalRequested::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: CapabilityApprovalDecided carries capability name and correlation ids [D-010]", function () {
    $meta = EventPayload::meta([
        "name" => "create-invoice",
        "invocation_id" => "inv-1",
        "request_id" => "req-1",
        "caller" => "http",
    ]);
    $e = new CapabilityApprovalDecided(capability: "create-invoice", approvalId: "a1", decision: "accept", decidedBy: "u1", meta: $meta);
    expect($e->capability)->toBe("create-invoice")->and($e->decision)->toBe("accept")->and($e->meta["invocation_id"])->toBe("inv-1");
});

it("fail: CapabilityApprovalDecided is not dispatched before domain commit on success path by default [D-010]", function () {
    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityApprovalDecided::class))->toBeTrue()
        ->and(CapabilityApprovalDecided::listenersShouldUseAfterCommit())->toBeTrue();
});

it("happy: CapabilityApprovalExecuted carries capability name and correlation ids [D-010]", function () {
    $meta = EventPayload::meta([
        "name" => "create-invoice",
        "invocation_id" => "inv-1",
        "request_id" => "req-1",
        "caller" => "http",
    ]);
    $e = new CapabilityApprovalExecuted(capability: "create-invoice", approvalId: "a1", via: "accept", meta: $meta);
    expect($e->capability)->toBe("create-invoice")->and($e->meta["request_id"])->toBe("req-1");
});

it("fail: CapabilityApprovalExecuted is not dispatched before domain commit on success path by default [D-010]", function () {
    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityApprovalExecuted::class))->toBeTrue()
        ->and(CapabilityApprovalExecuted::listenersShouldUseAfterCommit())->toBeTrue();
});

