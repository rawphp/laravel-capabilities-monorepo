# REQ-006: Authorize actor and tenancy scope

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T01:23:47Z
**Heartbeat:** 2026-07-27T01:23:47Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Pipeline stages resolve_actor, resolve_scope, authorize
**Terminal state:** Caller/actor derivation and scope re-resolution enforce tenancy; Scope/* Caller/* Job actor inventory tests pass.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src packages/laravel-capabilities/tests/Unit/Scope packages/laravel-capabilities/tests/Unit/Caller packages/laravel-capabilities/tests/Unit/Job packages/laravel-capabilities/tests/Unit/Context
**Depends on:** REQ-005

## Task

Implement server-derived caller (D-022), SystemActor for jobs (D-002), scope/tenancy re-resolve under authorize and run (D-003), context fields. Flesh Scope/*, Caller/*, Job/* actor tests, Context/* tests. Never trust client-claimed tenant/caller.

## Context

Security choke: resource IDs untrusted until re-resolved; jobs need explicit actor.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Caller derivation tests pass for credential-derived caller kinds
- [ ] Cross-tenant / attack-vector Scope matrix scenarios fail closed
- [ ] Job SystemActor / allowlist inventory scenarios pass
- [ ] Context field matrix scenarios covered with unit asserts

## Verification Steps

1. **test** `composer test:core -- --filter=Scope 2>&1 | tail -40`
   - Expected: Scope tests pass
2. **test** `composer test:core -- --filter=Caller 2>&1 | tail -40`
   - Expected: Caller tests pass
3. **test** `composer test:core -- --filter=Job 2>&1 | tail -40`
   - Expected: Job unit tests pass
4. **test** `composer test:core -- --filter=Context 2>&1 | tail -40`
   - Expected: Context tests pass

## Integration

**Reachability:** Pipeline stages on every invoke

**Data dependencies:** Actor + tenant scope on CapabilityContext

**Service dependencies:** CapabilityRegistry authorize/scope stages

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
