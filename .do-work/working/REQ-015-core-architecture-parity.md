# REQ-015: Architecture parity and remaining core matrices

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T02:48:15Z
**Heartbeat:** 2026-07-27T02:48:15Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Architecture/* Parity/* remaining matrix suites as contract guards
**Terminal state:** Architecture and Parity unit suites pass; they encode refuse tables, non-goals, governance-everywhere, cross-caller parity using the implemented bus.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** L
**Files:** packages/laravel-capabilities/tests/Unit/Architecture packages/laravel-capabilities/tests/Unit/Parity packages/laravel-capabilities/src docs/spec.md
**Depends on:** REQ-005, REQ-006, REQ-007, REQ-008, REQ-009, REQ-010, REQ-011, REQ-012, REQ-013, REQ-014

## Task

Implement remaining Architecture/* and Parity/* inventory scenarios as real unit tests asserting implemented behaviour (refuse tables, design rules, messaging boundary, dual-path inventory, cross-caller governance). Fix production gaps found; if a stub conflicts with docs/spec.md, update the test to match spec and document in decisions. No feature tests.

## Context

These matrices are the long-tail contract. Tests are SOT after intentional updates; spec is conflict oracle.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Architecture unit tests pass (or only intentional documented skips with zero silent todos)
- [ ] Parity cross-caller governance tests pass
- [ ] No dual mutation path remains untested
- [ ] Conflicts resolved via spec then test update, not by deleting scenarios without replacement

## Verification Steps

1. **test** `composer test:core -- --filter=Architecture 2>&1 | tail -50`
   - Expected: Architecture tests pass
2. **test** `composer test:core -- --filter=Parity 2>&1 | tail -50`
   - Expected: Parity tests pass

## Integration

**Reachability:** CI unit suite only — contract guards

**Data dependencies:** N/A

**Service dependencies:** Full core package behaviour

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
