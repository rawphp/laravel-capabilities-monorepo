# Changelog

All notable changes to `rawphp/laravel-capabilities` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Fixed

- **Server-only rules validate only the fields the client sent (D-004).** The
  `IlluminateServerRuleChecker` keeps a field's `nullable`, so a null value skips
  `exists` and `unique` as it does in Laravel. An omitted optional field is no longer
  checked as null. Approval rows store only the fields the client sent, so the
  approved run does not fail on them either. A field whose rule is a single Closure or
  rule object now validates instead of failing as `internal`.
- **An approved request re-gates on the surface it came through (D-022).** After an
  HTTP caller downgrade, the approval row kept only the policy caller, so the approved
  run was refused on a surface the request had passed. Rows now store
  `original_surface`. **Hosts:** run the new
  `add_original_surface_to_capabilities_approvals_table` migration (additive,
  nullable). Rows without the column gate on `original_caller`, as before.
- **HTTP catalog list and describe filter on the credential surface (D-022),** the same
  surface invoke checks. A downgraded client no longer sees capabilities it cannot call.
- **A lost approval race after the domain ran is audited (D-006).** When the row
  expired or was rejected during the run, the executor returned `conflict` with no
  record. It now writes `approval.executed` with `stored: false` and the row status,
  and counts `result=executed_unstored`.
- **A rolled-back strict wrap no longer queues a success audit (D-010).** With
  `audit.required`, a strict audit failure inside a held `wrap_run` transaction put
  the success entry in the outbox, then rolled the domain back.


## [0.6.1] - 2026-09-30

### Fixed

- **Security: the host `Authorizer` gate is restored for class capabilities (0.6.0 regression
  from L-001).** In 0.6.0 any `#[Capability]` class that defined `authorize()` skipped the
  `Authorizer` the host bound, so a host's shared policy gate (for example a token-scope check)
  no longer applied to it. A host-bound `Authorizer` is now a gate every invoke must pass, and
  the capability's own rule (fluent `->authorize()` callable, else the class `authorize()`)
  must pass as well:

  | Own rule | Host `Authorizer` bound | Result |
  |---|---|---|
  | present | yes | Host asked first; if it denies the own rule is not called. Both must allow |
  | present | no | The own rule decides (the L-001 fix stays) |
  | absent | yes | The host `Authorizer` decides |
  | absent | no | Denied (default deny, L-003) |

  "Bound" here means passed to the registry constructor or `withAuthorizer()`, the only ways
  any released version applied a host `Authorizer` (see the container-binding note under
  *Changed (BREAKING)*). The built-in deny fallback is not a gate. The same composition applies
  to the approval accept re-check (`CapabilityRegistry::authorizes()`) and to executing an
  approved row. Hosts on 0.6.0 that used `withAuthorizer()` and relied on a class `authorize()`
  alone will see their `Authorizer` enforced again.
- **The default peer matrix accepts the `laravel/mcp` and `laravel/ai` minors hosts run.**
  Caret on a `0.x` version pins the minor, so `^0.1` rejected `laravel/mcp` 0.9.x and 0.6.0
  refused to boot with `on_incompatible: fail` (0.5.x never read installed versions, so an
  unknown version passed). `PeerSupportMatrix` now lists `laravel/mcp` `^0.1`, `^0.6`, `^0.9`,
  `^1.0` and `laravel/ai` `^0.1`, `^0.10`, `^0.11`, `^1.0`; config `peers.support` reads it
  directly. Hosts that overrode `capabilities.peers.support` to get past boot can drop the
  override.

### Changed (BREAKING)

