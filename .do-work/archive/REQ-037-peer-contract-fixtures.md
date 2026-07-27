# REQ-037: Frozen peer contract fixtures and adapter shape tests


**UR:** UR-006
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-035
**Closure proof:** checkpoint:.do-work/runs/RUN-034.yml#REQ-037 commit:f5e5015 tests:passed
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Adapters/PeerVersionProbe.php packages/laravel-capabilities/tests/Fixtures/PeerContractFixtures.php packages/laravel-capabilities/tests/Unit/Adapters/PeerContractFixturesTest.php
**Depends on:** REQ-036

## Task

Ship frozen peer-facing contract fixtures (tool array shape, registration call shape, expected probe class lists, AdapterApi shape keys) and unit tests that fail when AI/MCP adapters drift from those fixtures. Keep all tests mock/fake-based — never require live `laravel/ai` or `laravel/mcp` packages.

## Context

Adapters map tools to arrays and probe via class_exists. Contract fixtures make “contract-shaped” verifiable and reduce silent peer churn when our bridge mapping changes. AdapterApi::requiresBump already exists; extend with fixture snapshots for tool schema mapping and structured responses.

## Acceptance Criteria

- [x] Fixture file(s) define expected AI tool map keys/shape and MCP tool registration shape for AdapterApi V1
- [x] Unit tests assert `AiToolAdapterV1` / `McpToolAdapterV1` (or exporters) produce fixture-compatible shapes with mock registry/definitions
- [x] Unit tests assert probe PEER_CLASSES / matrix cells remain documented in fixtures
- [x] AdapterApi CURRENT and supported() covered; requiresBump still fails on shape change
- [x] Default suite does not load or require live peer package classes (class_exists fakes only)
- [x] Existing PeerAdapters / ContractTable tests remain green or intentionally updated

## Verification Steps

1. **test** `composer test:core -- --filter=Contract 2>&1 | tail -40`
   - Expected: contract fixture tests pass
2. **test** `composer test:core -- --filter='AiTool|McpTool|AdapterApi' 2>&1 | tail -50`
   - Expected: adapter suites green without live peers

## Integration

**Reachability:** Pest unit suite under `tests/Unit/Adapters`

**Data dependencies:** fixtures under tests/Fixtures or Adapters fixtures

**Service dependencies:** `AiToolAdapterV1`, `McpToolAdapterV1`, `AdapterApi`, `ToolSelection`, `StructuredToolResponse`

## Assets

- packages/laravel-capabilities/tests/Unit/Adapters/ContractTableTest.php
- packages/laravel-capabilities/src/Adapters/AdapterApi.php

## Outputs

- packages/laravel-capabilities/tests/Fixtures/PeerContractFixtures.php — Frozen AdapterApi V1 peer contract snapshots
- packages/laravel-capabilities/tests/Unit/Adapters/PeerContractFixturesTest.php — Drift-detection unit tests
- packages/laravel-capabilities/src/Adapters/PeerVersionProbe.php — peerClasses() accessor for fixture lockstep

