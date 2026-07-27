---
ur: UR-009
received: 2026-07-27
status: captured
source: https://github.com/rawphp/laravel-capabilities-monorepo/issues/2
classification: bug-fix
layers_in_scope: []
layer_decisions: {}
reqs:
  - { id: REQ-057, layer: none, integration_confidence: n/a }
acknowledged_partials: []
open_gaps:
  - "Host workaround removal is not package acceptance — package binding is SOT"
  - "app(CapabilityController) wording is integration-y; monorepo needs unit plan/provider proof"
  - "Wrong singleton pattern could split registry store identity (REQ-048 parity)"
  - "Plan-only or provider-only fix leaves ContainerBindings vs provider drift"
  - "String alias alone does not satisfy interface constructor injection"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | bug-fix |
| Layers in scope | (none — bug-fix) |
| Layer decisions | (none — all covered) |
| REQs generated | 1 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-057 | none | n/a |
<!-- capture-summary-end -->

# UR-009: User Request

## Request

https://github.com/rawphp/laravel-capabilities-monorepo/issues/2

# CapabilityBus interface not bound — HTTP surface fails DI (catalog/CLI)

## Summary

`CapabilityController` type-hints `Rawphp\Capabilities\Contracts\CapabilityBus`, but `CapabilitiesServiceProvider` only registers the concrete `CapabilityRegistry` (which implements that interface). It never binds/aliases the interface.

Laravel cannot resolve the controller for `/capabilities/*`, so the product CLI (`capabilities catalog`, invoke, etc.) fails before any capability logic runs.

## Repro

Consumer: Laravel app with `rawphp/laravel-capabilities` (`dev-main`, HTTP surface enabled) + Go CLI against that app.

```bash
capabilities catalog --json
```

### Observed

```json
{
  "message": "Target [Rawphp\\Capabilities\\Contracts\\CapabilityBus] is not instantiable while building [Rawphp\\Capabilities\\Adapters\\Http\\CapabilityController].",
  "exception": "Illuminate\\Contracts\\Container\\BindingResolutionException",
  "file": ".../Illuminate/Container/Container.php",
  "line": 1416
}
```

### Container state (consumer app)

| Abstract | Bound? | Resolves? |
|---|---|---|
| `CapabilityRegistry` | yes | ok |
| `CapabilityBus` | **no** | same exception |

## Root cause

Package owns both ends of the wire:

1. **Controller** injects the interface:

```php
// packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php
public function __construct(
    private readonly CapabilityBus $registry,
    ...
)
```

2. **Service provider** binds only the concrete + string alias:

```php
// packages/laravel-capabilities/src/CapabilitiesServiceProvider.php
$this->app->singleton(CapabilityRegistry::class, function ($app) { ... });
$this->app->alias(CapabilityRegistry::class, 'CapabilityRegistry');
// missing: CapabilityBus::class → CapabilityRegistry
```

3. **`ContainerBindings::plan()`** also lists `CapabilityRegistry` only — not `CapabilityBus` — while other contracts (`Metrics`, `Tracer`, `IdempotencyStore`, …) are interface-bound.

Other abstracts get interface bindings; `CapabilityBus` was missed. Host apps should not paper over package-owned controller DI.

## Expected fix (package)

In `CapabilitiesServiceProvider::register()`, after the registry singleton (and ideally in `ContainerBindings` plan):

```php
$this->app->alias(CapabilityRegistry::class, CapabilityBus::class);
// or
$this->app->singleton(CapabilityBus::class, fn ($app) => $app->make(CapabilityRegistry::class));
```

Also:

- Add `CapabilityBus::class => CapabilityRegistry::class` (or equivalent) to `ContainerBindings` plan/abstracts if that is the source of truth for DI.
- Integration/unit test: with HTTP surface enabled, `app(CapabilityController::class)` (or `app(CapabilityBus::class)`) resolves without a host-side binding.

## Workaround (consumer, temporary)

```php
// AppServiceProvider::register()
$this->app->alias(
    \Rawphp\Capabilities\Registry\CapabilityRegistry::class,
    \Rawphp\Capabilities\Contracts\CapabilityBus::class,
);
```

Remove once this ships and the consumer bumps the package.

## Scope

- Package: `packages/laravel-capabilities`
- Surface: HTTP (`CapabilityController`) — blocks product CLI which is a client of that surface
- Not a CLI binary / PATH issue

## Acceptance criteria

- [ ] `CapabilityBus` is bound/aliased to `CapabilityRegistry` by the package provider
- [ ] `app(CapabilityBus::class)` resolves without host code
- [ ] HTTP list/catalog path no longer throws `BindingResolutionException` for missing `CapabilityBus`
- [ ] Test coverage asserts the binding (boot/DI or HTTP surface test)

## Context

Found while integrating MesoPrep (`rawphp/laravel-capabilities` + `capabilities` CLI catalog). CLI install path is separate work; this is pure package DI.
