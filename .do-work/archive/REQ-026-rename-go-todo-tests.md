# REQ-026: Rename Go CLI todo test files


**UR:** UR-003
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** checkpoint_log:passed (3/3) commit:a9ad97d
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/capabilities-cli/cmd/capabilities/*_test.go packages/capabilities-cli/internal/**/*_test.go tools/generate_requirement_stubs.py tools/tests/test_generate_requirement_stubs.py docs/requirements-inventory.md packages/laravel-capabilities/tests/Fixtures/ArchitectureHelpers.php packages/laravel-capabilities/tests/Unit/Architecture/ContractSourceOfTruthTest.php
**Depends on:** REQ-025

## Task

Rename all 28 `packages/capabilities-cli/**/*_todo_test.go` files to stable `*_test.go` names (drop `_todo`), update the generator catalog paths so new stubs are not re-created under `*_todo_test.go`, and update any architecture helpers/tests that assert on `_todo_test.go` naming or “t.Skip TODO until implemented” as the live convention.

## Context

CLI inventory Test* names are implemented (0 missing vs Go suite). Files still use generator-era `*_todo_test.go` names, which reads as unfinished work. Scaffold-sync only — no new CLI product behaviour.

## Acceptance Criteria

- [x] Zero `*_todo_test.go` files remain under `packages/capabilities-cli/`
- [x] Each former `foo_todo_test.go` lives as `foo_test.go` (or an explicit collision-safe name if `foo_test.go` already exists)
- [x] `go test ./...` under `packages/capabilities-cli` still passes
- [x] `tools/generate_requirement_stubs.py` catalog/relpaths no longer target `*_todo_test.go` for those modules
- [x] Architecture helper/tests no longer require live CLI tests to use `_todo_test.go` or `t.Skip("TODO…")` as the normal state; meta coverage of the generator pattern may remain if accurate

## Verification Steps

1. **runtime** `find packages/capabilities-cli -name '*_todo_test.go' | wc -l`
   - Expected: `0`
2. **test** `cd packages/capabilities-cli && go test ./...`
   - Expected: all packages `ok`
3. **test** `rg -n '_todo_test\.go' tools/generate_requirement_stubs.py packages/laravel-capabilities/tests || true`
   - Expected: no path catalog still writing `*_todo_test.go` for the renamed modules (historical comments OK only if clearly historical)

## Manual checks (advisory)

## Outputs

- packages/capabilities-cli/**/*_test.go — 28 renames from *_todo_test.go
- tools/generate_requirement_stubs.py — catalog paths stable *_test.go
- packages/laravel-capabilities/tests/Fixtures/ArchitectureHelpers.php — no _todo special-case
- packages/laravel-capabilities/tests/Unit/Architecture/ContractSourceOfTruthTest.php — assert zero *_todo_test.go
