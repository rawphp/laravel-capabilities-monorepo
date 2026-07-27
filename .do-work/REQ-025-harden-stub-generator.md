# REQ-025: Harden requirement stub generator

**UR:** UR-003
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** tools/generate_requirement_stubs.py tools/tests/test_generate_requirement_stubs.py AGENTS.md
**Depends on:**

## Task

Harden `tools/generate_requirement_stubs.py` so regenerating the requirements scaffold cannot wipe implemented unit tests. Default behaviour must refuse (or no-op with a clear error) when target test files already contain non-stub implementations; only files still marked AUTO-GENERATED stubs (or missing) may be rewritten. Document the safe command path in AGENTS.md if the regenerate contract changes.

## Context

UR-003 scaffold-sync only. Implemented PHP/Go suites green; generator `write_files()` still deletes Unit `*Test.php` and `*_todo_test.go` then rewrites pure `})->todo()` / `t.Skip("TODO…")` stubs. That makes inventory regeneration unsafe and is a primary reason status stayed “todo” after implementation.

## Acceptance Criteria

- [ ] Running the generator does not delete or overwrite a test file that lacks the AUTO-GENERATED stub marker (or equivalent “implemented” detection)
- [ ] Pure stub targets may still be regenerated; inventory markdown generation remains available without destroying live suites
- [ ] Clear stdout/stderr message when skips/refuses overwrite of implemented files (counts of written vs skipped)
- [ ] Automated test or scripted assertion covers the no-wipe behaviour (unit test of generator helpers, or a hermetic temp-dir exercise)
- [ ] AGENTS.md regenerate guidance matches the new safe contract (no “blind re-run wipes tests”)

## Verification Steps

1. **test** Run the generator unit/hermetic test added for no-wipe behaviour
   - Expected: pass
2. **runtime** In a temp copy or dry-run mode if provided, confirm an implemented fixture file is preserved while a stub fixture is rewritten
   - Expected: implemented content unchanged; stub rewritten or inventory-only path works
3. **test** `python3 -m py_compile tools/generate_requirement_stubs.py`
   - Expected: exit 0

## Manual checks (advisory)

- [ ] Before any intentional full scaffold refresh, operator reads AGENTS.md regenerate section — Observable outcome: procedure mentions inventory-only or force flag explicitly
