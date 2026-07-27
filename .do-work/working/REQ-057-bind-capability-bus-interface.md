# REQ-057: Bind CapabilityBus interface

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.41848
**Claimed at:** 2026-07-27T10:49:35Z
**Heartbeat:** 2026-07-27T10:49:35Z
<!-- claimed-end -->

**UR:** UR-009
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:** HTTP `CapabilityController` constructor DI (`CapabilityBus`); product CLI `capabilities catalog` against package HTTP API
**Terminal state:** `CapabilityBus` resolves to the same singleton as `CapabilityRegistry` without host-side binding; catalog/invoke no longer throw `BindingResolutionException` for missing `CapabilityBus`
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** S
**Files:** packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/tests/Unit/Boot/ContainerBindingsTest.php, packages/laravel-capabilities/tests/Unit/Boot/ServiceProviderTest.php
**Depends on:**

## Task

Bind/alias `Rawphp\Capabilities\Contracts\CapabilityBus` to `CapabilityRegistry` in both `ContainerBindings::plan()` (source of truth for abstracts) and `CapabilitiesServiceProvider::register()`, and add unit tests that prove the interface is bound and reuses the registry singleton (no second registry instance).

## Context

GitHub issue #2 / MesoPrep: `CapabilityController` type-hints `CapabilityBus`, but the provider only registers `CapabilityRegistry` + string alias `'CapabilityRegistry'`. Laravel cannot resolve the controller → `BindingResolutionException`. Other contracts (`IdempotencyStore`, `Metrics`, `Tracer`, …) are interface-bound; `CapabilityBus` was missed.

Ideate: use `alias(CapabilityRegistry::class, CapabilityBus::class)` or make-delegation that reuses the existing singleton — do **not** construct a second registry (REQ-048 store parity). Fix plan and provider together to avoid drift. Unit-only proof via plan/abstracts + provider fake-app — not a live HTTP feature test.

Host AppServiceProvider workaround is temporary and out of package acceptance.

## Acceptance Criteria

- [ ] `ContainerBindings::plan()` / `abstracts()` include `CapabilityBus::class` mapped to `CapabilityRegistry::class` (or equivalent that makes `binds`/plan consumers see the interface)
- [ ] `CapabilitiesServiceProvider::register()` binds or aliases `CapabilityBus` so it resolves to the same instance as `CapabilityRegistry`
- [ ] Unit test: plan/`ArrayContainer` (or `bindingAbstracts`) asserts `CapabilityBus` is bound
- [ ] Unit test: provider register path resolves `CapabilityBus` and `CapabilityRegistry` to the same object (singleton identity)
- [ ] No host-side binding required for package-owned controller DI
- [ ] `composer test:core` green; package unit coverage stays ≥95%

## Verification Steps

1. **test** `composer test:core -- --filter=CapabilityBus`
   - Expected: new binding/identity tests pass
2. **test** `composer test:core -- --filter=ContainerBindings`
   - Expected: plan/bind suite green with CapabilityBus covered
3. **test** `composer test:core -- --filter=ServiceProvider`
   - Expected: provider suite green including bus alias
4. **test** `composer test:core`
   - Expected: full core package suite green

## Manual checks (advisory)

- [ ] After package bump in a consumer (e.g. MesoPrep), remove temporary `AppServiceProvider` `CapabilityBus` alias and run `capabilities catalog --json` — Observable outcome: catalog JSON succeeds without `BindingResolutionException` for `CapabilityBus`
