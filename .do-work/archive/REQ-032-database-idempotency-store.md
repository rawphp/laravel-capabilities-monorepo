# REQ-032: Database IdempotencyStore


**UR:** UR-004
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-029
**Closure proof:** checkpoint_log:passed commit:5ece838 tests:DatabaseIdempotencyStore+core
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Persistence packages/laravel-capabilities/src/Idempotency packages/laravel-capabilities/src/Contracts/IdempotencyStore.php packages/laravel-capabilities/src/Support/InMemoryIdempotencyStore.php packages/laravel-capabilities/tests/Unit/Persistence packages/laravel-capabilities/tests/Unit/Idempotency
**Depends on:** REQ-030

## Task

Implement a production `Contracts\IdempotencyStore` using the idempotency table so mutating invoke outcomes survive process restart (D-005). Match identity composite (tenant, actor_type, actor_id, capability_name, key), TTL/expiry-as-missing, and put/find semantics of the in-memory implementations. Unit-test with mocks/fakes — no live DB.

## Context

Brief: agent retries need durable outcomes. Config defaults and domain `Idempotency\IdempotencyStore` are array-backed; production DB driver was deferred.

## Acceptance Criteria

- [x] Class implements `Contracts\IdempotencyStore`
- [x] find returns null for missing or expired rows
- [x] put stores/replaces by composite identity and returns the record
- [x] Unique identity prevents cross-tenant or cross-actor key collisions at the store API level (documented unique index in migration)
- [x] Unit tests cover happy path, expiry, and conflict/replace behaviour with mocks/fakes
- [x] Existing in-memory idempotency unit tests remain green

## Verification Steps

1. **test** `composer test:core -- --filter=DatabaseIdempotency 2>&1 | tail -40`
   - Expected: new store tests pass
2. **test** `composer test:core -- --filter=Idempotency 2>&1 | tail -50`
   - Expected: idempotency suite green

## Integration

**Reachability:** Bound when `idempotency.driver=database` (REQ-033); pipeline IdempotencyGuard / registry

**Data dependencies:** idempotency migration (REQ-030)

**Service dependencies:** `Contracts\IdempotencyStore`, `Idempotency\IdempotencyConfig`, Clock

## Assets

- packages/laravel-capabilities/src/Support/InMemoryIdempotencyStore.php
- packages/laravel-capabilities/src/Idempotency/IdempotencyStore.php
- docs/spec.md D-005


## Outputs

- packages/laravel-capabilities/src/Persistence/DatabaseIdempotencyStore.php — durable idempotency store