- **A container binding of `Contracts\Authorizer` is now honoured, for the first time.** No
  released version read it; only the constructor and `withAuthorizer()` applied a host
  `Authorizer`, so a bound but unwired `Authorizer` was ignored. The registry now resolves the
  binding on every authorize decision (never cached, so request-scoped instances are safe, and
  a binding made in any provider's `boot()` counts; `withAuthorizer()` still wins). A binding
  that throws, or resolves to something that is not an `Authorizer`, fails the invoke closed.
  Hosts that bound one (getting-started told them to) will see it enforced, and it now decides
  capabilities with no own rule, which the deny stub used to block. Check what you bound.
  `capabilities:health` `authorizer_bound` now reports whether the registry applies the gate.
- **A host `Authorizer` now also gates fluent `->authorize()` callables.** Before, a fluent
  callable bypassed the `Authorizer`; now the `Authorizer` is asked first and the callable only
  runs if it allows. Upgrade note: a host `Authorizer` that itself delegates to the class
  `authorize()` should drop that delegation, or the class rule runs twice. Hosts that bind an
  `Authorizer` that denies by default and use fluent callables must make it allow those
  capabilities.

## [0.6.0] - 2026-09-30

### Added

- **Discovery class-map cache (L-015).** `php artisan capabilities:cache` writes
  `bootstrap/cache/capabilities.php` — the classes the `#[Capability]` scan finds under
  `capabilities.path` — and `php artisan capabilities:clear` removes it
  (`Adapters\Artisan\CacheCapabilitiesCommand` / `ClearCapabilitiesCommand`,
  `Discovery\DiscoveryManifest`). When the manifest exists, boot discovery
  (`CapabilityDiscoveryBoot::run(..., $manifestPath)`) registers from it and never walks or
  tokenizes the directory; without it, behaviour is unchanged. Hooked into `optimize` /
  `optimize:clear` via `ServiceProvider::optimizes()` (Laravel 11.27+). Like `event:cache`, a
  stale manifest hides new classes until cleared. The two commands, like the scheduled resume
  sweep, are package infrastructure: `ArtisanCommandRegistrar::infrastructure()` /
  `all()` register them regardless of `surfaces.artisan.enabled`; `classes()` stays the ops
  invoke-surface table only.
- **Sibling packages register approval notifiers through the container (M-101 / L-101).**
  `Contracts\ApprovalNotifier::CONTAINER_TAG` (`capabilities.approval_notifiers`) names the tag
  the provider collects extra notifiers from, alongside the plain contract binding; each instance
  is attached once to the single `ApprovalManager`. Approval rows requested by the pipeline now
  carry the invoke context's `messaging` meta (`channel`, `chat_id`, `message_id`, …; `null`
  for HTTP / CLI / job requests) so a chat notifier knows where to put its buttons.
- **`self-update` is a reserved CLI domain (C-008).** `CapabilityDefinition::RESERVED_CLI_DOMAINS`
  gains `self-update`, matching the Go CLI's meta-command dispatcher, so a capability can no
  longer claim a `cli` domain the binary would never route to it. Definitions using that
  domain now fail at registration.
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
- **The approval crash-recovery sweep is scheduled (D-006 / P2-004, L-014).** New ops command
  `capabilities:approvals-resume {--id=} {--force}` (`Adapters\Artisan\ResumeApprovalsCommand`,
  listed in `ArtisanCommandTable`) runs `ResumeApprovedApprovals`. With the default
  `approval.execution = deferred` and `approval.resume.enabled = true` the service provider
  schedules it on the console `Schedule` every `resume.every_seconds` (minute granularity,
  `withoutOverlapping`; pure plan in `Approval\ResumeSchedulePlan`). Before, nothing ran the
  sweep, so a process crash between `approved` and `executed` left the row in limbo and the
  `resume.*` keys were inert. Atomic execution schedules nothing. Requires the host's
  `schedule:run` cron as for any Laravel schedule.
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
  silent no-op). It probes `CapabilityRegistry::audit()`, not a container binding. Warn
  only; exit code unchanged. `IntegrationHealthChecker::check()` takes an optional seventh
  `$auditWriterWired` probe.
- **Audit records are really written (D-010, L-006).** `audit.driver=database` (the default)
  now has a first-party writer: `Persistence\DatabaseAuditWriter` inserts one row per entry
  into `capabilities_audit_outbox` (status `pending`, `available_at = now`, full entry in
  `payload_json`); the row is the durable audit record and a host drain may forward it and
  mark it `completed`. The service provider wires that writer (or a host-bound
  `Contracts\AuditWriter`, which wins) into **both** the registry pipeline and the
  `ApprovalManager`, so `approval.requested` / `approval.decided` / `approval.executed`
  entries land too; `CapabilityRegistry::withAuditWriter()` forwards to
  `registry->approvals()`. Before, no production writer existed and every entry was dropped
  with no error. `ContainerBindings::makeAuditWriter()` and a `makeRegistry(...,
  auditWriter:)` argument are new. **Fail closed:** `audit.enabled` with `mode = strict` or
  `required = true` and no writer (memory driver, or database with no connection) now throws
  `BootException` at boot instead of passing silently; `best_effort` without a writer still
  boots and `capabilities:integration-health` warns.

