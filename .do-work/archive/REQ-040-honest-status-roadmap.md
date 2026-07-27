# REQ-040: Honest status and roadmap


**UR:** UR-007
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:** checkpoint_log:passed commit:efcfe43
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** README.md AGENTS.md docs/spec.md packages/laravel-capabilities/README.md
**Depends on:**

## Task

Replace vague “future / unpublished only” messaging with an honest consumer readiness picture: monorepo unit-complete status, pre-Packagist packaging stance, roadmap phases vs residuals (what is unit-covered vs not production-published), and an explicit note that D-020 helpers are being thickened (do not claim full dual-path parity until REQ-043/044 land — if shipping docs first, label helpers partial).

## Context

Brief: README/spec advertise future/unpublished while v0.1–v0.5 look largely implemented in unit form; consumer cannot tell stable API from REQ-driven monorepo. Connector: mirror UR-006 release-gate honesty (matrix + residuals) rather than marketing “stable.” Do not invent Packagist publish. Spec roadmap table at end of `docs/spec.md`; root README status banner.

## Acceptance Criteria

- [x] Root `README.md` status banner states monorepo / unit-tested design status and that packages are not Packagist-published (or equivalent honest phrasing)
- [x] A readiness residual table (or equivalent) lists at least: packaging/publish, release notes, first-capability tutorial, D-020 helper completeness, live peer CI — with current residual or done markers consistent with code at commit time
- [x] `docs/spec.md` roadmap section gains status/residual columns or an adjacent “status vs roadmap” note so unit-green is not read as shipped product
- [x] `AGENTS.md` product status line aligns with README (no contradiction: future-only vs unit-complete monorepo)
- [x] Docs do not claim “stable public API” or full D-020 multi-surface parity unless corresponding code REQs are already merged; partial state is labeled partial
- [x] Historical “future package design” wording is updated, not merely duplicated beside new status text

## Verification Steps

1. **runtime** `rg -n "future package design|not published|Roadmap|readiness|residual|stable" README.md AGENTS.md docs/spec.md packages/laravel-capabilities/README.md 2>/dev/null | head -80`
   - Expected: honest status language present; bare “future package design” alone is not the only status signal
2. **runtime** `rg -n "Packagist|path repositor|monorepo|unit" README.md docs/spec.md | head -40`
   - Expected: consumer can see monorepo/path vs published packaging stance

## Outputs

- README.md — status banner + residual table
- AGENTS.md — status alignment
- docs/spec.md — roadmap status columns
- packages/laravel-capabilities/README.md — package status banner
