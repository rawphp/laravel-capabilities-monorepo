# REQ-026: Rename Go CLI todo test files

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.27027
**Claimed at:** 2026-07-27T04:59:13Z
**Heartbeat:** 2026-07-27T04:59:13Z
<!-- claimed-end -->

**UR:** UR-003
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/capabilities-cli/cmd/capabilities/*_todo_test.go packages/capabilities-cli/internal/**/*_todo_test.go tools/generate_requirement_stubs.py packages/laravel-capabilities/tests/Fixtures/ArchitectureHelpers.php packages/laravel-capabilities/tests/Unit/Architecture/ContractSourceOfTruthTest.php
**Depends on:** REQ-025

## Task

Rename all 28 `packages/capabilities-cli/**/*_todo_test.go` files to stable `*_test.go` names (drop `_todo`), update the generator catalog paths so new stubs are not re-created under `*_todo_test.go`, and update any architecture helpers/tests that assert on `_todo_test.go` naming or “t.Skip TODO until implemented” as the live convention.

## Context

CLI inventory Test* names are implemented (0 missing vs Go suite). Files still use generator-era `*_todo_test.go` names, which reads as unfinished work. Scaffold-sync only — no new CLI product behaviour.

## Acceptance Criteria

- [ ] Zero `*_todo_test.go` files remain under `packages/capabilities-cli/`
- [ ] Each former `foo_todo_test.go` lives as `foo_test.go` (or an explicit collision-safe name if `foo_test.go` already exists)
- [ ] `go test ./...` under `packages/capabilities-cli` still passes
- [ ] `tools/generate_requirement_stubs.py` catalog/relpaths no longer target `*_todo_test.go` for those modules
- [ ] Architecture helper/tests no longer require live CLI tests to use `_todo_test.go` or `t.Skip("TODO…")` as the normal state; meta coverage of the generator pattern may remain if accurate

## Verification Steps

1. **runtime** `find packages/capabilities-cli -name '*_todo_test.go' | wc -l`
   - Expected: `0`
2. **test** `cd packages/capabilities-cli && go test ./...`
   - Expected: all packages `ok`
3. **test** `rg -n '_todo_test\.go' tools/generate_requirement_stubs.py packages/laravel-capabilities/tests || true`
   - Expected: no path catalog still writing `*_todo_test.go` for the renamed modules (historical comments OK only if clearly historical)

## Manual checks (advisory)