#### Audit entry `tool_profile` (D-008 / D-010)

Every audit entry now carries `tool_profile`: the tool profile the surface gated the invoke under.
`runCapabilityInProfile()` stamps the **enforced** profile (overwriting any caller-supplied
`tool_profile` option), so agent and MCP adapter invokes record it; sibling surfaces that gate
tools themselves (messaging) pass it as an invoke option. `null` for invokes outside a profile
(HTTP, CLI, job).

### Changed (BREAKING)

#### Approval execution has one idempotency writer (D-005, L-202)

`ApprovalManager::withIdempotency()`, `ApprovalExecutor::withIdempotency()` and the
`idempotency:` constructor argument of both are removed. They enabled a second writer that
marked the request's key `completed` after every approved execution — even a failed one — and
the provider never wired it. The approved execution already runs through the invoke pipeline
under the request's own key (L-102), which moves the row from `pending_approval` to `completed`
or `failed`; that is now the only writer. The settled row keeps its `approval_id`. Hosts that
passed `idempotency:` to `new ApprovalManager(...)` should drop the argument; bind the
`IdempotencyStore` on the registry instead (`withIdempotencyStore()`).

#### `RunCapabilityJob` really queues (D-002 / D-019, L-016)

`RunCapabilityJob` was a plain object whose static `dispatch()` only built an instance —
nothing was ever enqueued, and a job pushed onto the bus by hand threw `unresolvableUser`
for every user id because `handle()` received no `user_resolver`. It now implements
`ShouldQueue` with `Illuminate\Bus\Queueable` (`onQueue`, `onConnection`, `delay`, …):

- `dispatch(array $payload, ?Dispatcher $bus = null)` validates the actor (D-002), then
  pushes the job through the given bus or the container's `Illuminate\Contracts\Bus\Dispatcher`;
  with neither it throws `LogicException` instead of silently doing nothing. Code that used the
  old return value as a pure builder should call the new `make(array $payload)`.
- `handle(CapabilityRegistry $registry, array $options = [])` resolves `actingAs` user ids
  through the registry's requester resolver (the host auth provider the service provider
  wires — the same lookup approvals use; new `CapabilityRegistry::hasRequesterResolver()` /
  `resolveRequester()`) when no `user_resolver` option is passed. No resolver anywhere still
  fails closed.
- `failed(?Throwable $e)` records the D-019 tags plus the exception (`lastFailure()`) and logs
  `capability.job.failed` through the bound `log` service when there is one.



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

#### `DefaultScopeResolver` reads `tenant_id` / `tenantId` as membership (D-003, M-301)

The package default resolver used to place a user actor by the host's `user_tenants` map or
the principal's `current_tenant_id` only. A principal that carried `tenant_id` or `tenantId`
but no `current_tenant_id` resolved to a trusted `tenant_id` invoke option when one was
passed, otherwise to `default-tenant`. Those attributes are server-side membership facts
(a `users.tenant_id` column, the messaging `LinkedUser::$tenantId`), and D-003 makes
membership the authority for user scope, so the resolver now reads them: `user_tenants`,
then `current_tenant_id`, then `tenant_id`, then `tenantId` — first set wins. This applies to
every invoke, not only to approval decisions.

Hosts that see different tenants after upgrading are the ones **without their own
`ScopeResolver` binding** whose principals carry `tenant_id` / `tenantId` but not
`current_tenant_id`:

| Principal | Invoke option | Before | After |
|---|---|---|---|
| `tenant_id = acme` | — | `default-tenant` | `acme` |
| `tenant_id = acme` | `tenant_id => other` | `other` | `acme` |
| `tenantId = acme` (LinkedUser) | — | `default-tenant` | `acme` |
| `current_tenant_id = acme`, `tenant_id = other` | any | `acme` | `acme` (unchanged) |
| no tenant attribute | `tenant_id => other` | `other` | `other` (unchanged) |

The resolved tenant is the `CapabilityScope` `authorize()` / `run()` receive and the tenant
stamped on audit, idempotency and approval rows. On invokes, membership now wins over a
trusted `tenant_id` option for these principals; on `ApprovalGateway::accept()` /
`reject()` the `tenant_id` option no longer overrides the approver's tenant — it fills in
only when the approver has no membership tenant.

