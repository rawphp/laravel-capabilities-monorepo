<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

dataset('non_pending_statuses', ['executed', 'rejected', 'expired', 'approved']);

it('fail: other-tenant accept on a non-pending approval is forbidden before its status leaks [D-006]', function (string $status) {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], $status);
    $before = $h['runCount']->value;

    $r = $h['manager']->accept((string) $row['id'], ApprovalHelpers::otherTenantUser());

    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('forbidden')
        ->and($r->meta)->not->toHaveKey('approval_replay')
        ->and($h['runCount']->value)->toBe($before);
})->with('non_pending_statuses');

it('fail: same-tenant user outside policy cannot replay an executed approval result [D-006]', function () {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');

    $r = $h['manager']->accept((string) $row['id'], ApprovalHelpers::randomUser());

    expect($r->errorCode())->toBe('forbidden')
        ->and($r->data)->toBeNull();
});

it('happy: authorized approver still replays an executed approval [D-006]', function () {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], 'executed');

    $r = $h['manager']->accept((string) $row['id'], ApprovalHelpers::requester());

    expect($r->meta['approval_replay'] ?? false)->toBeTrue();
});

it('edge: authorized approver still sees terminal status conflicts [D-006]', function (string $status, string $code) {
    $h = ApprovalHelpers::harness();
    $row = ApprovalHelpers::seedStatus($h['manager'], $status);

    $r = $h['manager']->accept((string) $row['id'], ApprovalHelpers::requester());

    expect($r->errorCode())->toBe($code);
})->with([
    ['rejected', 'conflict'],
    ['expired', 'expired'],
    ['approved', 'conflict'],
]);
