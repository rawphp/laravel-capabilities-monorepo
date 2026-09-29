# Changelog

All notable changes to `rawphp/laravel-capabilities` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Added

- **Approval is declared on the capability (D-006, L-002).** Fluent definitions gain
  `->needsApproval(fn (Input $input, CapabilityContext $ctx): bool)`; class capabilities
  use their `needsApproval()` method. Before, approval could only be triggered by the
  internal invoke options `needs_approval` / `needs_approval_callback` / `require_approval`,
  which no adapter sets — so the whole approval state machine was unreachable from a real
  surface. The row now also stores the capability's `approvalPolicy` (`approval_policy`,
  new nullable column via migration
  `2026_09_29_000001_add_approval_policy_to_capabilities_approvals_table`) and its
  `approvalTtlHours` is applied to `expires_at`. Custom `ApprovalStore` / `TableGateway`
  implementations must persist the new key.
- **Approval executor identity columns** — `capabilities_approvals` gains nullable
  `executor_actor_type` / `executor_actor_id` (new migration
  `2026_09_24_000001_add_executor_actor_to_capabilities_approvals_table`).
  `ApprovalExecutor::execute()` writes them in the same conditional update as
  `result_status` on every terminal path (ok, domain failure, stale, original actor
  forbidden): the deciding user on accept, the `SystemActor` on resume (D-002).
  Custom `ApprovalStore` / `TableGateway` implementations must persist the two new keys.
- `GET /{prefix}/health` now reports `api_version` (`RouteTable::API_VERSION`, currently `1`).
  The product CLI checks it before `run` and refuses a server that speaks another version.
  Bump it on any breaking change to route shapes or the invoke/error envelopes.
- **`capabilities:integration-health` pings the AI progress store:** in AI-chat mode a new `ai_progress_ready` row resolves `Rawphp\CapabilitiesAi\Contracts\ProgressStoreReadiness` by class-string and fails when the store is unreachable (or cannot be resolved, e.g. `progress.driver=redis` with no Redis client); skips when the AI package does not bind it. `IntegrationHealthChecker::check()` takes an optional sixth `$progressStoreReady` callable.
- **`capabilities:integration-health` warns on silent audit loss (D-010).** New
  `audit_writer` check: when `audit.enabled` and any invoke surface is on but the live
  registry has no `AuditWriter`, it reports `warn` (every audit record is otherwise a
  silent no-op). It probes `CapabilityRegistry::audit()`, not a container binding — the
  service provider does not inject a bound `AuditWriter`; wire one with
  `CapabilityRegistry::withAuditWriter(...)`. Warn only; exit code unchanged.
  `IntegrationHealthChecker::check()` takes an optional seventh `$auditWriterWired` probe.

#### Audit entry `tool_profile` (D-008 / D-010)

Every audit entry now carries `tool_profile`: the tool profile the surface gated the invoke under.
`runCapabilityInProfile()` stamps the **enforced** profile (overwriting any caller-supplied
`tool_profile` option), so agent and MCP adapter invokes record it; sibling surfaces that gate
tools themselves (messaging) pass it as an invoke option. `null` for invokes outside a profile
(HTTP, CLI, job).

### Changed (BREAKING)

#### Invokes without an actor are refused on every surface (D-002, L-004)

`ResolveActor` used to hand any non-job invoke that omitted `options['actor']` a
fabricated user (`stdClass`, `id = 1`, `name = default-user`). That principal drove
`authorize()`, rate-limit keys, idempotency identity and audit — usually as the id of the
first (admin) user. The fallback is gone: a missing actor now fails closed with
`unauthenticated` before `run()`, exactly as jobs and explicit `null` already did. Pass
`'actor' => $request->user()` (or a `SystemActor` / `CapabilityContext`) on every in-process
`invoke()`; adapters already do. `ResolveActor::defaultUser()` is removed — build your own
test principal.

#### Accepted approvals now run the capability

