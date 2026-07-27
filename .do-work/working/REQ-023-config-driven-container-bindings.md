# REQ-023: Config-driven container bindings

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.24443
**Claimed at:** 2026-07-27T04:45:13Z
**Heartbeat:** 2026-07-27T04:45:13Z
<!-- claimed-end -->

**UR:** UR-002
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-020
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/src/Boot/ContainerBindings.php packages/laravel-capabilities/src/Boot/CapabilitiesConfig.php packages/laravel-capabilities/config/capabilities.php packages/laravel-capabilities/tests/Unit/Boot
**Depends on:**

## Task

Construct registry and related services from full `config/capabilities.php` (surfaces, audit, approval, clients/drivers) instead of hard-coding empty `CapabilityRegistry` + unconditional in-memory stores. Keep unit-testable pure binding plans; provider applies the plan.

## Context

Provider today binds `InMemoryIdempotencyStore`, empty `CapabilityRegistry`, and `ApprovalManager::inMemory()` regardless of config. Config already declares audit driver/mode, approval store, path, surfaces, clients. Connector: reuse `ContainerBindings`, `CapabilitiesConfig`, `RegistrationPlan` — extend the plan so config selects drivers; do not invent a second binding map.

Challenger: silent memory stores in production are dangerous for idempotency/approvals — defaults must be explicit (testing memory vs configured driver), and missing production drivers fail closed or document clearly without requiring a feature suite.

## Acceptance Criteria

- [ ] Binding plan is a pure function of config (surfaces, audit, approval, and related keys) and is unit-tested without booting a full app DB
- [ ] `CapabilityRegistry` singleton is constructed so discovery and fluent registration share one map (D-017)
- [ ] Audit mode / approval store / idempotency driver selection is driven by config keys already present in `config/capabilities.php` (or documented extensions of that file)
- [ ] When a configured driver is “memory” or testing, in-memory implementations remain available for unit tests
- [ ] Provider `register()` applies the plan (not only static plan helpers unused by boot)
- [ ] Existing Boot/Config unit tests remain green or are intentionally updated with failing-first coverage for the new plan

## Verification Steps

1. **test** `composer test:core -- --filter=ContainerBindings 2>&1 | tail -40`
   - Expected: plan tests pass for memory vs configured drivers / audit mode matrix
2. **test** `composer test:core -- --filter=Boot 2>&1 | tail -50`
   - Expected: Boot suite green; no DB connection required

## Integration

**Reachability:** `CapabilitiesServiceProvider::register()` applies `ContainerBindings` plan

**Data dependencies:** `packages/laravel-capabilities/config/capabilities.php` (`audit`, `approval`, `surfaces`, `path`, clients if present)

**Service dependencies:** `Boot\ContainerBindings`, `Boot\CapabilitiesConfig`, `Registry\CapabilityRegistry`, `Support\InMemoryIdempotencyStore`, `Approval\ApprovalManager`

## Assets

- docs/spec.md — boot / config sections
