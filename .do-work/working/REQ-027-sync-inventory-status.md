# REQ-027: Sync inventory status to suite reality

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.68154
**Claimed at:** 2026-07-27T05:03:47Z
**Heartbeat:** 2026-07-27T05:03:47Z
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
**Files:** docs/requirements-inventory.md tools/generate_requirement_stubs.py tools/sync_requirements_inventory.py packages/laravel-capabilities/tests/Unit/Architecture/ContractSourceOfTruthTest.php
**Depends on:** REQ-026

## Task

Stop presenting `docs/requirements-inventory.md` as 5010 open TODOs when suites are implemented. Add (or extend generator with) a sync path that (1) marks inventory cases done when a matching Pest `it()` / Go `Test*` exists, including matrix cases registered via `it($title)`, (2) retitles the header away from permanent “Total TODO cases” toward implemented vs remaining counts, and (3) leaves true remaining gaps unchecked. Prefer regenerating status from the suite over hand-editing 5k checkboxes.

## Context

Inventory is a static dump that always writes `- [ ]`. Implemented tests use single-quoted `it('…')` and dynamic matrix titles (`it($title)`), so naive “all todo” reading is wrong. User chose scaffold-sync only — not re-implementing product scenarios.

## Acceptance Criteria

- [ ] Inventory header reports implemented vs remaining (or equivalent), not only “Total TODO cases: 5010”
- [ ] Cases with a matching live test are marked complete (`- [x]` or equivalent documented status)
- [ ] Cases without a matching live test remain incomplete
- [ ] Matching handles Pest double- and single-quoted `it()` titles and Go `Test*` names from inventory CLI lines
- [ ] Dynamic matrix registration (`it($title)` / string interpolation building inventory titles) is counted when the constructed title equals the inventory label (execute-scan or static evaluation of the title expressions used in foreach matrices is acceptable if documented)
- [ ] Architecture/contract test that claimed “go CLI stubs use t.Skip TODO until implemented” is updated to the post-sync truth if still present
- [ ] Re-running sync is idempotent

## Verification Steps

1. **runtime** `python3 tools/sync_requirements_inventory.py` (or the chosen command documented by the REQ)
   - Expected: exit 0; inventory header shows non-zero implemented count; many `- [x]` lines present
2. **test** Spot-check: a known implemented label (e.g. pipeline stage failure case) is `- [x]`; if any intentional residual gap exists it stays `- [ ]`
   - Expected: matches suite reality for samples checked
3. **test** `composer test:core -- --filter=ContractSourceOfTruth 2>&1 | tail -40`
   - Expected: architecture inventory/CLI status tests pass under new wording

## Manual checks (advisory)

- [ ] Skim inventory CLI section after rename/sync — Observable outcome: paths/names match non-todo Go files and Test* rows look checked when tests exist
