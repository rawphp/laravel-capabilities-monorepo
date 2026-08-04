<?php

// REQ-015: Parity cross-caller governance contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ParityHelpers as P;

it('fail: caller agent code validation_failed has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'validation_failed');
});

it('fail: caller agent code unauthenticated has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'unauthenticated');
});

it('fail: caller agent code forbidden has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'forbidden');
});

it('happy: caller agent code approval_required stores pending only not domain success [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'approval_required');
});

it('fail: caller agent code domain_error has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'domain_error');
});

it('fail: caller agent code rate_limited has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'rate_limited');
});

it('fail: caller agent code conflict has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'conflict');
});

it('fail: caller agent code not_found has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'not_found');
});

it('fail: caller agent code output_invalid has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'output_invalid');
});

it('fail: caller agent code internal has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('agent', 'internal');
});

it('fail: caller mcp code validation_failed has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'validation_failed');
});

it('fail: caller mcp code unauthenticated has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'unauthenticated');
});

it('fail: caller mcp code forbidden has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'forbidden');
});

it('happy: caller mcp code approval_required stores pending only not domain success [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'approval_required');
});

it('fail: caller mcp code domain_error has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'domain_error');
});

it('fail: caller mcp code rate_limited has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'rate_limited');
});

it('fail: caller mcp code conflict has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'conflict');
});

it('fail: caller mcp code not_found has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'not_found');
});

it('fail: caller mcp code output_invalid has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'output_invalid');
});

it('fail: caller mcp code internal has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('mcp', 'internal');
});

it('fail: caller http code validation_failed has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'validation_failed');
});

it('fail: caller http code unauthenticated has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'unauthenticated');
});

it('fail: caller http code forbidden has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'forbidden');
});

it('happy: caller http code approval_required stores pending only not domain success [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'approval_required');
});

it('fail: caller http code domain_error has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'domain_error');
});

it('fail: caller http code rate_limited has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'rate_limited');
});

it('fail: caller http code conflict has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'conflict');
});

it('fail: caller http code not_found has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'not_found');
});

it('fail: caller http code output_invalid has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'output_invalid');
});

it('fail: caller http code internal has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('http', 'internal');
});

it('fail: caller cli code validation_failed has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'validation_failed');
});

it('fail: caller cli code unauthenticated has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'unauthenticated');
});

it('fail: caller cli code forbidden has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'forbidden');
});

it('happy: caller cli code approval_required stores pending only not domain success [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'approval_required');
});

it('fail: caller cli code domain_error has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'domain_error');
});

it('fail: caller cli code rate_limited has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'rate_limited');
});

it('fail: caller cli code conflict has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'conflict');
});

it('fail: caller cli code not_found has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'not_found');
});

it('fail: caller cli code output_invalid has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'output_invalid');
});

it('fail: caller cli code internal has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('cli', 'internal');
});

it('fail: caller job code validation_failed has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'validation_failed');
});

it('fail: caller job code unauthenticated has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'unauthenticated');
});

it('fail: caller job code forbidden has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'forbidden');
});

it('happy: caller job code approval_required stores pending only not domain success [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'approval_required');
});

it('fail: caller job code domain_error has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'domain_error');
});

it('fail: caller job code rate_limited has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'rate_limited');
});

it('fail: caller job code conflict has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'conflict');
});

it('fail: caller job code not_found has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'not_found');
});

it('fail: caller job code output_invalid has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'output_invalid');
});

it('fail: caller job code internal has no successful domain mutation [PARITY-001]', function () {
    P::assertErrorCodeNoMutation('job', 'internal');
});
