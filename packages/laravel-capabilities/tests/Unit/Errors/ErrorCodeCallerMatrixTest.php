<?php

// REQ-010 fleshed unit tests for Errors/ErrorCodeCallerMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ErrorHelpers;

it('happy: code validation_failed producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code validation_failed for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code validation_failed producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code validation_failed for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code validation_failed producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code validation_failed for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code validation_failed producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code validation_failed for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code validation_failed producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'validation_failed');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code validation_failed for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('validation_failed');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code unauthenticated producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code unauthenticated for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code unauthenticated producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code unauthenticated for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code unauthenticated producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code unauthenticated for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code unauthenticated producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code unauthenticated for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code unauthenticated producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'unauthenticated');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code unauthenticated for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('unauthenticated');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code forbidden producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code forbidden for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code forbidden producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code forbidden for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code forbidden producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code forbidden for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code forbidden producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code forbidden for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code forbidden producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'forbidden');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code forbidden for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('forbidden');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code approval_required producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code approval_required for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code approval_required producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code approval_required for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code approval_required producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code approval_required for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code approval_required producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code approval_required for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code approval_required producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'approval_required');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code approval_required for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('approval_required');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code domain_error producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code domain_error for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code domain_error producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code domain_error for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code domain_error producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code domain_error for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code domain_error producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code domain_error for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code domain_error producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'domain_error');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code domain_error for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('domain_error');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code rate_limited producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code rate_limited for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code rate_limited producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code rate_limited for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code rate_limited producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code rate_limited for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code rate_limited producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code rate_limited for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code rate_limited producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'rate_limited');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code rate_limited for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('rate_limited');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code conflict producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('conflict', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code conflict for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code conflict producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('conflict', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code conflict for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code conflict producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('conflict', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code conflict for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code conflict producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('conflict', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code conflict for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code conflict producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('conflict', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'conflict');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code conflict for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('conflict');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code not_found producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('not_found', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code not_found for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code not_found producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('not_found', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code not_found for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code not_found producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('not_found', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code not_found for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code not_found producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('not_found', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code not_found for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code not_found producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('not_found', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'not_found');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code not_found for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('not_found');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code output_invalid producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code output_invalid for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code output_invalid producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code output_invalid for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code output_invalid producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code output_invalid for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code output_invalid producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code output_invalid for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code output_invalid producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'output_invalid');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code output_invalid for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('output_invalid');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code internal producible for caller agent maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('internal', 'req-agent');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
    expect($r->error['request_id'] ?? null)->toBe('req-agent');
});

it('fail: code internal for caller agent is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code internal producible for caller mcp maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('internal', 'req-mcp');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
    expect($r->error['request_id'] ?? null)->toBe('req-mcp');
});

it('fail: code internal for caller mcp is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code internal producible for caller http maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('internal', 'req-http');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
    expect($r->error['request_id'] ?? null)->toBe('req-http');
});

it('fail: code internal for caller http is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code internal producible for caller cli maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('internal', 'req-cli');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
    expect($r->error['request_id'] ?? null)->toBe('req-cli');
});

it('fail: code internal for caller cli is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertNotUnstructured($r);
});

it('happy: code internal producible for caller job maps stable envelope [D-018]', function () {
    $r = ErrorHelpers::failure('internal', 'req-job');
    ErrorHelpers::assertStructuredEnvelope($r, 'internal');
    expect($r->error['request_id'] ?? null)->toBe('req-job');
});

it('fail: code internal for caller job is not unstructured string only [D-018]', function () {
    $r = ErrorHelpers::failure('internal');
    ErrorHelpers::assertNotUnstructured($r);
});
