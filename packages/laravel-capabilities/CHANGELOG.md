# Changelog

All notable changes to `rawphp/laravel-capabilities` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Breaking (0.x behavior change)

#### JsonSchemaValidator — empty object / `[]`-as-object `required` enforcement

Empty PHP arrays that represent JSON `{}` (and empty list-shaped `[]` when the schema is an **object** or has `properties`) now run `required` and `additionalProperties` checks that previously skipped those payloads (`JsonSchemaValidator` `$asObject` path).

- **Scope:** **object** schemas only (`type: object` or schemas that declare `properties`). Does **not** claim that array-typed empty lists fail for being lists.
- **Consumer impact:** hosts/wire callers that sent empty objects (`{}` / PHP `[]`) for object schemas with required fields can start getting validation failures on the same payload that previously passed.

#### InvokePipeline — audit constructor / public field reshape (`InvokeAuditStage`)

`InvokePipeline` now requires a typed `InvokeAuditStage $auditStage` constructor argument. Pipeline-level audit configuration no longer lives as public props/params on `InvokePipeline`.

- **Required:** `InvokeAuditStage $auditStage` (audit write + mode/driver/outbox/failure policy live on the stage).
- **Removed from `InvokePipeline` (constructor kwargs and public props):** `auditWriter`, `auditMode`, `auditEnabled`, `auditRequired`, `auditDriver`, `auditOutbox`, `throwOnAuditFailure`.
- **Consumer guidance:** Prefer `CapabilityRegistry` / facade audit APIs (`withAuditWriter`, `withAuditConfig`, `throwOnAuditFailure`, `auditMode()`, …). Do **not** construct `InvokePipeline` with legacy audit kwargs or read `$pipeline->auditWriter` (etc.). In-repo only the registry builds the pipeline; sibling packages do not.
- **Distinct from** the JsonSchema empty-object required-enforcement break above.

### Changed

#### Internal extract — `ApprovalExecutor`, `InvokeAuditStage`, `RegistryAssertions`

Phase-2 peeled focused collaborators out of larger types (additive public classes under PSR-4; not a removal of host APIs):

- `ApprovalExecutor` — execution path extracted from `ApprovalManager`
- `InvokeAuditStage` — audit stage extracted from `InvokePipeline` (constructor reshape is **Breaking** above; this bullet only names the extract)
- `RegistryAssertions` — assertion helpers extracted from `CapabilityRegistry`

**Supported host surface is unchanged:** keep using `CapabilityRegistry`, `ApprovalManager`, and the `Capability` facade. New classes are additive for package internals / advanced wiring; they do not require host migration if you already use the registry/manager/facade path.

### Added

- Core product capability bus for Laravel apps: single registry choke point and invoke pipeline
  (validate → hydrate → actor → scope → idempotency → authorize → approval → rate limit → run → output → audit).
- Package-native DTOs / JSON Schema surface for catalog and wire edges.
- Surface adapters as thin entry points: agent (`laravel/ai`), MCP (`laravel/mcp`), HTTP capability API,
  product CLI (`caller: cli` via same HTTP API), jobs, optional Artisan ops — domain stays in app `run()`.
- Governance built into every invoke: authorization, optional approval state machine, audit modes,
  actor derivation, tenant/scope re-resolution, mutating idempotency keys.
- Conversation **contracts** only in core (messaging Bot API lives in the sibling messaging package).
- Unit-test contract scaffold aligned with monorepo `docs/spec.md` / requirements inventory (≥95% coverage target).
- **Laravel 13 / illuminate 13 support** — all `illuminate/*` requirements allow `^11.0|^12.0|^13.0`
  (PHP remains `^8.2`; Laravel 13 apps still need PHP `^8.3` per framework).
- **Additive helpers (non-breaking)** on invoke results — existing callers are unaffected:
  - `CapabilityResult::isRetryable()` — non-ok retry policy from wire `retryable` or `ErrorCodeMap` default; success is never retryable
  - `CapabilityResult::isHardRefuse()` — terminal auth/profile/runnability refuse via `ErrorCodeMap`
  - `ErrorCodeMap::isHardRefuse(string $code)` — hard refuse code set (`forbidden`, `capability_not_in_profile`, `not_runnable`, `unauthenticated`)

### Notes

- **Not published on Packagist.** Install from package-repo VCS or monorepo path.
- Public Composer package name is `rawphp/laravel-capabilities`; tags and stable `1.x`
  are not claimed until a deliberate release process lands.
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities` on push.

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0 (mirrored to this package remote), promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix.
-->

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
Consumers should pin a VCS ref or path checkout and read this changelog before upgrading.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/laravel-capabilities
[0.x]: https://github.com/rawphp/laravel-capabilities
