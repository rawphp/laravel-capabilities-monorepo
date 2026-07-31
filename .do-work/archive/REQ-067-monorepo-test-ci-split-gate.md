# REQ-067: Monorepo test CI and gate package split


**UR:** UR-012
**Status:** done
**Created:** 2026-07-31
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** tests.yml unit CI; split needs tests; tag cancel-in-progress false (X-001/X-002/X-013). commit:cde9289
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** .github/workflows/tests.yml, .github/workflows/split-packages.yml, docs/versioning.md
**Depends on:**

## Task

Add monorepo CI that runs `composer test` (core+messaging) and Go CLI tests; gate split/publish on green tests; disable cancel-in-progress for tag split concurrency (X-001, X-002, L-010, X-013).

## Context

Only split-packages.yml exists; it force-pushes package trees on main/tags without running unit suites. Tag concurrency cancel can partial-mirror.

## Acceptance Criteria

- [x] `.github/workflows/tests.yml` runs on PR + push to main: PHP 8.2 Pest for core and messaging; Go tests for capabilities-cli
- [x] `split-packages.yml` does not mirror until tests pass (needs: tests job, or workflow_call / same workflow job chain)
- [x] Tag/ref concurrency: cancel-in-progress false for tags (or equivalent safe policy)
- [x] docs/versioning.md notes that split is gated on green monorepo tests
- [x] Unit-only: no feature/DB test jobs introduced

## Verification Steps

1. **runtime** `test -f .github/workflows/tests.yml && grep -E 'composer test|pest|go test' .github/workflows/tests.yml`
   - Expected: tests workflow exists and invokes package unit suites
2. **runtime** `grep -E 'needs:|cancel-in-progress' .github/workflows/split-packages.yml`
   - Expected: split depends on tests (or equivalent gate); cancel policy addresses tags
3. **runtime** `grep -i 'test' docs/versioning.md | head -5`
   - Expected: docs mention test gate for split/publish

## Integration

Omit — CI/docs only.

## Outputs

- .github/workflows/tests.yml — monorepo unit CI
- .github/workflows/split-packages.yml — gated on tests
- docs/versioning.md — test gate documented
