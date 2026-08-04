<?php

// REQ-010 fleshed unit tests for Errors/SuccessMetaMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it('happy: success meta caller context agent idempotent_replay=False [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-agent', false);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(false)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context agent idempotent_replay=True [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-agent', true);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(true)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context mcp idempotent_replay=False [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-mcp', false);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(false)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context mcp idempotent_replay=True [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-mcp', true);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(true)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context http idempotent_replay=False [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-http', false);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(false)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context http idempotent_replay=True [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-http', true);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(true)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context cli idempotent_replay=False [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-cli', false);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(false)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context cli idempotent_replay=True [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-cli', true);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(true)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context job idempotent_replay=False [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-job', false);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(false)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});

it('happy: success meta caller context job idempotent_replay=True [D-018]', function () {
    $r = ErrorHelpers::success('create-invoice', 'req-job', true);
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay'])
        ->and($arr['meta']['idempotent_replay'])->toBe(true)
        ->and($arr['meta']['capability'])->toBe('create-invoice');
});
