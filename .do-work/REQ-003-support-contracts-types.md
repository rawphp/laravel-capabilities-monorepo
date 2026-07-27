# REQ-003: Core support types and contracts

**UR:** UR-001
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Rawphp\Capabilities\Support\* and Contracts\* used by registry and tests
**Terminal state:** CapabilityData, CapabilityResult, SystemActor, and core contracts exist with Unit tests for Support/* and Contracts/* inventory scenarios passing (or reduced to intentional skip only if out of scope — prefer pass).
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Support packages/laravel-capabilities/src/Contracts packages/laravel-capabilities/src/Attributes packages/laravel-capabilities/tests/Unit/Support packages/laravel-capabilities/tests/Unit/Contracts
**Depends on:** REQ-002

## Task

Implement package-native DTOs and contracts: CapabilityData, CapabilityResult, SystemActor, actor/scope/caller types, and interfaces for registry stores (approval, idempotency, audit, etc.). Flesh Support/* and Contracts/* unit tests from inventory stubs into real asserts. Spec: typed DTOs on authorize/run/output; array only at wire edges.

## Context

Foundation types for the whole bus. Spec + AGENTS: CapabilityData, SystemActor (D-002), results/error envelopes later.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] CapabilityData and CapabilityResult are usable typed types with unit tests asserting construction and key behaviours from Support/* inventory
- [ ] SystemActor exists and Support/SystemActorTest scenarios pass
- [ ] Contracts needed by later pipeline stages are defined (interfaces only if implementation is elsewhere)
- [ ] Unit tests only; ≥95% coverage on files this REQ adds under src/Support and src/Contracts (or package still on track)

## Verification Steps

1. **test** `composer test:core -- --filter=Support 2>&1 | tail -50`
   - Expected: Support unit tests pass (no todo failures for implemented files)
2. **test** `composer test:core -- --filter=Contracts 2>&1 | tail -40`
   - Expected: Contracts-related unit tests pass or inventory files for Contracts green

## Integration

**Reachability:** Imported by CapabilityRegistry, adapters, and app capability classes

**Data dependencies:** CapabilityData DTOs carry invoke payloads; no DB

**Service dependencies:** Consumed by Registry, Schema, Approval, Idempotency packages

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
