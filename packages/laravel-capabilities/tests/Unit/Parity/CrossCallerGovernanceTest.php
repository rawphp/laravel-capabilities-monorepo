<?php

// REQ-015: Parity cross-caller governance contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ParityHelpers as P;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("happy: authorize deny via agent does not mutate [PARITY-001]", function () {
    P::assertAuthorizeDenyNoMutate('agent');
});

it("happy: schema invalid via agent does not mutate [PARITY-001]", function () {
    P::assertSchemaInvalidNoMutate('agent');
});

it("happy: scope cross-tenant via agent does not mutate [D-003]", function () {
    P::assertCrossTenantNoMutate('agent');
});

it("happy: needsApproval true via agent does not run until accept [D-006]", function () {
    P::assertNeedsApprovalNoRun('agent');
});

it("happy: idempotent replay via agent does not second run [D-005]", function () {
    P::assertIdempotentReplayNoSecondRun('agent');
});

it("happy: rate limited via agent does not run [D-013]", function () {
    P::assertRateLimitedNoRun('agent');
});

it("happy: audit records caller agent on success [D-010]", function () {
    P::assertAuditRecordsCaller('agent');
});

it("fail: client cannot spoof caller away from agent when derived as agent [D-022]", function () {
    P::assertCannotSpoofCaller('agent');
});

it("happy: authorize deny via mcp does not mutate [PARITY-001]", function () {
    P::assertAuthorizeDenyNoMutate('mcp');
});

it("happy: schema invalid via mcp does not mutate [PARITY-001]", function () {
    P::assertSchemaInvalidNoMutate('mcp');
});

it("happy: scope cross-tenant via mcp does not mutate [D-003]", function () {
    P::assertCrossTenantNoMutate('mcp');
});

it("happy: needsApproval true via mcp does not run until accept [D-006]", function () {
    P::assertNeedsApprovalNoRun('mcp');
});

it("happy: idempotent replay via mcp does not second run [D-005]", function () {
    P::assertIdempotentReplayNoSecondRun('mcp');
});

it("happy: rate limited via mcp does not run [D-013]", function () {
    P::assertRateLimitedNoRun('mcp');
});

it("happy: audit records caller mcp on success [D-010]", function () {
    P::assertAuditRecordsCaller('mcp');
});

it("fail: client cannot spoof caller away from mcp when derived as mcp [D-022]", function () {
    P::assertCannotSpoofCaller('mcp');
});

it("happy: authorize deny via http does not mutate [PARITY-001]", function () {
    P::assertAuthorizeDenyNoMutate('http');
});

it("happy: schema invalid via http does not mutate [PARITY-001]", function () {
    P::assertSchemaInvalidNoMutate('http');
});

it("happy: scope cross-tenant via http does not mutate [D-003]", function () {
    P::assertCrossTenantNoMutate('http');
});

it("happy: needsApproval true via http does not run until accept [D-006]", function () {
    P::assertNeedsApprovalNoRun('http');
});

it("happy: idempotent replay via http does not second run [D-005]", function () {
    P::assertIdempotentReplayNoSecondRun('http');
});

it("happy: rate limited via http does not run [D-013]", function () {
    P::assertRateLimitedNoRun('http');
});

it("happy: audit records caller http on success [D-010]", function () {
    P::assertAuditRecordsCaller('http');
});

it("fail: client cannot spoof caller away from http when derived as http [D-022]", function () {
    P::assertCannotSpoofCaller('http');
});

it("happy: authorize deny via cli does not mutate [PARITY-001]", function () {
    P::assertAuthorizeDenyNoMutate('cli');
});

it("happy: schema invalid via cli does not mutate [PARITY-001]", function () {
    P::assertSchemaInvalidNoMutate('cli');
});

it("happy: scope cross-tenant via cli does not mutate [D-003]", function () {
    P::assertCrossTenantNoMutate('cli');
});

it("happy: needsApproval true via cli does not run until accept [D-006]", function () {
    P::assertNeedsApprovalNoRun('cli');
});

it("happy: idempotent replay via cli does not second run [D-005]", function () {
    P::assertIdempotentReplayNoSecondRun('cli');
});

it("happy: rate limited via cli does not run [D-013]", function () {
    P::assertRateLimitedNoRun('cli');
});

it("happy: audit records caller cli on success [D-010]", function () {
    P::assertAuditRecordsCaller('cli');
});

it("fail: client cannot spoof caller away from cli when derived as cli [D-022]", function () {
    P::assertCannotSpoofCaller('cli');
});

it("happy: authorize deny via job does not mutate [PARITY-001]", function () {
    P::assertAuthorizeDenyNoMutate('job');
});

it("happy: schema invalid via job does not mutate [PARITY-001]", function () {
    P::assertSchemaInvalidNoMutate('job');
});

it("happy: scope cross-tenant via job does not mutate [D-003]", function () {
    P::assertCrossTenantNoMutate('job');
});

it("happy: needsApproval true via job does not run until accept [D-006]", function () {
    P::assertNeedsApprovalNoRun('job');
});

it("happy: idempotent replay via job does not second run [D-005]", function () {
    P::assertIdempotentReplayNoSecondRun('job');
});

it("happy: rate limited via job does not run [D-013]", function () {
    P::assertRateLimitedNoRun('job');
});

it("happy: audit records caller job on success [D-010]", function () {
    P::assertAuditRecordsCaller('job');
});

it("fail: client cannot spoof caller away from job when derived as job [D-022]", function () {
    P::assertCannotSpoofCaller('job');
});

