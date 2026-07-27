# REQ-019: Monorepo all packages green gate


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:** composer test && composer test:cli
**Terminal state:** All PHP + Go unit suites pass; core and messaging ≥95% coverage; no feature/DB tests; inventory scenarios implemented or deliberately updated with spec rationale.
**Parent:** 
**Closure proof:** checkpoint_log:passed commit:657b24f monorepo green core 95.1% messaging 97%
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** M
**Files:** packages/capabilities-cli/dist/README.md, .gitignore
**Depends on:** REQ-016, REQ-017, REQ-018

## Task

Final gate for UR-001: run full monorepo unit suites, close remaining gaps across packages, resolve any spec/test conflicts via docs/spec.md, ensure ≥95% coverage on both PHP packages, confirm zero feature tests and no DB dependency. Update inventory checkboxes only if generation tooling supports it — do not hand-delete scenarios.

## Context

Brief: implement all tests and business logic; all tests must pass; tests SOT; spec second source.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] composer test exits 0 (core + messaging)
- [x] composer test:cli exits 0
- [x] Core and messaging src coverage ≥95%
- [x] No tests/Feature directories; suites pass with DB_* unset
- [x] Any intentional test changes vs original stubs are justified by docs/spec.md

- [x] If any package suite fails or coverage is below 95%, the gate fails (non-zero) and does not declare monorepo green

## Verification Steps

1. **test** `composer test 2>&1 | tail -50`
   - Expected: Exit 0
2. **test** `composer test:cli 2>&1 | tail -30`
   - Expected: Exit 0
3. **test** `DB_CONNECTION= unset composer test 2>&1 | tail -20`
   - Expected: Still passes without DB

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- monorepo all packages green
