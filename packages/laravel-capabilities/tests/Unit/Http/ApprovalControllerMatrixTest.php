<?php

// REQ-011 fleshed unit tests for Http/ApprovalControllerMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\ApprovalController;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

it("happy: approval accept when authorized [D-006]", function () {
    $h = ApprovalHelpers::harness([
        'policy' => ApprovalPolicy::ANY_STAFF,
    ]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord([
        'requester_actor_id' => '10',
    ]));
    $controller = new ApprovalController($h['manager']);
    $approver = HttpHelpers::user(99);
    $approver->is_staff = true;

    $res = $controller->accept(
        HttpHelpers::authedRequest(['user' => $approver]),
        (string) $row['id'],
    );

    expect($res->errorCode())->not->toBe('unauthenticated')
        ->and($res->errorCode())->not->toBe('forbidden')
        ->and($res->isOk() || $res->errorCode() === null || $res->status < 400 || $res->isOk())->toBeTrue();
});

it("fail: approval accept when unauthorized [D-006]", function () {
    $h = ApprovalHelpers::harness([
        'policy' => ApprovalPolicy::REQUESTER,
    ]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord([
        'requester_actor_id' => '10',
    ]));
    $controller = new ApprovalController($h['manager']);
    $stranger = HttpHelpers::user(99);

    $res = $controller->accept(
        HttpHelpers::authedRequest(['user' => $stranger]),
        (string) $row['id'],
    );

    expect($res->errorCode())->toBe('forbidden')->and($res->status)->toBe(403);
});

it("fail: approval accept when unauthenticated [D-006]", function () {
    $h = ApprovalHelpers::harness();
    $row = $h['store']->put(ApprovalHelpers::pendingRecord());
    $controller = new ApprovalController($h['manager']);

    $res = $controller->accept(HttpHelpers::guestRequest(), (string) $row['id']);
    expect($res->errorCode())->toBe('unauthenticated');
});

it("fail: approval accept when system_actor [D-006]", function () {
    $h = ApprovalHelpers::harness(['policy' => ApprovalPolicy::ANY_STAFF]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord());
    $controller = new ApprovalController($h['manager']);

    $res = $controller->accept(
        HttpHelpers::authedRequest(['user' => SystemActor::named('billing-bot')]),
        (string) $row['id'],
    );
    expect($res->errorCode())->toBe('forbidden');
});

it("happy: approval reject when authorized [D-006]", function () {
    $h = ApprovalHelpers::harness(['policy' => ApprovalPolicy::ANY_STAFF]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord());
    $controller = new ApprovalController($h['manager']);
    $approver = HttpHelpers::user(99);
    $approver->is_staff = true;

    $res = $controller->reject(
        HttpHelpers::authedRequest(['user' => $approver, 'jsonBody' => ['reason' => 'nope']]),
        (string) $row['id'],
    );

    expect($res->errorCode())->not->toBe('unauthenticated')
        ->and($res->errorCode())->not->toBe('forbidden');
});

it("fail: approval reject when unauthorized [D-006]", function () {
    $h = ApprovalHelpers::harness(['policy' => ApprovalPolicy::REQUESTER]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord([
        'requester_actor_id' => '10',
    ]));
    $controller = new ApprovalController($h['manager']);

    $res = $controller->reject(
        HttpHelpers::authedRequest(['user' => HttpHelpers::user(99)]),
        (string) $row['id'],
    );
    expect($res->errorCode())->toBe('forbidden');
});

it("fail: approval reject when unauthenticated [D-006]", function () {
    $h = ApprovalHelpers::harness();
    $row = $h['store']->put(ApprovalHelpers::pendingRecord());
    $controller = new ApprovalController($h['manager']);

    $res = $controller->reject(HttpHelpers::guestRequest(), (string) $row['id']);
    expect($res->errorCode())->toBe('unauthenticated');
});

it("fail: approval reject when system_actor [D-006]", function () {
    $h = ApprovalHelpers::harness(['policy' => ApprovalPolicy::ANY_STAFF]);
    $row = $h['store']->put(ApprovalHelpers::pendingRecord());
    $controller = new ApprovalController($h['manager']);

    $res = $controller->reject(
        HttpHelpers::authedRequest(['user' => SystemActor::named('billing-bot')]),
        (string) $row['id'],
    );
    expect($res->errorCode())->toBe('forbidden');
});
