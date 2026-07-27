# REQ-013: AI and MCP peer adapters

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T02:06:53Z
**Heartbeat:** 2026-07-27T02:06:53Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** laravel/ai tools + laravel/mcp tools via adapters
**Terminal state:** Surfaces/Ai* Mcp/* Adapters/* unit tests pass against mocked peers; profiles limit tool dump (D-008/D-011/D-023).
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Adapters packages/laravel-capabilities/tests/Unit/Surfaces/AiAdapterTest.php packages/laravel-capabilities/tests/Unit/Surfaces/AiToolFailurePointsTest.php packages/laravel-capabilities/tests/Unit/Surfaces/McpAdapterTest.php packages/laravel-capabilities/tests/Unit/Surfaces/McpToolFailurePointsTest.php packages/laravel-capabilities/tests/Unit/Mcp packages/laravel-capabilities/tests/Unit/Adapters
**Depends on:** REQ-010

## Task

Implement AI and MCP adapters as thin bridges (D-011): tool handle → registry, MCP auth profiles (D-023), confused deputy tests, AdapterApi versioning, peer contract tables — all unit-tested with mocks/fakes of peer interfaces, never live peers.

## Context

Compose laravel/ai and laravel/mcp; do not reimplement LLM or MCP wire protocol.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] AiAdapter and McpAdapter unit tests pass
- [ ] MCP auth profile and confused-deputy scenarios pass
- [ ] AdapterApi version / contract table tests pass
- [ ] No live laravel/ai or laravel/mcp required in CI

- [ ] When peer interfaces are missing while surface enabled, adapter registration fails closed/soft-disables without calling live peers

## Verification Steps

1. **test** `composer test:core -- --filter=Ai 2>&1 | tail -40`
   - Expected: AI-related unit tests pass
2. **test** `composer test:core -- --filter=Mcp 2>&1 | tail -40`
   - Expected: MCP unit tests pass
3. **test** `composer test:core -- --filter=Adapter 2>&1 | tail -40`
   - Expected: Adapters unit tests pass

## Integration

**Reachability:** Registered when surfaces.agent/mcp enabled and peers present; soft-disable/fail closed per boot rules

**Data dependencies:** Tool schemas from capability DTOs

**Service dependencies:** CapabilityRegistry + profile catalog

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
