# REQ-002: Shared core test fakes and doubles

**UR:** UR-001
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:** packages/laravel-capabilities/tests support helpers constructing fakes
**Terminal state:** Unit tests can inject in-memory stores/clocks/authorizers without DB/HTTP/peers.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/tests packages/laravel-capabilities/src/Contracts packages/laravel-capabilities/src/Support
**Depends on:** REQ-001

## Task

Add shared unit-test fakes/in-memory drivers used across core domains: approval store, idempotency store, audit writer, clock, rate limiter, authorizer stubs, capability fixtures. Prefer interfaces in src/Contracts with test doubles under tests/ or src support designed for injection. No database. Align with AGENTS.md unit-only policy.

## Context

Connector: every domain needs the same doubles; without this workers re-invent mocks and footprint-collide. Foundation for TDD of pipeline and governance.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] In-memory (or pure fake) implementations exist for approval, idempotency, and audit store/writer contracts used by the pipeline
- [ ] Fakes are constructible in Pest unit tests with no DB, Redis, queue, or network
- [ ] At least one smoke unit test proves a fake can record and read back a pending approval / idempotency outcome
- [ ] No tests/Feature, RefreshDatabase, or real DB connection required

- [ ] Constructing fakes without required contracts fails loudly (type/constructor error) rather than silently returning null stores

## Verification Steps

1. **test** `composer test:core -- --filter=Fake 2>&1 | tail -40 || pest --configuration=packages/laravel-capabilities/phpunit.xml --filter=InMemory 2>&1 | tail -40`
   - Expected: New fake/smoke tests pass (or filter matches implemented tests)

## Integration

**Reachability:** Injected into CapabilityRegistry / pipeline stages by unit tests under packages/laravel-capabilities/tests/Unit

**Data dependencies:** In-memory maps only; no Eloquent models

**Service dependencies:** Implements contracts under packages/laravel-capabilities/src/Contracts (to be filled as types land in REQ-003+)

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
