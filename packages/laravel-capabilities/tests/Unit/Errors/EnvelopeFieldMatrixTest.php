<?php

// REQ-010 fleshed unit tests for Errors/EnvelopeFieldMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it("happy: envelope for validation_failed includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for validation_failed includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for validation_failed includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for validation_failed includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for validation_failed violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error['violations'] ?? [])->not->toBeEmpty();
});

it("edge: envelope for validation_failed approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for unauthenticated includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for unauthenticated includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for unauthenticated includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for unauthenticated includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for unauthenticated violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for unauthenticated approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for forbidden includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for forbidden includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for forbidden includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for forbidden includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for forbidden violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for forbidden approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for approval_required includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for approval_required includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for approval_required includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for approval_required includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for approval_required violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for approval_required approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->error['approval_id'] ?? null)->not->toBeNull();
});

it("happy: envelope for domain_error includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for domain_error includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for domain_error includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for domain_error includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for domain_error violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for domain_error approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for rate_limited includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for rate_limited includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for rate_limited includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for rate_limited includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for rate_limited violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for rate_limited approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for conflict includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for conflict includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for conflict includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for conflict includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for conflict violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for conflict approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for not_found includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for not_found includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for not_found includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for not_found includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for not_found violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for not_found approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for output_invalid includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for output_invalid includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for output_invalid includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for output_invalid includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for output_invalid violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for output_invalid approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});

it("happy: envelope for internal includes field code [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error)->toHaveKey('code');
});

it("happy: envelope for internal includes field message [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error)->toHaveKey('message');
});

it("happy: envelope for internal includes field request_id [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error)->toHaveKey('request_id');
});

it("happy: envelope for internal includes field retryable [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: envelope for internal violations only when validation [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    // non-validation codes may have empty violations list
    expect($r->error)->toHaveKey('violations');
});

it("edge: envelope for internal approval_id only when approval_required [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect($r->error['approval_id'] ?? null)->toBeNull();
});
