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

#### Telegram recording notifier rename (`TelegramApprovalNotifier` → `RecordingTelegramApprovalNotifier`)

Public class under `Approval\Notifiers\` renamed so core does not present a production Bot API type. The rename remains real; a **deprecated dual-class soft-landing** keeps the old FQCN loadable.

- **Canonical (core):** `Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier` — in-memory recording double only; **no** Bot API / network in core.
- **Deprecated dual-class (soft-landing):** `Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier` — empty subclass of `RecordingTelegramApprovalNotifier`, marked `@deprecated`; still recording-only (not a network client). Prefer the canonical name for new code.
- **Production Telegram notifier:** messaging package `Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier` (different package/namespace) — unchanged; not renamed by this soft-landing.
- **Consumer impact:** update imports to `RecordingTelegramApprovalNotifier` for test/recording doubles; keep using the messaging package class for real channel delivery. Old core FQCN continues to autoload with deprecation guidance until a later removal.

### Fixed

#### MCP auto-register boot — soft-fail when nothing to register

`McpServerRegistrar::plan()` evaluates the `laravel/mcp` peer only when there are servers that would actually auto-register. Empty plans short-circuit before peer evaluation, so missing/incompatible peers no longer hard-fail app boot when there is nothing to mount.

- **Soft-fail (boot continues):** empty `surfaces.mcp.profiles` / `servers`, or `auto_register` false — hosts without a compatible `laravel/mcp` no longer throw on boot solely because the MCP surface is enabled.
- **Still fail closed:** non-empty planned servers with a missing/incompatible peer and `on_incompatible=fail` (default) still throw / register nothing — no half-register of MCP tools or servers.
- **Consumer impact (path/VCS installers):** apps that enable `surfaces.mcp` but leave profiles empty, or set `auto_register` false for manual mounts, can boot without installing `laravel/mcp`. Install a compatible peer only when you actually plan MCP servers to auto-register.
- Does **not** claim incomplete `path_prefix` HTTP MCP server auto-mount behaviour beyond the planned server rows returned by the registrar.

#### Docs — MCP auto-register residual (plan + host wire)

Documentation honesty (monorepo `docs/spec.md` + package user-guide alignment): `auto_register` / `McpServerRegistrar` are **plan + adapter register**, not a shipped live `laravel/mcp` HTTP mount under `path_prefix`.

- **What production boot does:** build a server plan from `profiles` / `servers`; may call `McpToolAdapter::register` so planned profile tools load on the adapter.
- **What production boot does not do:** push planned definitions into `laravel/mcp` (no production peer sink like HTTP `registerInto`). Hosts still **wire** peer MCP routes themselves (e.g. `Mcp::web` / peer docs) or use manual `Capability::mcpTools`.
- **Multi-profile residual:** sequential `adapter->register` overwrites adapter active profile/tools (**last profile wins**). Multi-server hosts should wire each peer server with its own tool set.
- **Consumer impact (path/VCS installers):** enabling MCP + `auto_register` does **not** yield zero hand-wiring — planned `path_prefix` paths are metadata until the host mounts routes. No new mount feature ships in this entry; narrative only (ORI-804 / ORI-803 package docs).
- **Not Packagist-published / not stable 1.x** — unchanged.

### Added

#### Host integration diagnostics + MCP fail policy (UR-062 / D-024)

Package seams for host product readiness (companion AI package owns queue/reaper/proposals/readiness defaults):

- **`php artisan capabilities:integration-health`** (`IntegrationHealthCommand` / `IntegrationHealthChecker` / `IntegrationHealthReport`) — Artisan product-readiness diagnostic. **Not** HTTP `GET …/capabilities/health` (catalog/surface peer health). AI-chat mode = `capabilities-ai.routes.enabled` **OR** non-empty `capabilities-ai.queue.name`. Fails closed on AlwaysReady when `proposals.enabled` is true; ops checks for array progress / empty queue when AI-chat via routes only.
- **MCP allowlist validation** (`McpProfileValidator`) — at register, profile capability names must exist and expose the MCP surface (profiles remain `name => list<string>` only).
- **`surfaces.mcp.on_register_error`** (`CAPABILITIES_MCP_ON_REGISTER_ERROR`, default **`throw`**) — non-empty plan + mid-mount adapter failure: rethrow (default) or soft-empty when `disable`. Empty plan soft-fail unchanged (ORI-801).
- Docs: integration-health vs HTTP health, MCP validation / `on_register_error` in package user guide + README.

#### MCP auto-register public surface (`McpServerRegistrar` / boot helpers)

Config-driven product MCP **server plan** + adapter registration for 0.x consumers (ORI-790):

- **`Adapters\Mcp\McpServerRegistrar`** — builds a plan from `surfaces.mcp` and may call `McpToolAdapter::register` for planned profiles (plan + adapter tools; not a peer HTTP mount).
- **`CapabilitiesServiceProvider::bootMcpServers` / `bootMcpServersWith`** — boot-time entry points for the same plan/register path (`bootMcpServersWith` preferred for unit isolation).
- **Config:** `surfaces.mcp.auto_register` (default true), `path_prefix` (default `/mcp`, plan metadata only), `servers` (plus existing `profiles` used by the plan).
- **Consumer impact (path/VCS installers):** hosts get new public types and config keys on upgrade. Production boot still does **not** mount live `laravel/mcp` HTTP servers under `path_prefix` — integrators host-wire peer routes (e.g. `Mcp::web` / peer docs) or use manual `Capability::mcpTools`. Distinct from **Fixed** *MCP auto-register boot — soft-fail when nothing to register* (empty plan / peer short-circuit).

- `Contracts\ApprovalGateway` — sibling-safe port (`find` / `accept` / `reject`). `ApprovalManager` implements it; container plan + service provider alias the same singleton (mirrors `CapabilityBus`).
- README **Public surface for sibling packages** — Contracts + public DTOs allowlist (`CapabilityResult`, `CapabilityContext`, `CapabilityData`).

### Changed

#### Internal extract — approval / pipeline collaborators

Phase-2/3 peeled focused collaborators out of larger types (additive public classes under PSR-4; not a removal of host APIs):

- `ApprovalExecutor` — execution path extracted from `ApprovalManager`
- `ApprovalResumer` — stuck-row resume / grace / lease extracted from `ApprovalManager` (public `resume()` / `artisanResume()` unchanged)
- `ApprovalExpiry` — pending TTL expire / lazy expiry on find extracted from `ApprovalManager` (public `expire()` / `expirePending()` / `find()` unchanged)
- `InvokeAuditStage` — audit stage extracted from `InvokePipeline` (constructor reshape is **Breaking** above; this bullet only names the extract)
- `InvokeResultFinalizer` — finish / wire / events extracted from `InvokePipeline` (public `finishEarly()` unchanged; no constructor reshape)
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
