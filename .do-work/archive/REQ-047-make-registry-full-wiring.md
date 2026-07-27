# REQ-047: makeRegistry full config wiring


**UR:** UR-008
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-046
**Closure proof:** checkpoint_log:passed (3/3) commit:4482f3b
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/src/Registry/CapabilityRegistry.php, packages/laravel-capabilities/tests/Unit/Boot/ContainerBindingsTest.php, packages/laravel-capabilities/tests/Unit/Boot/ConfigDrivenBindingsTest.php
**Depends on:**

## Task

Implement `ContainerBindings::makeRegistry(array $config)` so it applies config and injects approval store, idempotency store, audit settings, and scope resolver (no more `unset($config)` bare factory).

## Context

Hotspot: `ContainerBindings::makeRegistry` returns `new CapabilityRegistry(clock: new SystemClock)` only. Registry already has `withApprovalStore`, `withIdempotencyStore`, `withAuditConfig`, `withAuditWriter`, `withScopeResolver`, `withGloballyEnabledSurfaces`, rate/tool/events/transactions helpers. Reuse those; do not reimplement pipeline. Unit-only monorepo policy — pure construction tests with fakes.

## Acceptance Criteria

- [x] `makeRegistry` uses config (or defaults) for globally enabled surfaces from `surfaces.*.enabled`
- [x] Injects approval store consistent with `makeApprovalManager` driver resolution for the same config
- [x] Injects idempotency store consistent with `makeIdempotencyStore` for the same config
- [x] Applies audit mode/enabled/required/driver-related settings via existing registry APIs
- [x] Injects `ScopeResolver` (default `DefaultScopeResolver` or from config/binding hook if already present)
- [x] Applies rate limit, validation/output, transactions wrap, events, and tool surface profile config when present on config array
- [x] Clock remains `SystemClock` by default; tests may still inject `FixedClock` via constructor/withClock
- [x] Unit tests fail on pre-change bare factory (prove config is not ignored) then pass after wiring
- [x] Challenger: when `approval.store=database` and `idempotency.driver=memory`, registry gets matching mixed drivers without inventing a second store type

## Verification Steps

1. **test** `composer test:core -- --filter=ContainerBindings`
   - Expected: new/updated tests pass for makeRegistry wiring
2. **test** `composer test:core -- --filter=makeRegistry`
   - Expected: green if filter matches; otherwise covered by ContainerBindings suite
3. **test** `composer test:core`
   - Expected: full core suite green; coverage floor still met for touched code

## Integration

**Reachability:** `CapabilitiesServiceProvider::register` singleton `CapabilityRegistry::class` → `ContainerBindings::makeRegistry(self::configFromApp($app))` in `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php`.

**Data dependencies:** `config/capabilities.php` / `CapabilitiesConfig::defaults()` — surfaces, approval, idempotency, audit, rate_limits, validation, transactions, events, tool profiles.

**Service dependencies:** `CapabilityRegistry` with* injectors; `makeApprovalManager` / `makeIdempotencyStore`; `DefaultScopeResolver`; `SystemClock`.

## Outputs

- packages/laravel-capabilities/src/Boot/ContainerBindings.php — makeRegistry full wiring
- packages/laravel-capabilities/src/Registry/CapabilityRegistry.php — accessors for stores/scope/audit
- packages/laravel-capabilities/tests/Unit/Boot/ConfigDrivenBindingsTest.php — wiring unit tests
- packages/laravel-capabilities/tests/Unit/Boot/ContainerBindingsTest.php — smoke wiring tests
