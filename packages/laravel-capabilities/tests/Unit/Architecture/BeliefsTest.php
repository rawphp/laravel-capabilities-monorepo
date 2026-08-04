<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it('happy: belief enforced by tests: one run per product mutation [BELIEF]', function () {
    A::assertBelief('one run per product mutation');
});

it('happy: belief enforced by tests: capability is product language not transport [BELIEF]', function () {
    A::assertBelief('capability is product language not transport');
});

it('happy: belief enforced by tests: governance is part of capability [BELIEF]', function () {
    A::assertBelief('governance is part of capability');
});

it('happy: belief enforced by tests: compose official packages do not replace them [BELIEF]', function () {
    A::assertBelief('compose official packages do not replace them');
});

it('happy: belief enforced by tests: surfaces optional defaults generous [BELIEF]', function () {
    A::assertBelief('surfaces optional defaults generous');
});

it('happy: belief enforced by tests: CLI is a client not second backend [BELIEF]', function () {
    A::assertBelief('CLI is a client not second backend');
});

it('happy: belief enforced by tests: thin framework fat domain [BELIEF]', function () {
    A::assertBelief('thin framework fat domain');
});

it('happy: belief enforced by tests: fail closed and fail obvious [BELIEF]', function () {
    A::assertBelief('fail closed and fail obvious');
});

it('happy: belief enforced by tests: no silent actors [BELIEF]', function () {
    A::assertBelief('no silent actors');
});

it('happy: belief enforced by tests: no ambient tenancy [BELIEF]', function () {
    A::assertBelief('no ambient tenancy');
});

it('happy: belief enforced by tests: retries must not double apply [BELIEF]', function () {
    A::assertBelief('retries must not double apply');
});

it('happy: belief enforced by tests: approvals are decisions not fire and forget [BELIEF]', function () {
    A::assertBelief('approvals are decisions not fire and forget');
});

it('happy: belief enforced by tests: least privilege for model tool lists [BELIEF]', function () {
    A::assertBelief('least privilege for model tool lists');
});

it('happy: belief enforced by tests: framework does not reintroduce dual paths [BELIEF]', function () {
    A::assertBelief('framework does not reintroduce dual paths');
});

it('happy: belief enforced by tests: domain success not hostage to audit failure unless strict [BELIEF]', function () {
    A::assertBelief('domain success not hostage to audit failure unless strict');
});

it('happy: belief enforced by tests: peer packages pinned by matrix [BELIEF]', function () {
    A::assertBelief('peer packages pinned by matrix');
});

it('happy: belief enforced by tests: caller is server derived fact [BELIEF]', function () {
    A::assertBelief('caller is server derived fact');
});

it('happy: belief enforced by tests: MCP principals are explicit auth profiles [BELIEF]', function () {
    A::assertBelief('MCP principals are explicit auth profiles');
});