**Upgrade:** rows and host data stamped `default-tenant` for these users no longer match
the scope they resolve to, and in-flight idempotency keys for them start a new identity.
Server-side `tenant_id` options passed to `invoke()` / `accept()` / `reject()` are now
ignored for principals with a membership tenant — pass the right principal instead. To keep
the previous rule, bind a `ScopeResolver` in the host
(`$this->app->singleton(ScopeResolver::class, ...)`) that reads only `current_tenant_id`.

#### Unused public helpers removed

These shipped in 0.5.3 and had no callers inside the package:

- `Boot\SurfaceNames::isKnown()`
- `Boot\BootGuard::fromDefaults()` and `BootGuard::evaluatePeer()`: the provider builds
  `BootGuard`, and `validate()['surfaces']` carries each peer surface's status.
- `Support\SchemaSnapshot::document()`
- `Adapters\PeerSupportMatrix::supports()`: use
  `PeerSupportMatrix::versionSatisfies($version, PeerSupportMatrix::for($peer))`.
- `Approval\ApprovalExecutor::withStore()` and `ApprovalExecutor::withMetrics()`: pass the
  store and metrics to the constructor.

#### `Contracts\RateLimiter` gains `availableIn()` (D-013, C-007)

`Contracts\RateLimiter` has a new method, `availableIn(string $key): int` — seconds until
the key's window frees, `0` when unknown. The pipeline reads it for the `rate_limited`
`error.retry_after` hint (see Changed). `InMemoryRateLimiter` and `LaravelCacheRateLimiter`
implement it. **Upgrade:** host `RateLimiter` implementations must add it.

#### `CapabilityController::lastInvokeOptions()` removed

The test hook on `Adapters\Http\CapabilityController` kept the last request's invoke
options, actor included, on the container singleton (see Fixed (HTTP surface)).
**Upgrade:** assert invoke options through a recording `CapabilityBus` instead.

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
- **Bus events reach Laravel's dispatcher and the in-memory window is bounded (D-010 §5,
  L-007).** `CapabilityInvoked`, `CapabilityFailed`, `CapabilityApprovalRequested`,
  `CapabilityApprovalDecided` and `CapabilityApprovalExecuted` were only appended to arrays on
  the registry singleton — no host listener ever fired, and the arrays grew for the life of a
  queue worker. The service provider now hands the app `events` dispatcher to the registry and
  the `ApprovalManager` when `events.enabled` (`CapabilityRegistry::withEventDispatcher()`,
  `ApprovalManager::withEventDispatcher()`, `ApprovalExecutor::withEventDispatcher()` are new);
  events are dispatched after `run()`, so listeners that touch the database should still use
  `afterCommit()`. `registry->invokedEvents()` / `failedEvents()` / `approvalEvents()` /
  `logs()` keep only the newest `InvokeObservation::MAX_RETAINED` (100) entries — a
  diagnostic window, not the delivery channel.
- **Device-code poll contract is spelled out (C-001).** `Contracts\AuthTokenIssuer` now documents
  what the Go CLI drives: `POST {prefix}/auth/device` starts the flow; the CLI polls
  `POST {prefix}/auth/token` with `grant_type = AuthTokenIssuer::GRANT_DEVICE_CODE` (new
  constant) every `interval` seconds (floored to 10 s for the `throttle:6,1` auth stack) and,
  while undecided, expects `data.status` (or `data.error`) in
  `AuthTokenIssuer::DEVICE_POLL_STATUSES` — `authorization_pending`, `slow_down`,
  `access_denied`, `expired_token` — **inside the `ok: true` envelope**, then the token shape.
  The package's fake issuer fixture and the user guide follow the same contract. Host
  implementations of `issueToken()` must handle the device-code grant this way.
- **`surfaces.http.prefix` documents the CLI constraint (C-009).** The Go CLI hardcodes
  `/capabilities/...` after `--base-url`, so the prefix's last segment must be `capabilities`
  (config comment + user guide). No behaviour change.
- **Pipeline `rate_limited` sends a backoff hint (D-013, C-007).** The envelope now carries
  `error.retry_after` (seconds until the tripped per-minute / per-capability window frees)
  and `HttpResponse::fromResult` adds `Retry-After` on 429 when it is present (an explicit
  header passed by the caller wins). The zero-limit edge and the agent turn budget send none.
  Host `RateLimiter` implementations must add `availableIn()` (see Changed (BREAKING)).
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

