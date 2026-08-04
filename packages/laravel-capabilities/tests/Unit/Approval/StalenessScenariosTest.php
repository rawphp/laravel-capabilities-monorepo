<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('fail: accept fails without run when customer_deleted [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'customer_deleted'])]]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: accept stores terminal failed when customer_deleted [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'customer_deleted'])]]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('executed')->and($row['result_status'])->toBe('failed')->and($h['runCount']->value)->toBe(0);
});

it('fail: accept fails without run when customer_moved_tenant [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'customer_moved_tenant'])]]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: accept stores terminal failed when customer_moved_tenant [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'customer_moved_tenant'])]]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('executed')->and($row['result_status'])->toBe('failed')->and($h['runCount']->value)->toBe(0);
});

it('fail: accept fails without run when actor_lost_permission [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'actor_lost_permission'])]]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: accept stores terminal failed when actor_lost_permission [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'actor_lost_permission'])]]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('executed')->and($row['result_status'])->toBe('failed')->and($h['runCount']->value)->toBe(0);
});

it('fail: accept fails without run when resource_archived [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'resource_archived'])]]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: accept stores terminal failed when resource_archived [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'resource_archived'])]]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('executed')->and($row['result_status'])->toBe('failed')->and($h['runCount']->value)->toBe(0);
});

it('fail: accept fails without run when schema_incompatible_with_stored_input [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'schema_incompatible_with_stored_input'])]]);
    $r = $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: accept stores terminal failed when schema_incompatible_with_stored_input [D-006]', function () {
    $h = ApprovalHelpers::withPending(['record' => ['input_json' => array_merge(PipelineHelpers::validInput(), ['__stale' => true, '__reason' => 'schema_incompatible_with_stored_input'])]]);
    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());
    $row = $h['store']->find((string) $h['row']['id']);
    expect($row['status'])->toBe('executed')->and($row['result_status'])->toBe('failed')->and($h['runCount']->value)->toBe(0);
});
