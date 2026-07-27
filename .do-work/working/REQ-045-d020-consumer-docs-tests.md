# REQ-045: D-020 consumer docs and inventory honesty

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T05:52:39Z
**Heartbeat:** 2026-07-27T05:52:39Z
<!-- claimed-end -->

**UR:** UR-007
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** M
**Files:** docs/spec.md docs/tutorials/first-capability.md packages/laravel-capabilities/README.md packages/laravel-capabilities/tests/Unit/TestingHelpers docs/requirements-inventory.md
**Depends on:** REQ-043, REQ-044

## Task

Document full D-020 consumer workflow (schema snapshots + parity in app CI) against the **implemented** helper APIs from REQ-043/044; update package README testing section; align first-capability tutorial testing section if present; ensure inventory/test titles no longer claim behaviour that is only presence-level. Prefer intentional inventory sync via project tools if needed (`tools/sync_requirements_inventory.py`) after suite truth changes — do not leave green checkboxes for no-op helpers.

## Context

Brief: schema snapshot can check equality but not full D-020 DX; consumer helpers thin. Spec D-020 “Document: app CI should run snapshots for every capability before release.” Path-unit needs docs honesty coupled to real helpers. Depends on 043/044 so docs match code.

## Acceptance Criteria

- [ ] Package README (or `docs/` testing section linked from it) documents `assertSchemaSnapshot` and `assertParity` with real argument shapes matching implementation
- [ ] Spec D-020 prose matches shipped helper behaviour (update only if implementation differs from old example — keep dual-path prevention intent)
- [ ] First-capability tutorial (if already added by REQ-042) links or embeds a minimal testing snippet using the real helpers
- [ ] No documentation claims empty-arg assertParity alone proves multi-surface parity
- [ ] Unit suite for TestingHelpers does not retain “exists for package consumers” as the only assert for parity/snapshot
- [ ] Inventory status for D-020 scenarios is consistent with suite after sync (or documented intentional deltas)

## Verification Steps

1. **test** `composer test:core -- --filter=TestingHelpers 2>&1 | tail -60`
   - Expected: green after docs+tests alignment
2. **runtime** `rg -n "assertSchemaSnapshot|assertParity" packages/laravel-capabilities/README.md docs/spec.md docs/tutorials 2>/dev/null | head -40`
   - Expected: consumer-facing docs mention both helpers with non-empty usage examples
3. **runtime** `rg -n "method_exists.*assertParity|assertParity\\(\\)->toBeTrue" packages/laravel-capabilities/tests/Unit/TestingHelpers || true`
   - Expected: zero leftover presence-only parity assertions (or only explicitly named “rejects empty usage” cases)

## Integration

**Reachability:** Developers reading package README / tutorial / spec D-020 section after helpers ship

**Data dependencies:** Implemented method signatures on `CapabilityRegistry` / facade from REQ-043/044

**Service dependencies:** None beyond documentation and unit tests
