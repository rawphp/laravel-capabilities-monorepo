# REQ-008: Idempotency keys and outcome store


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Idempotency-Key on mutating invokes
**Terminal state:** Replays return stored outcomes without re-run; conflict/hash/key format matrices pass for Idempotency/*.
**Parent:** 
**Closure proof:** checkpoint_log:passed commit:a32c48c Idempotency:184
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Idempotency, packages/laravel-capabilities/src/Pipeline/IdempotencyGuard.php, packages/laravel-capabilities/src/Registry/CapabilityRegistry.php, packages/laravel-capabilities/src/Schema/CatalogPresenter.php, packages/laravel-capabilities/src/Support/InMemoryIdempotencyStore.php, packages/laravel-capabilities/tests/Unit/Idempotency, packages/laravel-capabilities/tests/Fixtures/IdempotencyHelpers.php
**Depends on:** REQ-005

## Task

Implement idempotency for mutating invokes (D-005): key format, request hash, store outcomes, replay vs conflict. Flesh Idempotency/* unit tests with in-memory store.

## Context

Mutating invokes support idempotency keys; store outcomes, not hope.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] Completed result replay skips run()
- [x] Key format and status/hash matrix scenarios pass
- [x] Wire format / storage identity matrices pass
- [x] Unit-only fakes

- [x] Conflicting idempotency payload for same key is rejected or returns stored outcome without re-running (per D-005 inventory fail cases)

## Verification Steps

1. **test** `composer test:core -- --filter=Idempotency 2>&1 | tail -50`
   - Expected: Idempotency tests pass

## Integration

**Reachability:** Pipeline idempotency_lookup stage; HTTP/CLI headers

**Data dependencies:** Idempotency store rows

**Service dependencies:** CapabilityRegistry

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- packages/laravel-capabilities — implementation commit a32c48c
