# REQ-001: Fix unit test harness load


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:** composer test:core | composer test:messaging | composer test:cli
**Terminal state:** All three suites start without fatal redeclare/load errors; Pest/Go report TODO/incomplete tests rather than crashing at load.
**Parent:** 
**Closure proof:** checkpoint_log:passed (3/3) commit:0ae2927 core:4504-todos messaging:239-todos cli:go-test-ok inventory:5010
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** tools/generate_requirement_stubs.py docs/requirements-inventory.md packages/laravel-capabilities/tests packages/laravel-capabilities-messaging/tests packages/capabilities-cli
**Depends on:** 

## Task

Make the monorepo unit suites loadable. Fix Pest redeclare fatals from duplicate generated test titles (e.g. KeyFormatMatrixTest) by repairing the generator and regenerating stubs (or uniquely suffixing collisions). Ensure composer test:core, test:messaging, and test:cli can enumerate tests. Do not implement business logic yet. Preserve inventory alignment — do not prune scenarios.

## Context

UR-001 requires all tests to pass. Suites currently fatal on Pest redeclare before any implementation. Ideate: harness must load first. Tests remain SOT; generator is source for stub titles.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] composer test:core exits without PHP fatal redeclare; suite enumerates tests
- [x] composer test:messaging loads without fatal
- [x] composer test:cli loads (go test ./... starts) without compile/load failure unrelated to unimplemented behaviour
- [x] No intentional removal of inventory scenarios to 'fix' collisions — collisions get unique names while preserving intent
- [x] If generator regenerated stubs, docs/requirements-inventory.md stays consistent with tools/generate_requirement_stubs.py

## Verification Steps

1. **test** `composer test:core 2>&1 | tail -30`
   - Expected: No Fatal error redeclare; output shows incomplete/todo or pass/fail counts, not crash at load
2. **test** `composer test:messaging 2>&1 | tail -20`
   - Expected: Suite loads without fatal
3. **test** `composer test:cli 2>&1 | tail -20`
   - Expected: go test starts cleanly (may have failing/skipped tests)

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- tools/generate_requirement_stubs.py — Pest evaluable uniquify, key-format tags, never-drop ensure_unique_cases
- docs/requirements-inventory.md — Regenerated inventory aligned to 5010 generator cases
- packages/laravel-capabilities/tests/Unit/** — Unique scenario titles for loadable Pest suites
- packages/laravel-capabilities-messaging/tests/Unit/** — Unique messaging scenario titles
