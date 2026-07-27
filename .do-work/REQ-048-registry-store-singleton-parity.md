# REQ-048: Registry store singleton parity

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-046
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/tests/Unit/Boot/ServiceProviderTest.php, packages/laravel-capabilities/tests/Unit/Boot/LaravelGlueBootPathTest.php
**Depends on:** REQ-047

## Task

Ensure the container’s `CapabilityRegistry` and the dedicated `ApprovalManager` / `IdempotencyStore` singletons share the same underlying store instances (or equivalent identity) so invoke and accept paths cannot diverge.

## Context

Ideate Challenger: dual ApprovalManager binding under concurrent accept/invoke. SP currently binds registry, ApprovalManager, and IdempotencyStore independently. After REQ-047 wiring, still verify provider registration order and shared gateways/stores when drivers are database or memory.

## Acceptance Criteria

- [ ] Resolving registry and ApprovalManager for the same config yields the same approval store instance (or documented shared gateway that makes compareAndUpdate/find consistent)
- [ ] Resolving registry and IdempotencyStore yields the same idempotency store instance
- [ ] Unit test covers memory and database driver resolution paths with ArrayTableGateway (no live DB)
- [ ] When gateway is injected for database drivers, both registry and ApprovalManager use it
- [ ] No silent re-create of in-memory managers inside registry after SP registration

## Verification Steps

1. **test** `composer test:core -- --filter=ServiceProvider`
   - Expected: parity tests pass
2. **test** `composer test:core -- --filter=singleton`
   - Expected: green or covered by ServiceProvider/LaravelGlue tests
3. **test** `composer test:core`
   - Expected: suite green

## Integration

**Reachability:** `CapabilitiesServiceProvider::register` bindings for `CapabilityRegistry`, `ApprovalManager`, `IdempotencyStore`.

**Data dependencies:** same `config('capabilities')` snapshot as factory.

**Service dependencies:** `ContainerBindings::makeRegistry`, `makeApprovalManager`, `makeIdempotencyStore`, optional shared `TableGateway` binding.
