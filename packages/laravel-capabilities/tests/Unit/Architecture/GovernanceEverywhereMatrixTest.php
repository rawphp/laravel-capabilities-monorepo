<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('happy: concern authorize applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('authorize', 'agent');
});

it('fail: concern authorize cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('authorize', 'agent');
});

it('happy: concern authorize applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('authorize', 'mcp');
});

it('fail: concern authorize cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it('happy: concern authorize applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('authorize', 'http');
});

it('fail: concern authorize cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('authorize', 'http');
});

it('happy: concern authorize applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('authorize', 'cli');
});

it('fail: concern authorize cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('authorize', 'cli');
});

it('happy: concern authorize applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('authorize', 'job');
});

it('fail: concern authorize cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('authorize', 'job');
});

it('happy: concern approval applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('approval', 'agent');
});

it('fail: concern approval cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('approval', 'agent');
});

it('happy: concern approval applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('approval', 'mcp');
});

it('fail: concern approval cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('approval', 'mcp');
});

it('happy: concern approval applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('approval', 'http');
});

it('fail: concern approval cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('approval', 'http');
});

it('happy: concern approval applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('approval', 'cli');
});

it('fail: concern approval cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('approval', 'cli');
});

it('happy: concern approval applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('approval', 'job');
});

it('fail: concern approval cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('approval', 'job');
});

it('happy: concern audit applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('audit', 'agent');
});

it('fail: concern audit cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('audit', 'agent');
});

it('happy: concern audit applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('audit', 'mcp');
});

it('fail: concern audit cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('audit', 'mcp');
});

it('happy: concern audit applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('audit', 'http');
});

it('fail: concern audit cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('audit', 'http');
});

it('happy: concern audit applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('audit', 'cli');
});

it('fail: concern audit cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('audit', 'cli');
});

it('happy: concern audit applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('audit', 'job');
});

it('fail: concern audit cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('audit', 'job');
});

it('happy: concern actor applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('actor', 'agent');
});

it('fail: concern actor cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('actor', 'agent');
});

it('happy: concern actor applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('actor', 'mcp');
});

it('fail: concern actor cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('actor', 'mcp');
});

it('happy: concern actor applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('actor', 'http');
});

it('fail: concern actor cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('actor', 'http');
});

it('happy: concern actor applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('actor', 'cli');
});

it('fail: concern actor cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('actor', 'cli');
});

it('happy: concern actor applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('actor', 'job');
});

it('fail: concern actor cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('actor', 'job');
});

it('happy: concern scope applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('scope', 'agent');
});

it('fail: concern scope cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('scope', 'agent');
});

it('happy: concern scope applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('scope', 'mcp');
});

it('fail: concern scope cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('scope', 'mcp');
});

it('happy: concern scope applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('scope', 'http');
});

it('fail: concern scope cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('scope', 'http');
});

it('happy: concern scope applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('scope', 'cli');
});

it('fail: concern scope cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('scope', 'cli');
});

it('happy: concern scope applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('scope', 'job');
});

it('fail: concern scope cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('scope', 'job');
});

it('happy: concern idempotency applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('idempotency', 'agent');
});

it('fail: concern idempotency cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('idempotency', 'agent');
});

it('happy: concern idempotency applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('idempotency', 'mcp');
});

it('fail: concern idempotency cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('idempotency', 'mcp');
});

it('happy: concern idempotency applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('idempotency', 'http');
});

it('fail: concern idempotency cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('idempotency', 'http');
});

it('happy: concern idempotency applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('idempotency', 'cli');
});

it('fail: concern idempotency cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('idempotency', 'cli');
});

it('happy: concern idempotency applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('idempotency', 'job');
});

it('fail: concern idempotency cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('idempotency', 'job');
});

it('happy: concern rate_limit applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('rate_limit', 'agent');
});

it('fail: concern rate_limit cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('rate_limit', 'agent');
});

it('happy: concern rate_limit applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('rate_limit', 'mcp');
});

it('fail: concern rate_limit cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('rate_limit', 'mcp');
});

it('happy: concern rate_limit applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('rate_limit', 'http');
});

it('fail: concern rate_limit cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('rate_limit', 'http');
});

it('happy: concern rate_limit applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('rate_limit', 'cli');
});

it('fail: concern rate_limit cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('rate_limit', 'cli');
});

it('happy: concern rate_limit applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('rate_limit', 'job');
});

it('fail: concern rate_limit cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('rate_limit', 'job');
});

it('happy: concern schema applies for caller agent [BELIEF-003]', function () {
    A::assertConcernApplies('schema', 'agent');
});

it('fail: concern schema cannot be skipped for caller agent [BELIEF-003]', function () {
    A::assertConcernCannotSkip('schema', 'agent');
});

it('happy: concern schema applies for caller mcp [BELIEF-003]', function () {
    A::assertConcernApplies('schema', 'mcp');
});

it('fail: concern schema cannot be skipped for caller mcp [BELIEF-003]', function () {
    A::assertConcernCannotSkip('schema', 'mcp');
});

it('happy: concern schema applies for caller http [BELIEF-003]', function () {
    A::assertConcernApplies('schema', 'http');
});

it('fail: concern schema cannot be skipped for caller http [BELIEF-003]', function () {
    A::assertConcernCannotSkip('schema', 'http');
});

it('happy: concern schema applies for caller cli [BELIEF-003]', function () {
    A::assertConcernApplies('schema', 'cli');
});

it('fail: concern schema cannot be skipped for caller cli [BELIEF-003]', function () {
    A::assertConcernCannotSkip('schema', 'cli');
});

it('happy: concern schema applies for caller job [BELIEF-003]', function () {
    A::assertConcernApplies('schema', 'job');
});

it('fail: concern schema cannot be skipped for caller job [BELIEF-003]', function () {
    A::assertConcernCannotSkip('schema', 'job');
});