- **Approved executions run under the scope stamped on the approval row (D-003 / D-006,
  L-401 / L-501 / L-601).** `CapabilityRegistry::executeApproval()` — the default executor behind
  accept and resume — re-invoked the capability as the real requester and let the
  `ScopeResolver` place them again, so a requester who had switched tenant between request
  and decision (for example a new `current_tenant_id`) had the stored input run in the *new*
  tenant: the approver was checked against the row's tenant and `authorize()` was re-checked
  there (`OriginalActorAuthorizer`), but `run()`, audit and the idempotency row landed
  elsewhere, and the original key stayed `pending_approval`. The request now stamps the
  whole resolved scope into the row's `scope` column (`scope_json`) via
  `CapabilityScope::toRow()` — `tenant_id`, `team_id`, `organization_id` and scalar
  `attributes`; the query factory is a closure and is not persisted — and both the accept
  re-check and the executor rebuild it with `CapabilityScope::fromRow()`, so scope,
  authorization, `run()`, audit and the key row all see exactly what the approver saw. The
  row's `tenant_id` is the tenant authority; team / organization / attributes are never
  taken from a fresh resolution of the requester (which could sit in another tenant). A
  rebuilt scope regains `query()` through the container-bound `ScopedQueryFactory`, the same
  route as any resolver-produced scope. Untenanted rows are not unscoped: a team-only or
  org-only host whose `ScopeResolver` leaves the tenant null, and global system work with
  team / organization dimensions, rebuild the stamp with a null tenant, so a requester who
  switched team still runs under the stamped team. Rows written before this change (`scope`
  a bare tenant string) rebuild tenant-only; only rows with neither a tenant nor a `scope`
  array resolve scope at execution time.
- **Approver placement fails closed on any ScopeResolver error (D-003 / D-006, L-402).**
  `ResolveTenantFromCaller::tenantOfPrincipal()` only treated the package's own scope
  exceptions as "not placed"; a host resolver that threw anything else (a `DomainException`
  for a user with no tenant selected, an `ErrorException` from an undefined property on a chat
  `LinkedUser`) escaped `accept()` / `reject()` / `resume()` as a `RuntimeException` — a 500
  on the HTTP approve routes and an unanswered Telegram callback — while the same resolver
  error on an invoke was `forbidden`. Every throwable now yields "not placed": the decision is
  `forbidden`, `run()` never executes, and the host exception is handed to the
  `ExceptionHandler` and counted as `approver_scope_failed_total{caller}`
  (`FailureReporter::APPROVER_SCOPE_FAILED`).
- **In-tenant approvers can accept, reject and resume (D-003 / D-006, M-301).** The approval
  row's `tenant_id` comes from the `ScopeResolver` (`current_tenant_id`, or `default-tenant`
  for a user without one), but `ApprovalManager` and `ApprovalResumer` read the approver's
  `tenant_id` attribute, so with the default wiring every accept / reject over HTTP or a chat
  callback was `forbidden` unless the user carried both attributes with the same value.
  Approvers are now placed by the same resolver that stamped the row
  (`ResolveTenantFromCaller::tenantOfPrincipal()`): `ApprovalManager::withScopeResolver()` /
  constructor `scopeResolver:`; `CapabilityRegistry::withApprovalManager()` and
  `withScopeResolver()` hand the registry's resolver to the manager; the provider gives the
  `ApprovalManager` singleton and the registry the container's `ScopeResolver` binding, so a
  host resolver governs invokes and approval decisions alike. A cross-tenant approver is still
  refused, and a resolver that cannot place the approver fails closed as `forbidden`. The
  companion `DefaultScopeResolver` change (it now reads `tenant_id` / `tenantId` as
  membership, on every invoke) is under *Changed (BREAKING)* above with its upgrade note.
- **One configured ApprovalManager for every approval (D-006, L-101).** The registry pipeline
  used to build its own `new ApprovalManager($store)` from the provider's store, so
  `approval_required` rows ignored `approval.ttl_hours` (always 24 h) and no
  `ApprovalNotifier` ever fired. `CapabilityRegistry::withApprovalManager()` adopts the
  provider's configured singleton (`ContainerBindings::makeRegistry(..., approvalManager:)`),
  re-attaching only the registry run path, audit sink and event dispatcher. The provider now
  attaches every `ApprovalNotifier` the container knows: the contract binding plus anything
  tagged `CapabilitiesServiceProvider::APPROVAL_NOTIFIER_TAG` (`capabilities.approval_notifiers`),
  each instance once. Hosts and sibling packages register notifiers through those two container
  seams; `withApprovalStore()` remains for bare-store wiring with default config.
