# REQ-033: Wire database store bindings

**UR:** UR-004
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-029
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Boot/ContainerBindings.php packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/config/capabilities.php packages/laravel-capabilities/tests/Unit/Boot packages/laravel-capabilities/tests/Unit/Persistence
**Depends on:** REQ-031, REQ-032

## Task

Wire `ContainerBindings` / provider so `approval.store=database` and `idempotency.driver=database` construct the new DB-backed stores (not silent memory fallback with `package_default: true`). Keep memory drivers for tests. Fail closed on unknown drivers. Optionally document env keys for host apps.

## Context

REQ-023 introduced config-driven resolve with database → memory package_default. This REQ replaces that fallback with real concretes once REQ-031/032 exist. Hosts that still want memory keep explicit `memory` config.

## Acceptance Criteria

- [ ] `ContainerBindings::resolve` for database approval store sets `package_default: false` and concrete to Database ApprovalStore class
- [ ] Same for idempotency database driver
- [ ] `makeApprovalManager` / `makeIdempotencyStore` construct DB-backed types when config says database (may inject connection factory that unit tests replace)
- [ ] Memory drivers still construct InMemory* types
- [ ] Unknown drivers still throw BootException
- [ ] Unit tests cover resolve matrix memory vs database without live DB
- [ ] Existing Boot/ConfigDriven bindings tests updated and green

## Verification Steps

1. **test** `composer test:core -- --filter=ConfigDrivenBindings 2>&1 | tail -40`
   - Expected: pass with updated database expectations
2. **test** `composer test:core -- --filter=ContainerBindings 2>&1 | tail -30`
   - Expected: pass

## Integration

**Reachability:** `CapabilitiesServiceProvider::register()` factories

**Data dependencies:** config keys `approval.store`, `idempotency.driver`

**Service dependencies:** REQ-031/032 classes, `Boot\ContainerBindings`

## Assets

- packages/laravel-capabilities/src/Boot/ContainerBindings.php
