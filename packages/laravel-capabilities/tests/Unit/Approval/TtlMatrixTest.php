<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('edge: effective ttl computed when global=1 cap=None [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 1]);
    expect($h['manager']->effectiveTtlHours(null))->toBe(1);
});

it('happy: pending expires after effective ttl when global=1 cap=None [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=1 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 1]);
    expect($h['manager']->effectiveTtlHours(1))->toBe(1);
});

it('happy: pending expires after effective ttl when global=1 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'approval_ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=1 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 1]);
    expect($h['manager']->effectiveTtlHours(12))->toBe(1);
});

it('happy: pending expires after effective ttl when global=1 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'approval_ttl_hours' => 12]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=1 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 1]);
    expect($h['manager']->effectiveTtlHours(24))->toBe(1);
});

it('happy: pending expires after effective ttl when global=1 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 1, 'approval_ttl_hours' => 24]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=24 cap=None [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 24]);
    expect($h['manager']->effectiveTtlHours(null))->toBe(24);
});

it('happy: pending expires after effective ttl when global=24 cap=None [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 24]);
    ApprovalHelpers::advanceHours($h['clock'], 24);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=24 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 24]);
    expect($h['manager']->effectiveTtlHours(1))->toBe(1);
});

it('happy: pending expires after effective ttl when global=24 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 24, 'approval_ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=24 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 24]);
    expect($h['manager']->effectiveTtlHours(12))->toBe(12);
});

it('happy: pending expires after effective ttl when global=24 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 24, 'approval_ttl_hours' => 12]);
    ApprovalHelpers::advanceHours($h['clock'], 12);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=24 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 24]);
    expect($h['manager']->effectiveTtlHours(24))->toBe(24);
});

it('happy: pending expires after effective ttl when global=24 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 24, 'approval_ttl_hours' => 24]);
    ApprovalHelpers::advanceHours($h['clock'], 24);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=72 cap=None [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 72]);
    expect($h['manager']->effectiveTtlHours(null))->toBe(72);
});

it('happy: pending expires after effective ttl when global=72 cap=None [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 72]);
    ApprovalHelpers::advanceHours($h['clock'], 72);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=72 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 72]);
    expect($h['manager']->effectiveTtlHours(1))->toBe(1);
});

it('happy: pending expires after effective ttl when global=72 cap=1 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 72, 'approval_ttl_hours' => 1]);
    ApprovalHelpers::advanceHours($h['clock'], 1);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=72 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 72]);
    expect($h['manager']->effectiveTtlHours(12))->toBe(12);
});

it('happy: pending expires after effective ttl when global=72 cap=12 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 72, 'approval_ttl_hours' => 12]);
    ApprovalHelpers::advanceHours($h['clock'], 12);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});

it('edge: effective ttl computed when global=72 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::harness(['ttl_hours' => 72]);
    expect($h['manager']->effectiveTtlHours(24))->toBe(24);
});

it('happy: pending expires after effective ttl when global=72 cap=24 [D-006]', function () {
    $h = ApprovalHelpers::withPending(['ttl_hours' => 72, 'approval_ttl_hours' => 24]);
    ApprovalHelpers::advanceHours($h['clock'], 24);
    ApprovalHelpers::advance($h['clock'], 1);
    $row = $h['manager']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('expired');
});
