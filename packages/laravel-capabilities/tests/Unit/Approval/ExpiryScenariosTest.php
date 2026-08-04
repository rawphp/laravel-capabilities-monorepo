<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: pending past ttl becomes expired on_read [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_read [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_accept [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_accept [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_reject [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_reject [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});

it('happy: pending past ttl becomes expired on_sweeper [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    expect($h['manager']->find((string) $h['row']['id'])['status'])->toBe('expired');
});

it('fail: expired cannot accept on_sweeper [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 2);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->errorCode())->toBe('expired')->and($h['runCount']->value)->toBe(0);
});
