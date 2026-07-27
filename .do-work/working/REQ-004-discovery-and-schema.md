# REQ-004: Discovery attributes and schema pipeline

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T00:53:13Z
**Heartbeat:** 2026-07-27T00:53:13Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** #[Capability] / Capability::define + DTO→JSON Schema validation
**Terminal state:** Discovery and Schema unit tests (Discovery/*, Schema/* inventory) pass with real validation behaviour; portable JSON Schema derived from DTOs.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Attributes packages/laravel-capabilities/src/Schema packages/laravel-capabilities/src/Registry packages/laravel-capabilities/tests/Unit/Discovery packages/laravel-capabilities/tests/Unit/Schema
**Depends on:** REQ-003

## Task

Implement capability discovery (attribute + fluent define per D-017) and schema: DTO hydration, JSON Schema generation, portable vs server-only validation, output validation (D-014). Replace Discovery/* and Schema/* todos with real unit tests + production code. No Laravel rule strings as sole SOT.

## Context

Catalog and tools share the same JSON Schema. CLI validates portable schema locally; server is law.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Attribute and fluent discovery paths register definitions with name, description, schema, surfaces, mutating flags as required by inventory Discovery tests
- [ ] DTO→JSON Schema generation and input validation cover Schema/* happy/fail cases from inventory
- [ ] Output validation fails closed (no success to client on invalid output) per D-014 scenarios
- [ ] Tests use mocks/fakes only; no DB

## Verification Steps

1. **test** `composer test:core -- --filter=Discovery 2>&1 | tail -50`
   - Expected: Discovery unit tests pass
2. **test** `composer test:core -- --filter=Schema 2>&1 | tail -50`
   - Expected: Schema unit tests pass

## Integration

**Reachability:** Service provider discovery + Capability::define at boot; schema used by registry pipeline stages

**Data dependencies:** Definition metadata in registry memory

**Service dependencies:** CapabilityRegistry, Http catalog, AI/MCP tool schema export

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
