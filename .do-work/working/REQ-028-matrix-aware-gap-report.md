# REQ-028: Matrix-aware inventory gap report

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.45746
**Claimed at:** 2026-07-27T05:14:57Z
**Heartbeat:** 2026-07-27T05:14:57Z
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
**Priority:** 1
**Size:** S
**Files:** tools/report_inventory_gaps.py docs/requirements-inventory.md AGENTS.md
**Depends on:** REQ-027

## Task

Add a small report script that prints real remaining inventory gaps after matrix-aware matching (Pest static + dynamic titles, Go Test names), grouped by package/file/tag, so future “are there still todos?” questions answer from evidence instead of raw `- [ ]` counts. Document one-line usage in AGENTS.md near the inventory section.

## Context

Scaffold-sync only. Static string match under-counts matrix suites; this report is the durable way to distinguish label drift from true missing scenarios without reopening product implementation work.

## Acceptance Criteria

- [ ] `python3 tools/report_inventory_gaps.py` exits 0 and prints totals: inventory cases, matched, unmatched, by package
- [ ] Report does not treat implemented dynamic `it($title)` matrix cases as gaps when titles match inventory labels
- [ ] CLI inventory Test* names that exist in `packages/capabilities-cli` are matched
- [ ] Output is human-readable (stdout); optional JSON flag is fine but not required
- [ ] AGENTS.md mentions the report command next to inventory/stub regenerate guidance

## Verification Steps

1. **runtime** `python3 tools/report_inventory_gaps.py 2>&1 | tail -40`
   - Expected: exit 0; shows matched count >> 0; remaining gap count far below 5010 if suite is largely implemented
2. **runtime** `python3 -m py_compile tools/report_inventory_gaps.py`
   - Expected: exit 0

## Manual checks (advisory)
