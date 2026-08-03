<?php

// REQ-010 fleshed unit tests for Errors/RetryableMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it('happy: code validation_failed retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect(ErrorCodeMap::retryableDefault('validation_failed'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code validation_failed retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code unauthenticated retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect(ErrorCodeMap::retryableDefault('unauthenticated'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code unauthenticated retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code forbidden retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    expect(ErrorCodeMap::retryableDefault('forbidden'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code forbidden retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code approval_required retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    expect(ErrorCodeMap::retryableDefault('approval_required'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code approval_required retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code domain_error retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    expect(ErrorCodeMap::retryableDefault('domain_error'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code domain_error retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code rate_limited retryable default True [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect(ErrorCodeMap::retryableDefault('rate_limited'))->toBe(true)
        ->and($r->error['retryable'] ?? null)->toBe(true);
});

it('edge: code rate_limited retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code conflict retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    expect(ErrorCodeMap::retryableDefault('conflict'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code conflict retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code not_found retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    expect(ErrorCodeMap::retryableDefault('not_found'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code not_found retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code output_invalid retryable default False [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect(ErrorCodeMap::retryableDefault('output_invalid'))->toBe(false)
        ->and($r->error['retryable'] ?? null)->toBe(false);
});

it('edge: code output_invalid retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});

it('happy: code internal retryable default True [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    expect(ErrorCodeMap::retryableDefault('internal'))->toBe(true)
        ->and($r->error['retryable'] ?? null)->toBe(true);
});

it('edge: code internal retryable flag always present [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error)->toHaveKey('retryable')
        ->and(is_bool($r->error['retryable']))->toBeTrue();
});
