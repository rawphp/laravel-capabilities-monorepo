# Ideate — UR-009

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **Assumption: host workaround can go away on package bump alone.** Brief frames consumer AppServiceProvider alias as temporary. If MesoPrep (or other hosts) keep the alias after the package ships, double-alias is usually harmless in Laravel, but a host that rebound `CapabilityBus` to a different concrete would then mask package intent — capture should treat package ownership as SOT and not require host cleanup as acceptance.
- **Foggy: “resolves without host code” test surface.** Monorepo policy is unit-only / no full Laravel app boot for package truth. Brief’s `app(CapabilityController::class)` wording sounds integration-y; the real gate is plan/provider unit coverage (`ContainerBindings::plan`, provider register path, or ArrayContainer) plus asserting interface abstract is bound — not a live HTTP catalog call.
- **Stakeholders beyond CLI:** any surface that type-hints `CapabilityBus` (future adapters, host jobs, meta tools) hits the same DI hole; fix is one bus binding, not HTTP-only.

## Challenger — Risks & Edge Cases

- **Double binding / two registry instances.** Alias vs `singleton(CapabilityBus, fn => make(CapabilityRegistry))` both work if done carefully; wrong pattern (`singleton` that constructs a second registry) would split ApprovalManager/idempotency store identity (contradicts REQ-048 singleton parity). Prefer `alias(CapabilityRegistry::class, CapabilityBus::class)` or a make-delegation that reuses the same singleton.
- **Plan vs provider drift.** Issue notes `ContainerBindings::plan()` lists only `CapabilityRegistry` while Metrics/Tracer/IdempotencyStore are interface-keyed. Fixing only the provider leaves plan/abstracts tests green but consumers of `ArrayContainer::fromPlan()` / `bindingAbstracts()` still missing `CapabilityBus` — both ends must move.
- **String alias only is not enough.** Existing `'CapabilityRegistry'` string alias does not help constructor injection of the interface FQCN; Laravel resolves type-hints by class name, not string alias.

## Connector — Links & Reuse

- **Reuse pattern already in package:** `IdempotencyStore`, `Metrics`, `Tracer`, `ScopeResolver` are bound by interface in `ContainerBindings::plan()` and provider — mirror that for `CapabilityBus` → `CapabilityRegistry` (registry is the sole implementor today).
- **Tests live next to BOOT-001:** `ContainerBindingsTest` already has `happy: container binds CapabilityRegistry`; `ServiceProviderTest` / `ConfigDrivenBindingsTest` assert plan abstracts — extend those rather than inventing a Feature suite.
- **Standing decisions:** unit-only + ≥95% coverage (UR-001); core layer only for package DI (messaging/cli out of scope for pure core bindings, matching prior URs).

## Summary

This is a one-line (plus plan) package DI miss: controller injects `CapabilityBus`, provider binds only `CapabilityRegistry`. Decompose as a small core path-unit — bind/alias in plan + provider, unit-test resolution/singleton identity, no messaging/CLI package work. Watch singleton parity so accept/invoke still share one registry instance.
