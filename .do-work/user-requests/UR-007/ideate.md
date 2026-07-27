# Ideate — UR-007

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **“Implemented in unit form” ≠ shippable release.** Brief assumes v0.1–v0.5 are done because inventory checkboxes are green; a Composer consumer still sees monorepo path packages with no Packagist versions, no CHANGELOG, and README status “future package design (not published).” Without an explicit stability contract (semver / 0.x caveats / public API surface list), apps cannot decide whether to depend.
- **Who is the consumer persona?** README install snippets (`composer require rawphp/laravel-capabilities`) speak to app developers; AGENTS.md speaks to REQ workers. Packaging, release notes, and a “first capability” tutorial only land if we pick one primary reader (app integrator) and treat monorepo/inventory as secondary developer docs.
- **Stability is multi-dimensional.** Consumers need: (1) package version numbers, (2) which APIs are public vs internal, (3) which roadmap items are “unit-covered design” vs “production-ready.” A single “stable” label would over-promise; a matrix (package × phase × readiness) matches the monorepo better.
- **D-020 is product surface for apps, not only package self-tests.** Spec D-020 says app CI should run schema snapshots for every capability before release. Thin helpers that return `true` after validating surface name strings do not give that consumer workflow even if inventory marks D-020 scenarios `[x]`.
- **Tutorial scope is foggy.** “First capability” could mean fluent `Capability::define`, `#[Capability]` attribute class, HTTP invoke only, or full multi-surface path. Without a chosen minimal path, docs REQs will thrash across packages.

## Challenger — Risks & Edge Cases

- **Honesty vs marketing tension.** Updating README/spec to claim “ready” while `assertParity()` still no-ops (presence-only) recreates the exact consumer confusion the brief names — docs ahead of DX code, not lagging. Docs REQs must state readiness honestly (e.g. unit-complete monorepo, pre-Packagist, D-020 helpers partial).
- **Roadmap rewrite can invent false shipped state.** Mapping inventory greens onto v0.1–v0.5 without naming residual gaps (live peer matrix in CI, Packagist publish, real multi-surface parity) misleads. Prefer “status vs roadmap” columns with explicit residuals over deleting the roadmap.
- **assertParity full D-020 shape conflicts with unit-only policy if mis-scoped.** Spec wants invoke across `http` / `registry` / `ai` adapter paths. True HTTP/feature paths are forbidden; correct design is registry + adapter unit paths with mocks/fakes (same choke point), not `tests/Feature`. Capture must keep that constraint explicit.
- **Snapshot file story is underspecified.** Current `assertSchemaSnapshot($name, ?$expected)` compares in-memory optional array; D-020 implies durable snapshot files under app (or package) tests. Without a file convention + update workflow, equality checks stay ad-hoc and drift-prone.
- **Packaging REQs without publish credentials.** “Packaging” might mean composer.json polish + version tags + branch alias only — not actual Packagist push. If a REQ assumes network publish, the run loop will block. Prefer monorepo packaging readiness + versioning docs over remote publish unless explicitly approved.
- **Thin helper tests already green will resist real behaviour.** Inventory and `HelperSurfaceTest` assert `method_exists` and empty-arg `true`. Strengthening D-020 will require intentional test rewrites (tests-as-SOT after intentional updates per UR-001 decisions) — not silent assert softening.

## Connector — Links & Reuse

- **D-020 lives on `CapabilityRegistry` + facade** (`assertParity`, `assertSchemaSnapshot`, `fake`, scope/tenant helpers). `assertCannotInvokeAcrossTenant` already has a fuller path when opts are passed; parity/schema are the thin spots. Extend those methods rather than a parallel Testing namespace unless façade DX demands it.
- **`CapabilityResult` assertion helpers** (`assertOk` / `assertFailed` / …) are real behavioural helpers — reuse their exception style for parity/snapshot failures (`CapabilityResultAssertionException` pattern).
- **Peer release-gate docs (UR-006)** already introduced “matrix + fixtures + release-gate docs” honesty for D-011. Mirror that pattern for product readiness (status table + residuals) instead of inventing a second docs philosophy.
- **Spec sections to update in place:** README status banner; `docs/spec.md` Roadmap table + D-020 helper docs (~L3107); AGENTS.md “Status: future package design” and indicative roadmap. No second competing bible.
- **No examples/ or package CHANGELOGs exist** — greenfield for tutorial + release notes, not a sync of stale copies.
- **Prior decisions:** unit-only + ≥95% coverage (UR-001); tests SOT after intentional updates; messaging/cli often out of scope when work is core-only — this brief likely spans **docs (all packages)** + **core D-020 helpers**; messaging/cli tutorial mentions may be optional unless “first capability” deliberately multi-surface.

## Summary

This UR is two complementary tracks: (1) make consumer-facing status/versioning/docs honest and usable (README, roadmap, CHANGELOG, first-capability tutorial) without overstating Packagist readiness; (2) thicken D-020 helpers so `assertParity` / schema snapshots enforce real cross-surface success/deny class and durable schema locks under the monorepo’s unit-only rule. The main trap is documenting “stable” while helpers still no-op — capture should couple docs honesty to helper behaviour and keep packaging as monorepo/versioning readiness, not live publish.