Before this change, an `ApprovalManager` with no executor bound — which included the
container singleton behind the HTTP approve route and the messaging `ApprovalGateway`,
and `CapabilityRegistry::approvals()` — marked accepted or resumed approvals `executed`
with a fabricated `{"executed": true}` result. The capability never ran and its output
contract was never checked.

- **Default executor** — `CapabilityRegistry::executeApproval($row)` re-invokes the
  stored capability through the registry pipeline as the original requester (user id +
  tenant, or the same `SystemActor`) and caller: re-validate, re-scope, authorize, run
  once, output contract. The container `ApprovalManager` and `registry->approvals()`
  use it by default.
- **Fail closed** — a manager with no executor now records `executed` + `failed` with
  `not_configured` instead of reporting success.
- **Runs as the real requester (L-005)** — the container registry resolves the original
  requester through the default auth guard's user provider (the same lookup as the accept
  re-check) via `CapabilityRegistry::withRequesterResolver(fn (string $type, string $id): ?object)`,
  so `authorize()` / `run()` receive the host's user model (`$actor->can(...)` works). An
  unresolvable requester fails closed: `forbidden`, row `executed` + `failed`, `run()` not
  called. `SystemActor` requesters never touch the resolver. Registries built without a
  resolver (unit tests, manual `ContainerBindings::makeRegistry`) still rebuild a plain
  principal (`id`, `tenant_id`); wire a resolver for real user models.

#### MCP integration clients are bound to configured profiles (D-023)

An `integration` MCP principal could previously run inside any profile the host
passed. It now runs only inside profiles listed for its `client_id` under
`surfaces.mcp.auth.integration_profiles` (`client_id => list<profile>`). Any other
profile — or no profile — returns `forbidden` with `normalized_code`
`integration_profile_forbidden`, before the registry is invoked. User principals
(`user_pat`, `user_delegated`) are unchanged.

**Upgrade:** add an `integration_profiles` entry for every client in
`integration_actors`, or its tool calls will be refused.

### Changed

