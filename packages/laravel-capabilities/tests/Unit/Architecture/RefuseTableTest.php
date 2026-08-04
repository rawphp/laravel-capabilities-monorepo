<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('fail: package refuses anti-pattern: null user allow on jobs [D-002]', function () {
    A::assertRefuse('null user allow on jobs');
});

it('fail: package refuses anti-pattern: jobs bypass policy global config [D-002]', function () {
    A::assertRefuse('jobs bypass policy global config');
});

it('fail: package refuses anti-pattern: tenant magic key for SystemActor [P2-005]', function () {
    A::assertRefuse('tenant magic key for SystemActor');
});

it('fail: package refuses anti-pattern: client spoofable caller header upgrade [D-022]', function () {
    A::assertRefuse('client spoofable caller header upgrade');
});

it('fail: package refuses anti-pattern: full catalog dump to agents by default [D-008]', function () {
    A::assertRefuse('full catalog dump to agents by default');
});

it('fail: package refuses anti-pattern: meta tools privilege escape [P2-007]', function () {
    A::assertRefuse('meta tools privilege escape');
});

it('fail: package refuses anti-pattern: second HTTP invoke controller for CLI [D-009]', function () {
    A::assertRefuse('second HTTP invoke controller for CLI');
});

it('fail: package refuses anti-pattern: Laravel rules as only schema source [D-004]', function () {
    A::assertRefuse('Laravel rules as only schema source');
});

it('fail: package refuses anti-pattern: CLI only validation without server revalidation [D-004]', function () {
    A::assertRefuse('CLI only validation without server revalidation');
});

it('fail: package refuses anti-pattern: approval without revalidation on accept [D-006]', function () {
    A::assertRefuse('approval without revalidation on accept');
});

it('fail: package refuses anti-pattern: unsigned Telegram approve id [D-006]', function () {
    A::assertRefuse('unsigned Telegram approve id');
});

it('fail: package refuses anti-pattern: approved limbo without resume or atomic [P2-004]', function () {
    A::assertRefuse('approved limbo without resume or atomic');
});

it('fail: package refuses anti-pattern: silent audit drop when required [D-010]', function () {
    A::assertRefuse('silent audit drop when required');
});

it('fail: package refuses anti-pattern: peer half-register tools [D-011]', function () {
    A::assertRefuse('peer half-register tools');
});

it('fail: package refuses anti-pattern: messaging bot runtime inside core [D-007]', function () {
    A::assertRefuse('messaging bot runtime inside core');
});

it('fail: package refuses anti-pattern: Artisan as product CLI [D-016]', function () {
    A::assertRefuse('Artisan as product CLI');
});

it('fail: package refuses anti-pattern: MCP vague token user without profile [D-023]', function () {
    A::assertRefuse('MCP vague token user without profile');
});

it('fail: package refuses anti-pattern: integration credentials without allowlist [D-023]', function () {
    A::assertRefuse('integration credentials without allowlist');
});

it('fail: package refuses anti-pattern: actor from tool JSON for MCP [D-023]', function () {
    A::assertRefuse('actor from tool JSON for MCP');
});

it('fail: package refuses anti-pattern: SystemActor can approve [D-006]', function () {
    A::assertRefuse('SystemActor can approve');
});

it('fail: package refuses anti-pattern: idempotency only on one surface [D-005]', function () {
    A::assertRefuse('idempotency only on one surface');
});

it('fail: package refuses anti-pattern: dedupe by input without key [D-005]', function () {
    A::assertRefuse('dedupe by input without key');
});

it('fail: package refuses anti-pattern: third capability discovery path [D-017]', function () {
    A::assertRefuse('third capability discovery path');
});

it('fail: package refuses anti-pattern: domain logic in Go CLI [D-016]', function () {
    A::assertRefuse('domain logic in Go CLI');
});

it('fail: package refuses anti-pattern: trust exists alone for multi-tenant [D-003]', function () {
    A::assertRefuse('trust exists alone for multi-tenant');
});
