# REQ-035: Peer contract residual path

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.95896
**Claimed at:** 2026-07-27T05:40:22Z
**Heartbeat:** 2026-07-27T05:40:22Z
<!-- claimed-end -->

**UR:** UR-006
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Maintainer prepares a release that touches AI/MCP adapters or the peer support matrix; consumer app may optionally run peer-live checks against installed `laravel/ai` / `laravel/mcp`
**Terminal state:** D-011 residual is non-aspirational: explicit support matrix drives probe defaults; frozen contract fixtures + adapter unit tests catch bridge drift without live SDKs in default CI; release-gate docs describe matrix/contract requirements and an optional consumer peer-live path; default `composer test:core` still never requires live `laravel/ai` or `laravel/mcp`
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** packages/laravel-capabilities/src/Adapters packages/laravel-capabilities/config packages/laravel-capabilities/tests/Unit/Adapters docs packages/laravel-capabilities/README.md
**Depends on:** REQ-036, REQ-037, REQ-038

## Task

Path-unit for D-011 residual peer-contract hardening. Child REQs implement matrix, fixtures/tests, and release-gate docs; this REQ defines reachability and closure only.

## Context

Brief: adapters are contract-shaped (tool arrays, class_exists probe, no live peers in CI) — honest monorepo policy, but peer churn risk remains aspirational for real minors. Spec D-011 wants matrix + contract tests + AdapterApi + fail/disable + release gate.

## Acceptance Criteria

- [ ] Child REQs REQ-036–REQ-038 are done and verification steps pass
- [ ] Default package CI / `composer test:core` does not require installed `laravel/ai` or `laravel/mcp`
- [ ] Support matrix is machine-readable and non-empty for ai and mcp peers
- [ ] Adapter unit/contract fixtures fail if declared wire shapes drift
- [ ] Release-gate documentation states matrix + contract obligations and optional consumer peer-live path

## Verification Steps

1. **test** `composer test:core -- --filter=Adapter 2>&1 | tail -50`
   - Expected: adapter-related unit tests pass without live peer packages
2. **test** `composer test:core -- --filter=Peer 2>&1 | tail -40`
   - Expected: peer probe/matrix tests pass

## Manual checks (advisory)

- [ ] In a consumer app with real laravel/ai installed, run the documented optional peer-live checklist once — Observable outcome: matrix cell and probe report align with installed version

## Integration

**Reachability:** Package release process + consumer install of suggested peers

**Data dependencies:** Peer support matrix file, AdapterApi CURRENT

**Service dependencies:** `PeerVersionProbe`, `AiToolAdapterV1`, `McpToolAdapterV1`, `PeerSurfaceBootstrap`

## Assets

- docs/spec.md D-011
- .do-work/user-requests/UR-006/ideate.md
