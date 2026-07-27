# REQ-005: Registry invoke pipeline choke point


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:** CapabilityRegistry::invoke (and Capability::invoke facade) from any caller
**Terminal state:** Successful invoke runs ordered stages; stage failures never call run(); inventory Registry/* and Pipeline/* scenarios pass.
**Parent:** 
**Closure proof:** checkpoint_log:passed (3/3) commit:63c4aec Registry:560 Pipeline:392 Facade:28
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Registry packages/laravel-capabilities/src/Pipeline packages/laravel-capabilities/src/Facades packages/laravel-capabilities/src/Approval/ApprovalManager.php packages/laravel-capabilities/src/Events/CapabilityInvoked.php packages/laravel-capabilities/src/Events/CapabilityApprovalRequested.php packages/laravel-capabilities/src/Schema/InputValidator.php packages/laravel-capabilities/tests/Unit/Registry packages/laravel-capabilities/tests/Unit/Pipeline packages/laravel-capabilities/tests/Unit/Facades packages/laravel-capabilities/tests/Fixtures/PipelineHelpers.php
**Depends on:** REQ-004

## Task

Implement CapabilityRegistry as the single choke point: validate → hydrate → server-only → actor → scope → idempotency → authorize → approval → rateLimit → run → output → audit → events (per PIPE-001). Stage fail matrices (PIPE-002), caller parity hooks, facade methods. Flesh Registry/* Pipeline/* Facades/* unit tests with fakes; run() never dual-path.

## Context

Core product law: one run(). Adapters are dumb. Spec pipeline + AGENTS MUST NOT dual mutation paths.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] Happy-path invoke executes stages in documented order and calls capability run once
- [x] Each pre-run stage failure prevents run() and returns correct error envelope (PIPE-002 family)
- [x] Unknown capability / disabled surface behaviours match inventory
- [x] Facades/CapabilityFacade tests pass for exposed invoke/list entry points
- [x] Unit tests only with injected fakes

## Verification Steps

1. **test** `composer test:core -- --filter=Registry 2>&1 | tail -60`
   - Expected: Registry unit tests pass
2. **test** `composer test:core -- --filter=Pipeline 2>&1 | tail -30`
   - Expected: Pipeline unit tests pass
3. **test** `composer test:core -- --filter=Facade 2>&1 | tail -30`
   - Expected: Facade unit tests pass

## Integration

**Reachability:** In-process Capability::invoke / registry; all surface adapters call this only

**Data dependencies:** Capability definitions + invoke DTOs

**Service dependencies:** Schema, Approval, Idempotency, Audit, Authorizer, RateLimiter contracts

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- packages/laravel-capabilities/src/Registry/CapabilityRegistry.php — full invoke pipeline
- packages/laravel-capabilities/src/Pipeline/* — stage units
- packages/laravel-capabilities/src/Facades/Capability.php — facade entry
- packages/laravel-capabilities/tests/Unit/Registry|Pipeline|Facades — inventory green
