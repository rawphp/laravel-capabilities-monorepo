# REQ-028: Matrix-aware inventory gap report


**UR:** UR-003
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** checkpoint_log:passed (2/2) commit:3bf7103
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** tools/report_inventory_gaps.py tools/tests/test_report_inventory_gaps.py AGENTS.md
**Depends on:** REQ-027

## Task

Add a small report script that prints real remaining inventory gaps after matrix-aware matching (Pest static + dynamic titles, Go Test names), grouped by package/file/tag, so future “are there still todos?” questions answer from evidence instead of raw `- [ ]` counts. Document one-line usage in AGENTS.md near the inventory section.

## Context

Scaffold-sync only. Static string match under-counts matrix suites; this report is the durable way to distinguish label drift from true missing scenarios without reopening product implementation work.

## Acceptance Criteria

- [x] `python3 tools/report_inventory_gaps.py` exits 0 and prints totals: inventory cases, matched, unmatched, by package
- [x] Report does not treat implemented dynamic `it($title)` matrix cases as gaps when titles match inventory labels
- [x] CLI inventory Test* names that exist in `packages/capabilities-cli` are matched
- [x] Output is human-readable (stdout); optional JSON flag is fine but not required
- [x] AGENTS.md mentions the report command next to inventory/stub regenerate guidance

## Verification Steps

1. **runtime** `python3 tools/report_inventory_gaps.py 2>&1 | tail -40`
   - Expected: exit 0; shows matched count >> 0; remaining gap count far below 5010 if suite is largely implemented
2. **runtime** `python3 -m py_compile tools/report_inventory_gaps.py`
   - Expected: exit 0

## Manual checks (advisory)

## Outputs

- tools/report_inventory_gaps.py — matrix-aware inventory gap report
- tools/tests/test_report_inventory_gaps.py — hermetic unit tests
- AGENTS.md — one-line usage for gap report
