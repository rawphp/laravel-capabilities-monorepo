<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('fail: refuse spoof_caller_header for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('spoof_caller_header', 'agent');
});

it('fail: refuse spoof_caller_header for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('spoof_caller_header', 'mcp');
});

it('fail: refuse spoof_caller_header for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('spoof_caller_header', 'http');
});

it('fail: refuse spoof_caller_header for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('spoof_caller_header', 'cli');
});

it('fail: refuse spoof_caller_header for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('spoof_caller_header', 'job');
});

it('fail: refuse skip_authorize for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('skip_authorize', 'agent');
});

it('fail: refuse skip_authorize for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('skip_authorize', 'mcp');
});

it('fail: refuse skip_authorize for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('skip_authorize', 'http');
});

it('fail: refuse skip_authorize for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('skip_authorize', 'cli');
});

it('fail: refuse skip_authorize for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('skip_authorize', 'job');
});

it('fail: refuse skip_scope for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('skip_scope', 'agent');
});

it('fail: refuse skip_scope for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('skip_scope', 'mcp');
});

it('fail: refuse skip_scope for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('skip_scope', 'http');
});

it('fail: refuse skip_scope for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('skip_scope', 'cli');
});

it('fail: refuse skip_scope for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('skip_scope', 'job');
});

it('fail: refuse skip_idempotency_on_mutating for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('skip_idempotency_on_mutating', 'agent');
});

it('fail: refuse skip_idempotency_on_mutating for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('skip_idempotency_on_mutating', 'mcp');
});

it('fail: refuse skip_idempotency_on_mutating for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('skip_idempotency_on_mutating', 'http');
});

it('fail: refuse skip_idempotency_on_mutating for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('skip_idempotency_on_mutating', 'cli');
});

it('fail: refuse skip_idempotency_on_mutating for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('skip_idempotency_on_mutating', 'job');
});

it('fail: refuse dump_full_catalog for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('dump_full_catalog', 'agent');
});

it('fail: refuse dump_full_catalog for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('dump_full_catalog', 'mcp');
});

it('fail: refuse dump_full_catalog for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('dump_full_catalog', 'http');
});

it('fail: refuse dump_full_catalog for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('dump_full_catalog', 'cli');
});

it('fail: refuse dump_full_catalog for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('dump_full_catalog', 'job');
});

it('fail: refuse meta_escape for caller agent [REFUSE]', function () {
    A::assertRefuseForCaller('meta_escape', 'agent');
});

it('fail: refuse meta_escape for caller mcp [REFUSE]', function () {
    A::assertRefuseForCaller('meta_escape', 'mcp');
});

it('fail: refuse meta_escape for caller http [REFUSE]', function () {
    A::assertRefuseForCaller('meta_escape', 'http');
});

it('fail: refuse meta_escape for caller cli [REFUSE]', function () {
    A::assertRefuseForCaller('meta_escape', 'cli');
});

it('fail: refuse meta_escape for caller job [REFUSE]', function () {
    A::assertRefuseForCaller('meta_escape', 'job');
});