- **`QueryTableGateway` writes SQL every supported engine accepts (D-006, L-012).** Scalars
  bound to JSON columns (the approval row's `scope` tenant id, for example) are now
  json-encoded like arrays — MySQL and PostgreSQL reject a bare string in a JSON column,
  SQLite silently accepted it. Timestamps are written as `Y-m-d H:i:s` in the PHP default
  timezone and read back as DATE_ATOM (MariaDB rejects an offset suffix); the lease-claim
  predicate compares `IS NULL` / `<=` only and no longer tests a timestamp column against
  `''`. New optional constructor argument `timestampColumns` (defaults to
  `DEFAULT_TIMESTAMP_COLUMNS`). Rows written by earlier versions still decode.
- **Approval accept/reject honour the execution lease and never recurse (D-006, L-013).** A
  pending row whose `execution_lease_until` is still live is a Shape B (`approval.execution =
  atomic`) run in flight: `accept()` and `reject()` now return `conflict` with
  `in_progress: true` instead of `accept()` re-entering itself until the lease expired (a hot
  loop) and `reject()` flipping an executing row to `rejected` behind the runner's back.
  `reject()` uses the lease-aware conditional update; a lost race is settled from one re-read
  (terminal status → that outcome). `ApprovalStore::claimLease()` attributes need not carry a
  new lease.
- **`approval.connection` / `idempotency.connection` now reach the stores (L-008).** The
  service provider resolved the container's `ConnectionInterface` first; Laravel aliases that
  to the default `db.connection`, so a configured store connection name was never used and
  approvals/idempotency silently wrote to the default connection. The configured name is now
  resolved through the `db` manager first; a name that cannot be resolved fails closed at
  boot (`BootException`) instead of falling back to the default. The undocumented
  `capabilities.database.connection` / `capabilities.connection` fallbacks are gone.
- **Pipeline `rate_limited` sends a backoff hint (D-013, C-007).** The envelope now carries
  `error.retry_after` (seconds until the tripped per-minute / per-capability window frees)
  and `HttpResponse::fromResult` adds `Retry-After` on 429 when it is present (an explicit
  header passed by the caller wins). The zero-limit edge and the agent turn budget send none.
  **Contract change:** `Contracts\RateLimiter` gains `availableIn(string $key): int`;
  `InMemoryRateLimiter` and `LaravelCacheRateLimiter` implement it — host implementations
  must add it (return `0` when unknown).
- **`transactions.wrap_run = true` now really wraps `run()` (D-010, L-010).** The flag only
  set a test-visible marker; `run()` was called exactly as with the flag off, so an app that
  opted in for atomicity got none. The pipeline now executes `run()` inside
  `ConnectionInterface::transaction()` on the connection the container / `makeRegistry`
  hands it (`CapabilityRegistry::withTransactionConnection()`); a domain throw rolls back and
  keeps its `domain_error` mapping. `wrap_run` on with no connection fails closed at invoke
  with `not_configured` (run never called). The wrap covers `run()` only — output validation,
  idempotency storage and the audit record still happen after commit, so strict audit
  failure cannot un-commit a wrapped run (see D-010 audit modes).
- **`idempotency.*` config now reaches the guard (D-005, L-011).** `enabled`, `ttl_hours`,
  `header` and `warn_missing_key` were published but never applied: the pipeline always used
  `IdempotencyConfig::defaults()`, and the HTTP controller read an undocumented
  `surfaces.http.idempotency_header`. `ContainerBindings::makeRegistry` (and the container
  registry) now apply `config('capabilities.idempotency')`; `CapabilityRegistry` gains
  `withIdempotencyConfig(array|IdempotencyConfig)`, `idempotencyConfig()` and
  `idempotencyWarnings()`, and a constructor `idempotencyConfig` array. `withIdempotencyStore()`
  / `withClock()` keep the configured TTL. `enabled: false` makes the guard inert (no lookup,
  no store, no key policy). The container `CapabilityController` reads the header name from
  `idempotency.header`; an explicit `surfaces.http.idempotency_header` still wins.
- **Uncaught pipeline throwables are reported, hidden, and release the idempotency key (L-009).**
  An exception escaping a non-run stage (an `authorize()` callable, a store, the rate
  limiter, output validation, strict audit) returned `internal` with the raw
  `$e->getMessage()` on the wire — SQL text and bindings included — without reporting it,
  and skipped the failure finish, so a key claimed at idempotency lookup stayed
  `processing` (every retry answered `busy`) until its TTL. The catch now reports through
  the bound `ExceptionHandler`, answers `internal` / `Internal error.` (same as run-stage
  bugs), and runs the normal failure finish: the key is stored `failed` and a retry replays
  that failure. If the finish itself throws, that is reported too and a bare `internal`
  envelope is returned.
- **Accept / reject / forced resume enforce the capability's `approvalPolicy` (D-006, L-002).**
  `ApprovalManager` applied only its global `approval.default_policy`
  (`requester_or_role`), so a capability declaring `approvalPolicy: 'role:finance'` still let
  the requester self-approve. Decisions now use `ApprovalPolicy::forRow($row)`: the row's
  stored policy when present (host role / staff / custom checkers are kept), otherwise the
  global default. Rows written before this release have no stored policy and behave as before.
  The approved execution passes `executing_approval_id` to the pipeline so the needs-approval
  gate does not re-request approval for an already-decided row.
- **Class capabilities run their own `authorize()` / `needsApproval()` (D-017, L-001).**
  For `#[Capability]` classes the pipeline previously called only `run()`: the class's
  `authorize()` was never consulted (the invoke fell through to the host `Authorizer`,
  deny by default) and `needsApproval()` was never read. Now the handler is resolved
  **once per invoke through the container** (constructor dependencies inject; was `new`),
  and its `authorize()` is the authorize-stage decision (also on approval accept re-check),
  its `needsApproval()` gates the approval stage, and the same instance runs. A class
  without `authorize()` still goes through the host `Authorizer`. Fluent `->authorize()`
  callables are unchanged. `CapabilityRegistry::withHandlerFactory(callable)` swaps the
  construction path (unit tests).
- **Discovery fails closed on half-written capability classes (D-017).** A class carrying
  `#[Capability]` that does not implement `DefinesCapability` now throws `BootException`
  during discovery instead of being silently dropped from the catalog. Add
  `implements DefinesCapability` or remove the attribute.
- **Agent / MCP `handle()` refuse to run outside a profile (D-008, L-017).** With no
  registered profile and no `options['profile']` the adapters used to fall back to a bare
  `registry->invoke()` — a model could name any capability outside its tool list and, subject
  only to `authorize()`, run it. With `surfaces.<agent|mcp>.require_profile` (default `true`,
  new constructor argument `requireProfile` on `AiToolAdapterV1` / `McpToolAdapterV1`) such
  a call now returns `not_runnable` (`normalized_code: profile_required`) before the registry,
  matching the tool-list rule. `require_profile: false` keeps the old fallback. The service
  provider now also binds an `AiToolAdapter` singleton from `surfaces.agent.*` beside the MCP one.
- **MCP handle requires a profile after multi-profile register (D-008).** Once
  `McpToolAdapterV1` has registered more than one distinct profile, `handle()` /
  `handleStructured()` without `options['profile']` return `not_runnable`
  (`normalized_code: profile_required`, plus `registered_profiles`) instead of silently
  running under the last-registered profile. Single-profile hosts are unchanged.
- **Failed audit outbox rows are retried (D-010).** `WriteAuditJob::handle()` now calls
  `AuditOutbox::requeueFailed($maxAttempts)` before draining, so a `failed` row goes back
  to `pending` until it has used `maxAttempts` (new constructor argument, default `3`).
  Before, one failed write left the row `failed` forever. Rows at the cap stay `failed`.

### Fixed

- **Agent turn budget (D-013) is no longer agent-caller only:** the pipeline enforces `rate_limits.agent_turn.max_tool_calls` whenever an in-process adapter supplies `agent_turn_tool_calls`, whatever the caller. AI turns (`caller=job` from `rawphp/laravel-capabilities-ai`) are now capped. The option is never read from HTTP or tool input, and it can only deny.

### Fixed (HTTP surface)

- **Sanctum CLI tokens now run as `caller: cli` (D-022).** `IlluminateHttpBridge` added
  `adapter: http` to every authenticated credential, which shadowed
  `clients.token_abilities` / `clients.oauth` in `CallerDeriver`, so a
  `capabilities:cli` token always ran as `http`. The bridge now sets `adapter: http`
  only for a session user with no token abilities or OAuth client. CLI tokens are now
  subject to `surfaces.cli.enabled` and per-capability `surfaces` (a CLI token against an
  `http`-only capability gets `forbidden`). Unmapped abilities still derive `http`.
- **Auth issuance routes are throttled by default.** `POST auth/token`, `POST auth/device`
  and `GET auth/callback` accept no credentials, and Laravel 11+ `api` has no throttle, so
  they took unlimited attempts. They now get `throttle:6,1,capabilities-auth` (6 per
  minute per client IP) after `auth:*` is stripped. New `surfaces.http.auth_middleware`
  (default `null`) replaces that whole stack when set, e.g. for device-code polling.
- **HTTP describe honours the caller's surfaces (D-008).** `GET /{prefix}/{name}` returned
  the full input/output schema of capabilities the caller's surface cannot see (e.g.
  `mcp`-only), although list hid them. `CatalogPresenter::describe()` takes an optional
  third `$caller` and reads as unknown when the capability is not in that caller's
  effective surfaces; the controller passes the derived caller, so HTTP now returns
  `not_found`, matching list.
