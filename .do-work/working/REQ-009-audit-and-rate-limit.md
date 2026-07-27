# REQ-009: Audit modes and rate limiting

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T01:46:03Z
**Heartbeat:** 2026-07-27T01:46:03Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Post-run audit stage + rate_limit stage
**Terminal state:** best_effort audit failure does not roll back domain; strict mode fails; RateLimiting/* and Audit/* tests pass.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Audit packages/laravel-capabilities/src packages/laravel-capabilities/tests/Unit/Audit packages/laravel-capabilities/tests/Unit/RateLimiting
**Depends on:** REQ-005

## Task

Implement audit writer modes (D-010 best_effort vs strict), audit field matrices, rate limiting config/keys/agent budgets. Flesh Audit/* and RateLimiting/* unit tests.

## Context

Default best_effort: domain success not rolled back by audit failure unless strict.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] best_effort audit failure after success does not undo domain result
- [ ] strict mode fails invoke on audit failure
- [ ] Rate limit key/config/agent budget matrices pass
- [ ] Unit-only

## Verification Steps

1. **test** `composer test:core -- --filter=Audit 2>&1 | tail -40`
   - Expected: Audit tests pass
2. **test** `composer test:core -- --filter=RateLimit 2>&1 | tail -40`
   - Expected: RateLimiting tests pass

## Integration

**Reachability:** Pipeline stages rate_limit and audit

**Data dependencies:** Audit records; rate limit counters in fake limiter

**Service dependencies:** CapabilityRegistry

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
