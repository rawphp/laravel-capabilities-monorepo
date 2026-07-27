<?php

// REQ-010 fleshed unit tests for Errors/ErrorEnvelopeTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it("happy: success envelope ok true with data and meta request_id capability idempotent_replay [D-018]", function () {
    $r = ErrorHelpers::success();
    $arr = $r->toArray();
    expect($arr['ok'])->toBeTrue()
        ->and($arr)->toHaveKey('data')
        ->and($arr['meta'])->toHaveKeys(['request_id', 'capability', 'idempotent_replay']);
});

it("happy: error code validation_failed maps to HTTP 422 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and($r->error['http_status'] ?? null)->toBe(422);
});

it("happy: error code validation_failed maps to CLI exit 2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect(ErrorCodeMap::cliExit('validation_failed'))->toBe(2)
        ->and($r->error['cli_exit'] ?? null)->toBe(2);
});

it("happy: error envelope for validation_failed includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: error code unauthenticated maps to HTTP 401 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and($r->error['http_status'] ?? null)->toBe(401);
});

it("happy: error code unauthenticated maps to CLI exit 3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    expect(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3)
        ->and($r->error['cli_exit'] ?? null)->toBe(3);
});

it("happy: error envelope for unauthenticated includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: error code forbidden maps to HTTP 403 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and($r->error['http_status'] ?? null)->toBe(403);
});

it("happy: error code forbidden maps to CLI exit 3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect(ErrorCodeMap::cliExit('forbidden'))->toBe(3)
        ->and($r->error['cli_exit'] ?? null)->toBe(3);
});

it("happy: error envelope for forbidden includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: error code approval_required maps to HTTP 202 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and($r->error['http_status'] ?? null)->toBe(202);
});

it("happy: error code approval_required maps to CLI exit 4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect(ErrorCodeMap::cliExit('approval_required'))->toBe(4)
        ->and($r->error['cli_exit'] ?? null)->toBe(4);
});

it("happy: error envelope for approval_required includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: error code domain_error maps to HTTP 422 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and($r->error['http_status'] ?? null)->toBe(422);
});

it("happy: error code domain_error maps to CLI exit 5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    expect(ErrorCodeMap::cliExit('domain_error'))->toBe(5)
        ->and($r->error['cli_exit'] ?? null)->toBe(5);
});

it("happy: error envelope for domain_error includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: error code rate_limited maps to HTTP 429 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and($r->error['http_status'] ?? null)->toBe(429);
});

it("happy: error code rate_limited maps to CLI exit 6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    expect(ErrorCodeMap::cliExit('rate_limited'))->toBe(6)
        ->and($r->error['cli_exit'] ?? null)->toBe(6);
});

it("happy: error envelope for rate_limited includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: error code conflict maps to HTTP 409 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and($r->error['http_status'] ?? null)->toBe(409);
});

it("happy: error code conflict maps to CLI exit 5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    expect(ErrorCodeMap::cliExit('conflict'))->toBe(5)
        ->and($r->error['cli_exit'] ?? null)->toBe(5);
});

it("happy: error envelope for conflict includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: error code not_found maps to HTTP 404 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and($r->error['http_status'] ?? null)->toBe(404);
});

it("happy: error code not_found maps to CLI exit 5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    expect(ErrorCodeMap::cliExit('not_found'))->toBe(5)
        ->and($r->error['cli_exit'] ?? null)->toBe(5);
});

it("happy: error envelope for not_found includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: error code output_invalid maps to HTTP 500 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and($r->error['http_status'] ?? null)->toBe(500);
});

it("happy: error code output_invalid maps to CLI exit 5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    expect(ErrorCodeMap::cliExit('output_invalid'))->toBe(5)
        ->and($r->error['cli_exit'] ?? null)->toBe(5);
});

it("happy: error envelope for output_invalid includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: error code internal maps to HTTP 500 [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and($r->error['http_status'] ?? null)->toBe(500);
});

it("happy: error code internal maps to CLI exit 1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    expect(ErrorCodeMap::cliExit('internal'))->toBe(1)
        ->and($r->error['cli_exit'] ?? null)->toBe(1);
});

it("happy: error envelope for internal includes code message request_id retryable [D-018]", function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});

it("happy: validation_failed includes violations list [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    expect($r->error['violations'] ?? [])->not->toBeEmpty()
        ->and($r->error['violations'][0])->toHaveKey('field');
});

it("happy: approval_required includes approval_id [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required');
    expect($r->approvalId())->not->toBeNull();
});

it("happy: retryable flag present on envelope [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden');
    expect($r->error)->toHaveKey('retryable');
});

it("edge: CLI --json prints same envelope as HTTP [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed');
    $http = $r->toArray();
    $cli = ErrorHelpers::cliJson($r);
    expect($cli)->toBe($http)
        ->and(CliJsonEnvelope::exitCode($r))->toBe(ErrorCodeMap::cliExit('validation_failed'));
});

it("fail: ad-hoc unstructured error body is not returned for known failures [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
    expect($r->toArray()['error'])->toBeArray();
});
