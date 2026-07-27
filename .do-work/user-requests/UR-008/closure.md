---
ur: UR-008
closed_at: 2026-07-27T07:02:00Z
branch: main
path_units: 3
verdict_summary:
  closed: 0
  not-reached: 0
  terminal-mismatch: 0
  degraded:evidence-by-test: 3
  degraded:human-confirmed: 0
overall: closed
---

# Closure report — UR-008

Library monorepo path-units (container/registry/persistence/docs). Walked on merged `main` via targeted unit suite and docs probes (no full Laravel host app boot).

## REQ-046 — degraded:evidence-by-test

- req: REQ-046
- entry_point: "Laravel container resolves `CapabilityRegistry` (or `Capability::invoke` / facade) after package boot with published or default `config/capabilities.php`"
- terminal_state: "The shared registry singleton applies surface/governance config and uses the same approval store, idempotency store, audit settings, and scope resolver as the configured container bindings — unit tests prove inject parity and no dual-manager drift"
- walk_kind: library
- action_taken: "composer test:core -- --filter=RegistryFactory; inspect ContainerBindings::makeRegistry + CapabilitiesServiceProvider store inject order on main"
- observed_state: "RegistryFactoryPathTest 4 passed (41 assertions): dual independent construction diverges; container-resolved registry shares stores and applies config domains; database drivers share gateway-backed stores; prebuilt store inject closes dual-manager divergence. Code on main: withApprovalStore/withIdempotencyStore/withScopeResolver/withAuditConfig + SP makeRegistry from shared stores."
- verdict: degraded:evidence-by-test
- evidence_ref: "closure-evidence/req-046.txt; closure-evidence/merged-code-smoke.txt; tests Unit/Boot/RegistryFactoryPathTest"

## REQ-049 — degraded:evidence-by-test

- req: REQ-049
- entry_point: "Host enables `approval.store=database` and/or `idempotency.driver=database`, publishes/runs package migrations"
- terminal_state: "Package provides a first-party Illuminate query/DB TableGateway wired into database drivers by default (ArrayTableGateway remains unit-test default); docs document optional 10-line host override — unit tests prove gateway contract without a live MySQL/Postgres"
- walk_kind: library
- action_taken: "composer test:core filters DurableGatewayPath|QueryTableGateway|ContainerBindings|ProductionPersistencePath; rg docs for QueryTableGateway + host binding"
- observed_state: "46 related tests passed (170 assertions) including QueryTableGateway full contract, ProductionPersistencePathTest database+connection → QueryTableGateway (not silent Array), fail-closed null connection. QueryTableGateway.php present; makeDatabaseTableGateway used by approval/idempotency factories. Tutorial/core README document host TableGateway override."
- verdict: degraded:evidence-by-test
- evidence_ref: "closure-evidence/req-049.txt; packages/laravel-capabilities/src/Persistence/QueryTableGateway.php; docs/tutorials/first-capability.md"

## REQ-053 — degraded:evidence-by-test

- req: REQ-053
- entry_point: "Maintainer prepares monorepo packages for a pre-stable 0.x consumer install (tag + Packagist residual)"
- terminal_state: "Versioning metadata, changelogs, and publish checklist make a tagged 0.x + Packagist submission actionable; actual Packagist account publish and public tag push remain human-gated advisory steps"
- walk_kind: library
- action_taken: "test -f docs/versioning.md; rg Packagist|0.x|tag|checklist|Consumer readiness on docs/versioning.md + README.md"
- observed_state: "versioning.md present with branch-alias 0.x-dev policy, monorepo tag pattern v0.Y.Z, Packagist + git tag human checklist, residual until human. README Consumer readiness table: makeRegistry done, durable TableGateway done, release prep done, Packaging/Packagist residual. No claim packages are published."
- verdict: degraded:evidence-by-test
- evidence_ref: "closure-evidence/req-053.txt; docs/versioning.md#packagist--git-tag-publish-checklist-human-steps; README.md Consumer readiness"