- **Retrying an approval-gated invoke no longer opens duplicate approvals (D-005 §11, L-102).**
  A repeat invoke with the same `Idempotency-Key` and body whose row is `pending_approval`
  now replays the stored `approval_required` (same `approval_id`, `idempotent_replay` meta)
  instead of re-running the approval gate and creating another pending row. Accepted
  executions run under the row's original key (`CapabilityRegistry::executeApproval()` passes
  `idempotency_key`), so the key moves to `completed` / `failed` and later retries replay the
  executed outcome. `IdempotencyGuard::lookup()` gains an optional `executingApprovalId` that
  lets only that approval's own execution continue past its pending row.
- **Unknown `approvalPolicy` strings fail at definition time and never fall open (D-006, L-106).**
  `ApprovalPolicy::isKnown()` / `assertKnown()` accept only `requester`, `requester_or_role`,
  `any_staff`, `custom` and `role:<name>`. `CapabilityDefinition` rejects anything else
  (attribute, fluent builder and discovery all pass through it) with `InvalidArgumentException`,
  and `approval.default_policy` is checked the same way when the manager config is merged. A
  row that still carries an unrecognised policy now denies every approver instead of
  behaving like `requester_or_role` — before, a typo such as `role-finance` let the requester
  approve their own request.
- **Approved executions are no longer rate-limited as the requester (D-013, L-105).** The
  pipeline skips `stageRateLimit` when `executing_approval_id` is set (accept / resume). The
  request already spent its hit when it was made; re-counting the execution meant a capability
  with `rateLimit(['max' => 1])` that was accepted inside the decay window returned
  `rate_limited` and the row became `executed/failed` with no way to retry.
- **Audit write failures are reported, redacted on the wire, and never abort approvals (D-010, L-104).**
  Every failed `AuditWriter::write()` — invoke audit in either mode, and the
  `approval.requested` / `approval.decided` / `approval.executed` records — now goes to the
  host `ExceptionHandler` and increments `audit_write_failed_total{mode}` on the bound
  `Metrics` (new `Support\FailureReporter`, shared with the L-009 outer catch). Before, a
  best_effort failure left only a line in the capped in-memory observation window; a strict
  failure put `$e->getMessage()` — for a `QueryException`, the SQL plus bound `payload_json` —
  on the wire (now the fixed `Audit failed.`); and approval audit writes were unguarded, so a
  failed insert threw out of `request()`, `accept()` and `execute()` after the row had changed
  state or `run()` had committed. Approval audit records are best_effort by design (the state
  change is the record of truth). Known limit: the first-party `AuditOutbox` that
  `required=true` falls back to is process-local; hosts needing cross-process at-least-once
  should treat `capabilities_audit_outbox` as the durable sink and alert on the metric.
- **A request refused at the idempotency lookup no longer overwrites the key's owner row
  (D-005, found under L-202).** Reusing a key with a different body (`conflict`) or retrying
  while the key is still `processing` (`busy`) used to run the failure finish, which stored the
  refusal under the key: the owner's `completed` row became `failed/conflict` (its own retries
  then replayed `conflict` instead of the stored success), and an in-flight row was flipped to
  `failed` until the run finished. The refused request now never writes the row it did not claim.
- **A failing approval notifier or `CapabilityApprovalRequested` listener no longer turns a saved
  approval into `internal` (D-006, L-201 / M-201).** `ApprovalManager::request()` now calls each
  `ApprovalNotifier::notifyPending()` inside a guard: a throw (chat API outage, a half-configured
  channel) is reported to the `ExceptionHandler` and counted as
  `approval_notify_failed_total{notifier}`, and the remaining notifiers still run. The
  `CapabilityApprovalRequested` event goes through the same guarded dispatch as the other bus
  events (`bus_listener_failed_total{event}`). The caller gets the normal `approval_required`
  with its approval id, and a keyed invoke's idempotency row stays `pending_approval`. Before,
  the pending row was saved but the caller saw `internal` and the key was stored as `failed`, so
  accepting that row replayed `internal` instead of running, and an unkeyed retry opened a second
  approval.
