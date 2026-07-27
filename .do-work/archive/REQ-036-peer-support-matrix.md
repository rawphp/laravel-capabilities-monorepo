# REQ-036: Peer support matrix source of truth


**UR:** UR-006
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-035
**Closure proof:** checkpoint:.do-work/runs/RUN-033.yml#REQ-036 commit:f3b7578 tests:passed
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/Adapters/PeerSupportMatrix.php packages/laravel-capabilities/src/Adapters/PeerVersionProbe.php packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/src/Boot/CapabilitiesConfig.php packages/laravel-capabilities/config/capabilities.php packages/laravel-capabilities/tests/Unit/Adapters/PeerSupportMatrixTest.php packages/laravel-capabilities/README.md
**Depends on:**

## Task

Add a machine-readable peer support matrix (PHP and/or config) for `laravel/ai` and `laravel/mcp` that lists supported version constraints (not bare `*` forever). Wire `PeerVersionProbe` defaults to this matrix so compatibility is matrix-driven. Unit-test matrix shape and probe behaviour with injected versions — no live peers.

## Context

D-011 requires an explicit support matrix. Probe currently defaults supportedVersions to `['*']`. Brief: peer churn risk remains because CI never pins real minors; matrix is the package’s honest declared support surface.

## Acceptance Criteria

- [x] Single source of truth documents supported peers and version constraints (e.g. `PeerSupportMatrix` class and/or config key under capabilities)
- [x] Matrix includes both `laravel/ai` and `laravel/mcp` with non-empty constraint lists
- [x] `PeerVersionProbe` uses matrix defaults (tests can still inject overrides)
- [x] Incompatible version (injected) reports incompatible / not half-registered tools via existing bootstrap behaviour
- [x] Unit tests cover matrix non-empty + version match/mismatch without installing live peer packages
- [x] README or package README references the matrix (short pointer is enough)

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

## Outputs

- packages/laravel-capabilities/src/Adapters/PeerSupportMatrix.php — Machine-readable peer support matrix (SOT)
- packages/laravel-capabilities/src/Adapters/PeerVersionProbe.php — Probe defaults to PeerSupportMatrix
- packages/laravel-capabilities/src/CapabilitiesServiceProvider.php — Bind PeerVersionProbe from config peers.support
- packages/laravel-capabilities/src/Boot/CapabilitiesConfig.php — peers in TOP_LEVEL_KEYS
- packages/laravel-capabilities/config/capabilities.php — peers.support key
- packages/laravel-capabilities/tests/Unit/Adapters/PeerSupportMatrixTest.php — Unit tests for matrix/probe
- packages/laravel-capabilities/README.md — Pointer to PeerSupportMatrix / D-011

