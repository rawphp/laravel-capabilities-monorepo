# REQ-016: Core package full suite green


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:** composer test:core
**Terminal state:** All packages/laravel-capabilities unit tests pass (0 failures, 0 fatals); coverage ≥95% on packages/laravel-capabilities/src.
**Parent:** 
**Closure proof:** checkpoint_log:passed commit:f972ceb 4567 passed coverage 95.1%
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/tests/Unit/CoverageGreen packages/laravel-capabilities docs/spec.md
**Depends on:** REQ-015

## Task

Drive remaining core inventory files/tests to green: any leftover Unit/** todos, coverage gaps, and conflicts. Use docs/spec.md when tests conflict; update tests deliberately. Meet ≥95% line coverage on src. Unit-only.

## Context

UR-001: all tests must pass; core is the largest package (~4497 scenarios).

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] composer test:core exits 0 with no failed tests and no incomplete todos remaining for core contract scenarios
- [x] Coverage on packages/laravel-capabilities/src is ≥95% (PCOV/Xdebug)
- [x] No tests/Feature introduced; no DB required

## Verification Steps

1. **test** `composer test:core 2>&1 | tail -40`
   - Expected: Exit 0, all tests passed
2. **test** `composer test:core -- --coverage --min=95 2>&1 | tail -50 || pest --configuration=packages/laravel-capabilities/phpunit.xml --coverage --min=95 2>&1 | tail -50`
   - Expected: Coverage ≥95% or documented equivalent coverage command succeeds

## Integration

**Reachability:** composer test:core

**Data dependencies:** N/A

**Service dependencies:** Entire core package

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- core suite green 4567 tests, 95.1% coverage