- **`CapabilityController` no longer keeps the last request's actor.** The container
  singleton stored every invoke's options (including the authenticated user) for a test
  hook, which leaked across requests on Octane / long-lived workers. The
  `lastInvokeOptions()` method is removed; assert invoke options through a recording
  `CapabilityBus` instead.

## [0.5.3] - 2026-09-29

### Changed

#### InvokePipeline run stage — bug-class errors no longer look like domain errors

Before this release every throwable from a capability's run stage became 422
`domain_error` (cli_exit 5, retryable false) with the exception message passed
through, so PHP bugs and SQL errors reached callers as "domain" failures and
leaked SQL text and model class names. The run-stage catch now maps:

- **`\Error` (incl. `TypeError`) and `PDOException` (incl. `QueryException`)** →
  `internal`, HTTP 500, cli_exit 1, retryable true, message `Internal error.`.
  The exception is reported through the bound `ExceptionHandler`.
- **`ModelNotFoundException`** → `not_found`, HTTP 404, cli_exit 5, retryable false,
  message `Not found.`. Not reported.
- **Everything else** (other `RuntimeException` / `Exception` throws) → unchanged:
  422 `domain_error` with its message.

The `capabilities_invoke_total` status label follows the new codes.

Consumers: clients or middleware that matched 422 plus "No query results for model"
or SQL text must switch to 404/`not_found` and 500/`internal`. A run-stage `internal`
failure under an Idempotency-Key is stored and replayed for the key's TTL like any other
failure, so a retry of a transient error needs a new key.

