# REQ-038: Peer release gate docs and optional consumer peer-live path

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.95896
**Claimed at:** 2026-07-27T05:36:08Z
**Heartbeat:** 2026-07-27T05:36:08Z
<!-- claimed-end -->

**UR:** UR-006
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-035
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/README.md docs/spec.md docs packages/laravel-capabilities/tests/Unit/Adapters
**Depends on:** REQ-036, REQ-037

## Task

Document the D-011 release gate: matrix must be updated with any peer support change; adapter/contract fixture suite must be green; optional consumer-app “peer-live” checklist for apps that install real `laravel/ai` / `laravel/mcp`. Do not add live peers to default monorepo CI. Optionally add a unit test that asserts the release-gate doc section exists (string presence) if appropriate.

## Context

Brief: contract tests against real minors remain aspirational for consumer apps. Package honesty = unit contract fixtures + matrix; consumer honesty = optional live job they own. Spec D-011 release gate: matrix/adapter change without green contract jobs is not shippable.

## Acceptance Criteria

- [ ] Package README (or docs) has a “Peer support / D-011 release gate” section listing: matrix file location, required unit contract suite filters, fail/disable boot behaviour, AdapterApi bump rule
- [ ] Section explicitly states default package CI does **not** install live laravel/ai or laravel/mcp
- [ ] Section documents optional consumer peer-live steps (install peers, run app tests, confirm matrix cell)
- [ ] Spec D-011 section is not contradicted (update only if needed for accuracy, do not delete design bible)
- [ ] Unit test or lightweight check may guard README section anchors if chosen — still unit-only

## Verification Steps

1. **test** `rg -n "D-011|Peer support|release gate" packages/laravel-capabilities/README.md docs/spec.md | head -20`
   - Expected: release-gate documentation hits present
2. **test** `composer test:core -- --filter=Adapter 2>&1 | tail -30`
   - Expected: still green after doc-only or light guards

## Integration

**Reachability:** Maintainer README; consumer copy of optional checklist

**Data dependencies:** PeerSupportMatrix source of truth (REQ-036)

**Service dependencies:** none runtime — documentation + optional guard tests

## Assets

- docs/spec.md D-011
- packages/laravel-capabilities/README.md
