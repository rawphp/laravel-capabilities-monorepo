<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityResultAssertionException;

it('happy: result helper assertOk exists [RES-001]', function () {
    $result = CapabilityResult::ok(['invoice_id' => 1]);

    expect(method_exists($result, 'assertOk'))->toBeTrue()
        ->and($result->assertOk())->toBeInstanceOf(CapabilityResult::class)
        ->and($result->assertOk()->data)->toBe(['invoice_id' => 1]);
});

it('edge: result helper assertOk fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::failure('forbidden', 'no');

    expect(fn () => $result->assertOk())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertFailed exists [RES-001]', function () {
    $result = CapabilityResult::failure('domain_error', 'boom');

    expect(method_exists($result, 'assertFailed'))->toBeTrue()
        ->and($result->assertFailed())->toBe($result);
});

it('edge: result helper assertFailed fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::ok(['ok' => true]);

    expect(fn () => $result->assertFailed())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertForbidden exists [RES-001]', function () {
    $result = CapabilityResult::failure('forbidden', 'denied');

    expect(method_exists($result, 'assertForbidden'))->toBeTrue()
        ->and($result->assertForbidden())->toBe($result);
});

it('edge: result helper assertForbidden fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::failure('validation_failed', 'bad');

    expect(fn () => $result->assertForbidden())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertConflict exists [RES-001]', function () {
    $result = CapabilityResult::failure('conflict', 'key reuse');

    expect(method_exists($result, 'assertConflict'))->toBeTrue()
        ->and($result->assertConflict())->toBe($result);
});

it('edge: result helper assertConflict fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::ok([]);

    expect(fn () => $result->assertConflict())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertExpired exists [RES-001]', function () {
    $result = CapabilityResult::failure('expired', 'gone');

    expect(method_exists($result, 'assertExpired'))->toBeTrue()
        ->and($result->assertExpired())->toBe($result);
});

it('edge: result helper assertExpired fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::ok([]);

    expect(fn () => $result->assertExpired())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertApprovalRequired exists [RES-001]', function () {
    $result = CapabilityResult::approvalRequired('appr-1');

    expect(method_exists($result, 'assertApprovalRequired'))->toBeTrue()
        ->and($result->assertApprovalRequired())->toBe($result);
});

it('edge: result helper assertApprovalRequired fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::ok([]);

    expect(fn () => $result->assertApprovalRequired())->toThrow(CapabilityResultAssertionException::class);
});

it('happy: result helper assertReplay exists [RES-001]', function () {
    $result = CapabilityResult::ok(['invoice_id' => 9], ['idempotent_replay' => true]);

    expect(method_exists($result, 'assertReplay'))->toBeTrue()
        ->and($result->assertReplay())->toBe($result);
});

it('edge: result helper assertReplay fails test on mismatch [RES-001]', function () {
    $result = CapabilityResult::ok(['invoice_id' => 9], ['idempotent_replay' => false]);

    expect(fn () => $result->assertReplay())->toThrow(CapabilityResultAssertionException::class);
});