## [0.5.2] - 2026-08-27

### Fixed

#### capabilities_idempotency.id — string primary key (CLI invokes failed on MySQL)

Every CLI `capabilities run` failed at the idempotency stage on MySQL strict mode
(SQLSTATE 22003 / 1264): the create migration defined `id` as BIGINT auto-increment
while `QueryTableGateway::newId()` writes 32-char hex ids. Approvals and audit_outbox
already used string ids; idempotency was the outlier.

- **Create migration amended** — fresh installs get `id VARCHAR(64)` primary key via
  the shared `MigrationCatalog::defineIdempotency` definition (single source for both
  install paths).
- **New corrective migration `2026_08_27_000001_alter_capabilities_idempotency_id_column`** —
  existing installs converge on the same schema on `php artisan migrate`. Guards:
  no-op when the table is missing (fresh install already correct) or `id` is already
  a string column. Legacy rows are dropped, not converted — the table is an expiring
  idempotency cache (D-005), and rows on non-strict installs hold truncated ids.
  Rolling back is intentionally refused (the old shape cannot store gateway ids).
- **Identity columns narrowed 191 → 160 chars** (`tenant_id`, `actor_id`,
  `capability_name`, `idempotency_key`): the five-column composite unique exceeded
  InnoDB's 3072-byte index limit on utf8mb4 (828 chars × 4 = 3312 bytes → MySQL 1071),
  so table creation/rebuild failed on default-charset MySQL 8. New total
  (160+64+160+160+160) × 4 = 2816 bytes. Column names and semantics unchanged.

Consumers: `composer update rawphp/laravel-capabilities && php artisan migrate`.

### Added

- `ErrorCodeMap` admin-domain error codes: `self_delete` (403), `not_supported` (501),
  `confirmation_failed` (422), `last_super_admin` (409) for platform-admin AdminError mapping.

## [0.5.0] - 2026-08-07

Cumulative: entries shipped in tags `v0.1.0` through `v0.5.0`. Those tags did not get
per-tag sections; this file's git history shows the tag each entry first shipped in.

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

### Notes

- **Not published on Packagist.** Install from package-repo VCS or monorepo path.
- Public Composer package name is `rawphp/laravel-capabilities`; tags and stable `1.x`
  are not claimed until a deliberate release process lands.
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities` on push.

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
Consumers should pin a VCS ref or path checkout and read this changelog before upgrading.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.
Tags without their own section recorded no entries for this package.

[Unreleased]: https://github.com/rawphp/laravel-capabilities
[0.x]: https://github.com/rawphp/laravel-capabilities