- **A throwing bus-event listener no longer turns a committed run into `internal` (D-010, L-103).**
  `CapabilityInvoked`, `CapabilityFailed`, `CapabilityApprovalDecided` and
  `CapabilityApprovalExecuted` are dispatched inside a guard: a sync listener that throws, or a
  queued listener whose push fails, is reported (`bus_listener_failed_total{event}`) while the
  invoke keeps its success, the idempotency row stays `completed` (so retries replay instead of
  double-applying) and an executed approval stays `executed/ok`. Before, the L-009 outer catch
  rewrote the completed key as `failed/internal` for the whole TTL.
- **`authKind` is `cli_token` only for a `cli`-mapped ability (L-110).** `IlluminateHttpBridge`
  used to flag any token whose ability merely contained `cli` (`client:read`, `clinic:*`,
  `decline`) as a CLI token, disagreeing with `CallerDeriver`. `fromIlluminate()` / `fromArray()`
  take the `clients.token_abilities` map (the Illuminate wrapper controllers receive it from the
  provider) and match exactly, case-insensitively, on abilities mapped to `cli` (default
  `capabilities:cli`).
- **`HttpAuthGate::PROTECTED` no longer lists the auth issuance routes (L-109).** `auth_token`
  and `auth_device` are how the CLI logs in; `isProtected()` returns `false` for every
  `RouteTable::isAuthIssuanceRoute()` key so nothing built on it can lock out device-code login.
- **`AuthTokenIssuer` docblock and the device-code guide state the `capabilities:cli` ability (C-103).**
  Tokens minted for the product CLI must carry the ability mapped to caller `cli` in
  `clients.token_abilities`, or the CLI is an `http` caller and `cli`-only capabilities silently
  vanish from its catalog.
- **`capability:run` works (D-016 / REQ-024).** `RunCapabilityCommand` called a non-existent
  `ArtisanCapabilityInvoker::invoke()`, so every run printed an "undefined method" error and
  exited 1. It now normalises the flags through `ArtisanCapabilityInvoker::parseFlags()`
  (numeric `--acting-as` becomes an int; `--acting-as` with `--system` is refused) and calls
  `run()`. `--acting-as` now loads the host's real user through the registry requester
  resolver (the same lookup approvals use) and fails closed — `MissingArtisanActorException`
  — when the registry has none or the id is unknown (D-002, L-107). Before, the invoker built
  a fabricated `stdClass` "artisan-user-<id>" that `authorize()` implementations calling
  `$user->can()` would have received.
- **The approval resume sweep keeps its command when the artisan invoke surface is off (L-108).**
  `ArtisanCommandRegistrar::infrastructure($approvalConfig)` (the provider registers it via
  `all()`) adds `ResumeApprovalsCommand` whenever `ResumeSchedulePlan::fromConfig()` plans a
  sweep, and the provider passes `approval.*` to it. Before, `surfaces.artisan.enabled=false` unregistered the
  command while `bootResumeSchedule()` still scheduled it, so `schedule:run` failed every minute
  and crash recovery never ran.
- **HTTP routes register once, with their middleware once (REQ-021).** The provider now hands
  the router straight to `HttpRouteRegistrar::registerInto()`, which calls `addRoute()` with a
  `Controller@method` action. Before, the provider set `middleware` in the action and then
  appended it again with `->middleware()`, so every route listed its middleware twice, and a
  router passed directly to `registerInto()` got an array `uses` that Laravel read as a
  closure. The `match()` fallback for routers without `addRoute()` is gone; every Illuminate
  router has `addRoute()`.
- **A stale approval execution counts once (D-019).** `ApprovalExecutor` added the stale
  outcome to `approvals_resume_total{result=stale}` on every path, on top of the per-path
  metric: a stale resume counted twice and a stale accept also counted as a resume. It now
  increments `approvals_accept_total` or `approvals_resume_total` once, by path.
- **`Capability::swapRegistry()` works without a container (D-020).** It registered a no-op
  `resolved()` callback, which dereferences the facade application, so the helper documented
  for container-free unit tests threw unless an app was bound. It now only swaps the root.
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
  hook, which leaked across requests on Octane / long-lived workers. The hook,
  `lastInvokeOptions()`, is removed (see Changed (BREAKING)).

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
