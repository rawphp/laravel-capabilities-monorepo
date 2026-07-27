<?php

// REQ-010 fleshed unit tests for Errors/CodeCallerBulkTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it("happy: agent can surface validation_failed http=422 cli_exit=2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-agent');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('validation_failed'))->toBe(2);
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: agent can surface unauthenticated http=401 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-agent');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: agent can surface forbidden http=403 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden', 'req-agent');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and(ErrorCodeMap::cliExit('forbidden'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: agent can surface approval_required http=202 cli_exit=4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required', 'req-agent');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and(ErrorCodeMap::cliExit('approval_required'))->toBe(4);
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: agent can surface domain_error http=422 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error', 'req-agent');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('domain_error'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: agent can surface rate_limited http=429 cli_exit=6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-agent');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: agent can surface conflict http=409 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict', 'req-agent');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and(ErrorCodeMap::cliExit('conflict'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: agent can surface not_found http=404 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found', 'req-agent');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and(ErrorCodeMap::cliExit('not_found'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: agent can surface output_invalid http=500 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-agent');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('output_invalid'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: agent can surface internal http=500 cli_exit=1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal', 'req-agent');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('internal'))->toBe(1);
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});

it("happy: mcp can surface validation_failed http=422 cli_exit=2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('validation_failed'))->toBe(2);
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: mcp can surface unauthenticated http=401 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: mcp can surface forbidden http=403 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and(ErrorCodeMap::cliExit('forbidden'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: mcp can surface approval_required http=202 cli_exit=4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and(ErrorCodeMap::cliExit('approval_required'))->toBe(4);
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: mcp can surface domain_error http=422 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('domain_error'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: mcp can surface rate_limited http=429 cli_exit=6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: mcp can surface conflict http=409 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and(ErrorCodeMap::cliExit('conflict'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: mcp can surface not_found http=404 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and(ErrorCodeMap::cliExit('not_found'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: mcp can surface output_invalid http=500 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('output_invalid'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: mcp can surface internal http=500 cli_exit=1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal', 'req-mcp');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('internal'))->toBe(1);
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});

it("happy: http can surface validation_failed http=422 cli_exit=2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-http');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('validation_failed'))->toBe(2);
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: http can surface unauthenticated http=401 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-http');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: http can surface forbidden http=403 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden', 'req-http');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and(ErrorCodeMap::cliExit('forbidden'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: http can surface approval_required http=202 cli_exit=4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required', 'req-http');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and(ErrorCodeMap::cliExit('approval_required'))->toBe(4);
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: http can surface domain_error http=422 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error', 'req-http');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('domain_error'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: http can surface rate_limited http=429 cli_exit=6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-http');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: http can surface conflict http=409 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict', 'req-http');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and(ErrorCodeMap::cliExit('conflict'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: http can surface not_found http=404 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found', 'req-http');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and(ErrorCodeMap::cliExit('not_found'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: http can surface output_invalid http=500 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-http');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('output_invalid'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: http can surface internal http=500 cli_exit=1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal', 'req-http');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('internal'))->toBe(1);
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});

it("happy: cli can surface validation_failed http=422 cli_exit=2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-cli');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('validation_failed'))->toBe(2);
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: cli can surface unauthenticated http=401 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-cli');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: cli can surface forbidden http=403 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden', 'req-cli');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and(ErrorCodeMap::cliExit('forbidden'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: cli can surface approval_required http=202 cli_exit=4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required', 'req-cli');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and(ErrorCodeMap::cliExit('approval_required'))->toBe(4);
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: cli can surface domain_error http=422 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error', 'req-cli');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('domain_error'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: cli can surface rate_limited http=429 cli_exit=6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-cli');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: cli can surface conflict http=409 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict', 'req-cli');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and(ErrorCodeMap::cliExit('conflict'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: cli can surface not_found http=404 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found', 'req-cli');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and(ErrorCodeMap::cliExit('not_found'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: cli can surface output_invalid http=500 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-cli');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('output_invalid'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: cli can surface internal http=500 cli_exit=1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal', 'req-cli');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('internal'))->toBe(1);
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});

it("happy: job can surface validation_failed http=422 cli_exit=2 [D-018]", function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-job');
    expect(ErrorCodeMap::httpStatus('validation_failed'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('validation_failed'))->toBe(2);
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
});

it("happy: job can surface unauthenticated http=401 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-job');
    expect(ErrorCodeMap::httpStatus('unauthenticated'))->toBe(401)
        ->and(ErrorCodeMap::cliExit('unauthenticated'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
});

it("happy: job can surface forbidden http=403 cli_exit=3 [D-018]", function () {
    $r = ErrorHelpers::failure('forbidden', 'req-job');
    expect(ErrorCodeMap::httpStatus('forbidden'))->toBe(403)
        ->and(ErrorCodeMap::cliExit('forbidden'))->toBe(3);
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
});

it("happy: job can surface approval_required http=202 cli_exit=4 [D-018]", function () {
    $r = ErrorHelpers::failure('approval_required', 'req-job');
    expect(ErrorCodeMap::httpStatus('approval_required'))->toBe(202)
        ->and(ErrorCodeMap::cliExit('approval_required'))->toBe(4);
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
});

it("happy: job can surface domain_error http=422 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('domain_error', 'req-job');
    expect(ErrorCodeMap::httpStatus('domain_error'))->toBe(422)
        ->and(ErrorCodeMap::cliExit('domain_error'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
});

it("happy: job can surface rate_limited http=429 cli_exit=6 [D-018]", function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-job');
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
});

it("happy: job can surface conflict http=409 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('conflict', 'req-job');
    expect(ErrorCodeMap::httpStatus('conflict'))->toBe(409)
        ->and(ErrorCodeMap::cliExit('conflict'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
});

it("happy: job can surface not_found http=404 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('not_found', 'req-job');
    expect(ErrorCodeMap::httpStatus('not_found'))->toBe(404)
        ->and(ErrorCodeMap::cliExit('not_found'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
});

it("happy: job can surface output_invalid http=500 cli_exit=5 [D-018]", function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-job');
    expect(ErrorCodeMap::httpStatus('output_invalid'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('output_invalid'))->toBe(5);
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
});

it("happy: job can surface internal http=500 cli_exit=1 [D-018]", function () {
    $r = ErrorHelpers::failure('internal', 'req-job');
    expect(ErrorCodeMap::httpStatus('internal'))->toBe(500)
        ->and(ErrorCodeMap::cliExit('internal'))->toBe(1);
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
});
