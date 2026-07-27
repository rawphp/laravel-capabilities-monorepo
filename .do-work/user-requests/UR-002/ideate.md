# Ideate — UR-002

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **Host-app install is the real success criterion, not green unit tables.** The brief lists provider gaps (routes, discovery, config-built registry, Artisan registration). A consuming Laravel app that `composer require`s the package today gets config merge + a few singletons but no HTTP tree, no `app/Capabilities` scan, and empty console helpers — so every surface the package claims to own still needs hand-wiring. That breaks the “install package → surfaces appear under law” promise in AGENTS.md / spec package layout.
- **UR-001 deliberately left glue as pure tables.** REQ-014 closed Boot/Config/Events/Observability with `RouteTable`, `ArtisanCommandTable`, `SurfaceRegistrar`, and `registrationPlan()` — unit-testable artifacts that host apps “map onto Laravel at boot.” This brief is not re-implementing domain; it is the missing *application of those tables* inside `CapabilitiesServiceProvider::boot()`. Capture must treat UR-001 tables as inputs, not rewrites.
- **Default bindings are demo-grade, not production-grade.** Provider binds `InMemoryIdempotencyStore`, empty `CapabilityRegistry`, and `ApprovalManager::inMemory()`. Full `config/capabilities.php` (surfaces, audit mode, approval drivers, clients) is never used to construct the bus. Apps that enable HTTP without swapping stores will silently use memory stores that vanish on deploy — high-stakes for idempotency and approvals.
- **Stakeholders beyond the package author:** host-app developers (zero-config bootstrap), operators (Artisan ops commands), CLI clients (HTTP routes must exist under the single API prefix), CI/unit-test policy owners (must stay unit-only / no feature suite), and messaging package (must not grow core Telegram routes).

## Challenger — Risks & Edge Cases

- **Unit-only policy vs real Illuminate registration.** Loading routes, `$this->commands()`, and path discovery can be done without feature/DB tests if the provider methods remain thin wrappers over pure tables and discovery is testable with temp dirs / fakes. Risk: workers reach for `tests/Feature` or Orchestra boot+DB. Constraint (AGENTS.md): unit tests + mocks/fakes only; ≥95% coverage.
- **Double-registration / boot-order races.** Auto-discover + fluent `Capability::define` + attribute classes must stay single-map (D-017). If boot both discovers and something else re-registers, duplicate-name boot exceptions or silent overwrites appear. Registry construction from config must run once and fail closed on duplicates.
- **Route lifecycle without a “routes.php” file.** Spec/D-009 wants one HTTP tree; code has `RouteTable` but no `loadRoutesFrom`. Options: (a) ship `routes/capabilities.php` that loops `RouteTable::routes()`, or (b) register routes in provider from the table. (b) avoids a second source of truth if the file only mirrors the table; (a) is more conventional Laravel. Either way, disabled `surfaces.http` must register zero routes (SURF-003).
- **Artisan “real commands” vs product CLI.** Brief says register real Artisan commands beyond pure tables. Spec: Artisan is optional *in-server* ops, not product CLI. Commands must call the registry (same law), require SystemActor/user correctly, and never invent a second invoke path. Signatures should come from `ArtisanCommandTable`, not hand-copied.
- **In-memory defaults in production.** Constructing the registry from full config still needs store driver selection (memory vs DB). Shipping “full config construction” while leaving memory defaults without loud docs or env-gated warnings risks production data loss on approval/idempotency. Capture should decide: config-driven driver binding with memory as explicit testing default, not silent prod default.
- **Auto-discover path assumptions.** Default `app/Capabilities` may not exist, may use a custom namespace, or monorepo packages may live outside `app/`. DiscoveryPaths / config must allow override; missing directory must no-op, not throw.

## Connector — Links & Reuse

- **Reuse, don’t reimplement:** `RouteTable`, `ArtisanCommandTable`, `SurfaceRegistrar`, `RegistrationPlan`, `BootGuard`, `AttributeDiscoverer` / `DiscoveryPaths`, `ContainerBindings`, `CapabilitiesConfig` — all already under `packages/laravel-capabilities/src/`. Glue should call these from `CapabilitiesServiceProvider::boot()` / `register()`.
- **Prior work:** UR-001 REQ-011 (HTTP API), REQ-012 (job/artisan surfaces), REQ-014 (boot/config), REQ-004 (discovery/schema), REQ-015 (architecture parity). This UR is the integration gap after pure domain closed green.
- **Standing decisions (decisions.md):** unit-only + ≥95% coverage; tests SOT after intentional updates; docs/spec.md conflict oracle. Any glue design that “needs feature tests” must be redesigned for injectability.
- **Config publish already exists** (`capabilities-config`, migrations tags) — extend registration, don’t redo publishes.
- **Messaging stays sibling** — core provider must not `loadRoutesFrom` Telegram webhooks (D-007).

## Summary

UR-001 built the pure registration tables and domain bus; the provider never wires them into Laravel’s real lifecycle. Decompose as thin glue on top of existing tables: HTTP route registration from `RouteTable`, discovery into the registry, config-driven container construction (surfaces/audit/approval/clients + store drivers), and Artisan command registration from `ArtisanCommandTable` — all unit-tested without a feature suite. Keep Artisan ops separate from the product CLI and fail closed when surfaces or peers are disabled.
