# REQ-036: Peer support matrix source of truth

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.81144
**Claimed at:** 2026-07-27T05:22:56Z
**Heartbeat:** 2026-07-27T05:22:56Z
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
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/Adapters/PeerSupportMatrix.php packages/laravel-capabilities/src/Adapters/PeerVersionProbe.php packages/laravel-capabilities/config/capabilities.php packages/laravel-capabilities/tests/Unit/Adapters packages/laravel-capabilities/README.md
**Depends on:**

## Task

Add a machine-readable peer support matrix (PHP and/or config) for `laravel/ai` and `laravel/mcp` that lists supported version constraints (not bare `*` forever). Wire `PeerVersionProbe` defaults to this matrix so compatibility is matrix-driven. Unit-test matrix shape and probe behaviour with injected versions — no live peers.

## Context

D-011 requires an explicit support matrix. Probe currently defaults supportedVersions to `['*']`. Brief: peer churn risk remains because CI never pins real minors; matrix is the package’s honest declared support surface.

## Acceptance Criteria

- [ ] Single source of truth documents supported peers and version constraints (e.g. `PeerSupportMatrix` class and/or config key under capabilities)
- [ ] Matrix includes both `laravel/ai` and `laravel/mcp` with non-empty constraint lists
- [ ] `PeerVersionProbe` uses matrix defaults (tests can still inject overrides)
- [ ] Incompatible version (injected) reports incompatible / not half-registered tools via existing bootstrap behaviour
- [ ] Unit tests cover matrix non-empty + version match/mismatch without installing live peer packages
- [ ] README or package README references the matrix (short pointer is enough)

## Verification Steps

1. **test** `composer test:core -- --filter=PeerSupportMatrix 2>&1 | tail -40`
   - Expected: matrix tests pass
2. **test** `composer test:core -- --filter=PeerVersionProbe 2>&1 | tail -40`
   - Expected: probe tests pass with matrix-backed defaults

## Integration

**Reachability:** Boot / PeerVersionProbe construction; docs for maintainers

**Data dependencies:** matrix constants/config

**Service dependencies:** `PeerVersionProbe`, `PeerSurfaceBootstrap`, `AdapterApi`

## Assets

- docs/spec.md peer support matrix / D-011
