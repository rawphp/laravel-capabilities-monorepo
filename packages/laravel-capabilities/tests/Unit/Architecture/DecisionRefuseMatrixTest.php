<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;

it("fail: D-002 refuses: if caller job return true [D-002]", function () {
    A::assertRefuse('jobs bypass policy global config');
});

it("fail: D-002 refuses: null user allow [D-002]", function () {
    A::assertRefuse('null user allow on jobs');
});

it("fail: D-002 refuses: reuse only user can for scheduler without system [D-002]", function () {
    A::assertRefuse('null user allow on jobs');
});

it("fail: D-002 refuses: global jobs bypass policy [D-002]", function () {
    A::assertRefuse('jobs bypass policy global config');
});

it("fail: D-002 refuses: tenant in input for system jobs [D-002]", function () {
    A::assertRefuse('tenant magic key for SystemActor');
});

it("fail: D-003 refuses: trust exists alone [D-003]", function () {
    A::assertRefuse('trust exists alone for multi-tenant');
});

it("fail: D-003 refuses: policy footnote for tenancy [D-003]", function () {
    A::assertRefuse('trust exists alone for multi-tenant');
});

it("fail: D-003 refuses: system actor tenant from wire input [D-003]", function () {
    A::assertRefuse('tenant magic key for SystemActor');
});

it("fail: D-005 refuses: rely on clients not to retry [D-005]", function () {
    A::assertRefuse('dedupe by input without key');
});

it("fail: D-005 refuses: idempotency only on HTTP [D-005]", function () {
    A::assertRefuse('idempotency only on one surface');
});

it("fail: D-005 refuses: approval accept untied to key [D-005]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-005 refuses: dedupe by input without key [D-005]", function () {
    A::assertRefuse('dedupe by input without key');
});

it("fail: D-006 refuses: status without conditional updates [D-006]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-006 refuses: accept without revalidation [D-006]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-006 refuses: any authenticated approve silent multi-tenant default [D-006]", function () {
    A::assertRefuse('trust exists alone for multi-tenant');
});

it("fail: D-006 refuses: eternal pending [D-006]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-006 refuses: approved without resume or atomic [D-006]", function () {
    A::assertRefuse('approved limbo without resume or atomic');
});

it("fail: D-006 refuses: re-accept re-run while approved [D-006]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-006 refuses: unsigned telegram callback [D-006]", function () {
    A::assertRefuse('unsigned Telegram approve id');
});

it("fail: D-006 refuses: elevating approver silent default [D-006]", function () {
    A::assertRefuse('approval without revalidation on accept');
});

it("fail: D-007 refuses: messaging in core [D-007]", function () {
    A::assertRefuse('messaging bot runtime inside core');
});

it("fail: D-007 refuses: telegram in core [D-007]", function () {
    A::assertRefuse('messaging bot runtime inside core');
});

it("fail: D-007 refuses: messaging alternate run API [D-007]", function () {
    A::assertRefuse('messaging bot runtime inside core');
});

it("fail: D-008 refuses: full catalog dump default [D-008]", function () {
    A::assertRefuse('full catalog dump to agents by default');
});

it("fail: D-008 refuses: MCP all UI powers [D-008]", function () {
    A::assertRefuse('MCP vague token user without profile');
});

it("fail: D-008 refuses: meta tools escape hatch [D-008]", function () {
    A::assertRefuse('meta tools privilege escape');
});

it("fail: D-009 refuses: CliApiController invoke tree [D-009]", function () {
    A::assertRefuse('second HTTP invoke controller for CLI');
});

it("fail: D-009 refuses: second HTTP invoke API [D-009]", function () {
    A::assertRefuse('second HTTP invoke controller for CLI');
});

it("fail: D-010 refuses: undefined audit mode [D-010]", function () {
    A::assertRefuse('silent audit drop when required');
});

it("fail: D-010 refuses: default wrap money with audit txn [D-010]", function () {
    A::assertRefuse('silent audit drop when required');
});

it("fail: D-010 refuses: silent audit drop [D-010]", function () {
    A::assertRefuse('silent audit drop when required');
});

it("fail: D-010 refuses: events before commit default [D-010]", function () {
    A::assertRefuse('silent audit drop when required');
});

it("fail: D-011 refuses: support whatever peer without tests [D-011]", function () {
    A::assertRefuse('peer half-register tools');
});

it("fail: D-011 refuses: swallow adapter errors [D-011]", function () {
    A::assertRefuse('peer half-register tools');
});

it("fail: D-011 refuses: support only in discord [D-011]", function () {
    A::assertRefuse('peer half-register tools');
});

it("fail: D-011 refuses: adapter rewrite without AdapterApi bump [D-011]", function () {
    A::assertRefuse('peer half-register tools');
});

it("fail: D-022 refuses: client chosen caller header authority [D-022]", function () {
    A::assertRefuse('client spoofable caller header upgrade');
});

it("fail: D-022 refuses: self upgrade privilege via header [D-022]", function () {
    A::assertRefuse('client spoofable caller header upgrade');
});

it("fail: D-023 refuses: vague token user [D-023]", function () {
    A::assertRefuse('MCP vague token user without profile');
});

it("fail: D-023 refuses: tool args as actor authority [D-023]", function () {
    A::assertRefuse('MCP vague token user without profile');
});

it("fail: D-023 refuses: integration without allowlist [D-023]", function () {
    A::assertRefuse('integration credentials without allowlist');
});

