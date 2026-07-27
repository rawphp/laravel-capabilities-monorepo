# REQ-051: Wire gateway into database bindings

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.40419
**Claimed at:** 2026-07-27T06:33:56Z
**Heartbeat:** 2026-07-27T06:33:56Z
<!-- claimed-end -->

**UR:** UR-008
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-049
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/config/capabilities.php, packages/laravel-capabilities/tests/Unit/Boot/ContainerBindingsTest.php
**Depends on:** REQ-050

## Task

Wire the first-party query TableGateway into `makeApprovalManager` / `makeIdempotencyStore` / registry factory when drivers resolve to `database`, replacing silent `new ArrayTableGateway` default in production binding path while keeping ArrayTableGateway for explicit memory tests and unit isolation.

## Context

Today: `makeIdempotencyStore` / `makeApprovalManager` use `$gateway ?? new ArrayTableGateway` even for database driver. SP plan maps `TableGateway::class => ArrayTableGateway::class`. Need dual-table support (approvals vs idempotency tables) — likely factory per table name, not single gateway for both unless multi-table capable.

## Acceptance Criteria

- [ ] Database driver path constructs QueryTableGateway (or equivalent) with correct table name for approval and for idempotency
- [ ] Memory driver path still uses in-memory stores / ArrayTableGateway as appropriate
- [ ] Optional host-bound `TableGateway` or connection from container is honored when present
- [ ] Unit tests prove database resolution no longer instantiates only ArrayTableGateway as the sole production default (spy/factory)
- [ ] Config comments or keys document driver + connection name if needed
- [ ] Works with REQ-047/048 registry inject so database stores reach the registry

## Verification Steps

1. **test** `composer test:core -- --filter=ContainerBindings`
   - Expected: binding tests pass for database gateway wiring
2. **test** `composer test:core -- --filter=makeApproval`
   - Expected: covered or green
3. **test** `composer test:core`
   - Expected: suite green

## Integration

**Reachability:** `ContainerBindings::makeApprovalManager`, `makeIdempotencyStore`, `makeRegistry`; SP singletons.

**Data dependencies:** `config/capabilities.php` approval.store, idempotency.driver; MigrationCatalog table names.

**Service dependencies:** REQ-050 QueryTableGateway; existing Database* stores.
