# Core package: rawphp/laravel-capabilities

> Ships with the **laravel-capabilities** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Define a product capability once (schema, authorization, `run`, optional approval and audit) and expose it through agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

**Namespace:** `Rawphp\Capabilities\`  
**Status:** 0.x pre-stable, path or package-repo VCS install (not Packagist-published)

Peer matrix, durable persistence, and D-020 detail live in the [package README](../README.md). Full design oracle (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md).

## Before you start

- Install via package VCS or monorepo path — see [README install](../README.md#install)
- PHP ^8.2, Laravel 11/12/13 illuminate components as declared in package `composer.json` (Laravel 13 apps need PHP ^8.3 per framework)
- Optional peers: `laravel/ai`, `laravel/mcp` when agent/MCP surfaces are enabled

## Define a capability

Two registration paths produce the same definition shape. Prefer **one** style per capability name — never both.

### Fluent (explicit register)

```php
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;

/** @var CapabilityRegistry $registry */
$registry = app(CapabilityRegistry::class);

Capability::define('create-invoice')
    ->description('Create an invoice for a customer.')
    ->surfaces(['agent', 'mcp', 'http', 'cli', 'job'])
    ->input(CreateInvoiceInput::class)
    ->output(CreateInvoiceResult::class)
    ->groups(['billing'])
    ->idempotent('optional')
    ->authorize(function (CreateInvoiceInput $input, CapabilityContext $ctx): bool {
        // Re-resolve resources under scope; never trust client ids alone
        return $ctx->user() !== null;
    })
    ->run(function (CreateInvoiceInput $input, CapabilityContext $ctx): CreateInvoiceResult {
        // Single domain write path
        return new CreateInvoiceResult(invoice_id: 1);
    })
    ->register($registry);
```

Builder highlights (non-exhaustive): `description`, `surfaces`, `input`, `output`, `aliases`, deprecation fields, `groups`, `tags`, `idempotent`, `idempotencyKeyFields` (derive a key from named input fields when the caller sends none), `authorize`, `needsApproval` (`(Input, CapabilityContext): bool` — true stores an approval request and returns `approval_required` instead of running), `approvalPolicy` / `approvalTtlHours` (who may decide — one of `requester`, `requester_or_role`, `any_staff`, `custom`, `role:<name>`; anything else is rejected when the definition is built — and for how long the request stays pending; both travel on the approval row), `run`, `register`.

### CLI routing metadata (`domain` / `verb`)

Optional product-CLI synthesis metadata. When **both** domain and verb are set, catalog list/describe emit:

```json
"cli": { "domain": "invoices", "verb": "create" }
```

Agents then call `capabilities invoices create …` instead of only `capabilities run create-invoice`.

```php
Capability::define('create-invoice')
    // …
    ->cli('invoices', 'create')
    ->register($registry);

// Attribute form:
#[Capability(
    name: 'create-invoice',
    // …
    cliDomain: 'invoices',
    cliVerb: 'create',
)]
```

Rules (fail closed):

- Domain and verb tokens: lowercase `[a-z][a-z0-9-]*`
- Incomplete metadata (only domain or only verb) → definition error
- Domain must not collide with reserved CLI meta-commands (`auth`, `catalog`, `describe`, `run`, `mcp`, `approvals`, `version`, `help`, `self-update`)
- Two definitions claiming the same `(domain, verb)` → register/boot failure (server authoritative)
- Omit `cli` when unmapped — entry stays valid; clients use `run <name>` only
- `cli` is **routing only** — JSON Schema remains the sole input/output contract


### Attribute + discovery (canonical path)

Place classes under `config('capabilities.path')` (default `app/Capabilities`) with `#[Rawphp\Capabilities\Attributes\Capability]` implementing `Rawphp\Capabilities\Contracts\DefinesCapability`. Boot discovery runs through the service provider — do not invent a third registration mechanism.

**Cache the class map in production.** Without a cache every boot (each request, each queue job) walks and tokenizes the discovery path. `php artisan capabilities:cache` writes `bootstrap/cache/capabilities.php` (the classes the attribute scan finds — a cache, not another discovery path) and boot uses it instead of scanning; `php artisan capabilities:clear` removes it. Both are hooked into `optimize` / `optimize:clear` on Laravel 11.27+. Run `capabilities:clear` (or `optimize`) after adding, renaming or removing a capability class, exactly as for `event:cache`.

The class owns its governance. On every invoke the pipeline resolves the handler **once through the container** (constructor injection works) and calls, in order:

- `authorize(Input $input, CapabilityContext $ctx): bool` — the decision for this capability. Without it, the host `Authorizer` decides (deny by default).
- `needsApproval(Input $input, CapabilityContext $ctx): bool` — optional; `true` stores an approval request and returns `approval_required` without calling `run()`.
- `run(Input $input, CapabilityContext $ctx)` — the single mutation path.

Each method may declare `(Input $input)` alone; the context is passed only when the signature takes a second argument. Unit tests swap construction with `CapabilityRegistry::withHandlerFactory(fn (string $class) => ...)`.

Full teaching sample (monorepo): [First capability tutorial](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/tutorials/first-capability.md).

### Input / output DTOs

Extend `Rawphp\Capabilities\Support\CapabilityData`. Use `#[Field(...)]` attributes for JSON Schema-facing constraints. Schema on the wire comes from types (portable for CLI/catalog), not from Laravel rule strings alone. Mark personal or secret fields with `#[Field(sensitive: true)]` (emitted as `writeOnly: true`); the audit log stores `[REDACTED]` for them, including inside nested DTOs and array items, in addition to keys that contain `password`, `secret`, `token`, `apikey` or `authorization`.

## Invoke

Every surface ends at the registry:

```php
use Rawphp\Capabilities\Facades\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

$registry = app(CapabilityRegistry::class);

$result = $registry->invoke('create-invoice', [
    'customer_id' => 1,
    'amount_cents' => 2500,
    'currency' => 'USD',
], [
    // caller is normally set by the adapter
    'caller' => 'http',
    // who is acting — required on every surface (D-002); never inferred
    'actor' => $request->user(),
]);

if ($result->ok) {
    // success payload in $result->data
}

$result->assertOk(); // throws on deny/fail
```

Facade: `Capability::invoke(...)` when the container binding is present.

For unit tests without a full app boot, construct `CapabilityRegistry` with fakes (in-memory approval/idempotency/audit) as the package suite does.

## Scope (tenancy)

After the actor is known and before `authorize()` / `run()`, every invoke resolves a `CapabilityScope` (`tenantId`, `teamId`, `organizationId`, `attributes`) through the container's `Contracts\ScopeResolver` (D-003). Read it with `$ctx->scope()` / `$ctx->tenantId()`; `$scope->query(Model::class)` starts a scoped query through your bound `Contracts\ScopedQueryFactory`. The resolved tenant is stamped on audit, idempotency and approval rows.

The package binds `Support\DefaultScopeResolver`:

- **User actors:** the tenant comes from membership attributes on the principal — `current_tenant_id`, then `tenant_id`, then `tenantId` (first set wins). A trusted server-side `tenant_id` invoke option applies only when the principal has none of these; with neither, the tenant is `default-tenant`. Team and organization come from `current_team_id` / `current_organization_id`.
- **`SystemActor`:** the tenant comes only from first-class job / context fields (for example `RunCapabilityJob`'s `tenantId`), never from capability input.

To use another rule, bind your own resolver in the host (`$this->app->singleton(ScopeResolver::class, ...)`). The same binding governs invokes and approval decisions.

**Approvals:** the request stamps the whole resolved scope on the approval row. Approvers are placed by the same resolver: an approver outside the row's tenant is `forbidden`, and so is one the resolver cannot place — a resolver that throws is reported to the `ExceptionHandler` and counted as `approver_scope_failed_total{caller}`. The accept re-check and the approved execution run under the scope stamped on the row (null tenant included), not under a fresh resolution of the requester, so `run()`, audit and the idempotency row see what the approver saw.

## Surfaces

Global switches live in published `config/capabilities.php` under `surfaces.*`:

| Surface | Config key | Notes |
|---|---|---|
| Agent | `surfaces.agent` | Needs `laravel/ai` when enabled + `require_package` |
| MCP | `surfaces.mcp` | **Product MCP (server):** needs `laravel/mcp`; named profiles; optional `auto_register` (default true) via `McpServerRegistrar`; auth under `surfaces.mcp.auth` |
| HTTP | `surfaces.http` | Default prefix `capabilities`; middleware `api`, `auth:sanctum` — also the transport the product CLI uses |
| CLI | `surfaces.cli` | Marks capabilities available to product CLI **HTTP** callers (not an MCP bridge) |
| Job | `surfaces.job` | `RunCapabilityJob::dispatch(['name' => …, 'input' => …, 'actingAs' => $userId \| SystemActor::named('scheduler'), 'tenantId' => …])` queues a real `ShouldQueue` job; the worker runs it through the registry. An explicit actor is required (not “null user = allow”); user ids resolve through the default auth provider |
| Artisan | `surfaces.artisan` | Optional **in-server** ops — not the downloadable product CLI |
| Messaging | `surfaces.messaging` | Conversation channel flag; implementation is the **sibling** package. Invokes carrying messaging metadata stay `caller: agent` but are refused while this is off |

A capability’s `->surfaces([...])` list only **narrows** what global config already allows.

### Product MCP vs product CLI

| | **Product MCP** | **Product CLI** |
|---|---|---|
| Where | Server (`laravel/mcp` + this package) | Laptop binary `capabilities` |
| How tools appear | Boot **plans** servers from `surfaces.mcp.profiles` / `servers` (`auto_register`) and may call `McpToolAdapter::register`; host still wires peer MCP routes (e.g. `Mcp::web` / peer docs) or uses manual `Capability::mcpTools(profile: …)` | HTTP `catalog` / `run` / domain verbs only |
| Hosts | Cursor, Claude Desktop, other MCP clients → **app** MCP endpoints the **host** mounts (plan rows include a planned `path` under `path_prefix`, default `/mcp/{profile}` — not a live auto-mount by this package) | Shell agents / humans over the capability HTTP API |
| Not | The CLI binary | An MCP stdio server — `capabilities mcp` was **removed** |

### Worked example: MCP-only capability and principals

A capability that MCP hosts may call, and nothing else:

```php
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\SystemActor;

Capability::define('send-invoice-reminder')
    ->description('Email a payment reminder for an open invoice.')
    ->surfaces(['mcp'])                      // narrows: no agent/http/cli/job
    ->input(SendInvoiceReminderInput::class)
    ->groups(['billing'])
    ->allowSystemCallers(['billing-bot'])    // only needed for `integration` principals
    ->authorize(function (SendInvoiceReminderInput $input, CapabilityContext $ctx): bool {
        $actor = $ctx->actor();              // User or SystemActor, never null

        return $actor instanceof SystemActor
            ? true                           // allow-list above already gated the name
            : $actor->can('remind', Invoice::class);
    })
    ->run(fn (SendInvoiceReminderInput $input, CapabilityContext $ctx) => /* one domain write */)
    ->register($registry);
```

```php
// config/capabilities.php
'mcp' => [
    'enabled' => true,
    'profiles' => ['billing' => ['send-invoice-reminder', 'list-invoices']],
    'auth' => [
        'default_profile' => 'user_pat',
        'allow_integration_credentials' => true,     // default false
        'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        'integration_profiles' => ['mcp-billing-service' => ['billing']], // profiles that client may use
    ],
],

// Host wiring (the package plans servers; the host mounts them)
use Laravel\Mcp\Facades\Mcp;
use Rawphp\Capabilities\Facades\Capability;

Mcp::web('billing', fn ($server) => $server->tools(Capability::mcpTools(profile: 'billing')));
```

Three gates apply in order: the capability must be in the MCP **profile** the host mounted (else `capability_not_in_profile`); the host's credential must resolve to a **principal** (D-023); then `authorize()` runs as usual. The credential decides the actor, never tool input — keys like `actor`, `user_id`, `caller`, `client_id`, `auth_profile`, `tenant_id` in arguments are refused as `forbidden`.

Once `mcp` is enabled, who can invoke `send-invoice-reminder`:

| Principal | Credential | Actor in `run()` | Can invoke when | Refused as |
|---|---|---|---|---|
| `user_pat` | `McpCredential::userPat($user)` | That `User` | Always resolves; `authorize()` decides. `allowSystemCallers` is ignored | `unauthenticated` if no user is bound |
| `integration` | `McpCredential::integration('mcp-billing-service')` | `SystemActor` `billing-bot` | `allow_integration_credentials` is true, `client_id` is in `integration_actors`, the call's profile is listed for that `client_id` in `integration_profiles`, **and** the mapped name is in `allowSystemCallers` | `forbidden` when integration is off, the profile is not listed (`normalized_code: integration_profile_forbidden`) or the name is not allowed; `unauthenticated` for a missing or unknown `client_id` |
| `user_delegated` | `McpCredential::userDelegated($user, 'cursor-mcp')` | The delegating `User` | `client_id` is present; `authorize()` decides. `allowSystemCallers` is ignored | `unauthenticated` if no user or no `client_id` |

Every successful call records `caller: mcp` and `mcp.auth_profile`; `integration` and `user_delegated` also record `mcp.client_id` (`user_pat` only when a client id is supplied and `audit_client_id` is on). An `integration` principal's tenant comes from the trusted credential session (`session.tenant_id`), never from tool input.

For `integration` principals `$ctx->user()` is `null` — an `authorize()` that only checks `$ctx->user() !== null` (like the `create-invoice` example above) will deny every integration call. Branch on `$ctx->actor()` instead. The table is pinned by the monorepo unit test [`AuthProfileCapabilityMatrixTest`](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/packages/laravel-capabilities/tests/Unit/Mcp/AuthProfileCapabilityMatrixTest.php).

### HTTP API (single tree)

When `surfaces.http.enabled` is true, routes come from `RouteTable` (default prefix `capabilities`):

| Method | Path | Role |
|---|---|---|
| `GET` | `/capabilities` | Catalog list |
| `GET` | `/capabilities/health` | Health |
| `POST` | `/capabilities/auth/token` | Token auth |
| `POST` | `/capabilities/auth/device` | Device auth |
| `GET` | `/capabilities/auth/callback` | OAuth callback |
| `POST` | `/capabilities/approvals/{id}/accept` | Accept approval |
| `POST` | `/capabilities/approvals/{id}/reject` | Reject approval |
| `GET` | `/capabilities/{name}` | Describe one capability |
| `POST` | `/capabilities/{name}` | **Invoke** |

The three `auth/*` routes issue credentials, so they skip `auth:*` middleware and are throttled per client IP (`throttle:6,1,capabilities-auth`). Set `surfaces.http.auth_middleware` to a middleware list to replace that stack, for example to allow faster device-code polling.

Product CLI is a remote client of **this** API. Do not add a second invoke controller tree.

#### Device-code login (what the CLI expects from your `AuthTokenIssuer`)

`capabilities auth login --base-url=…` runs RFC 8628 against the two `auth/*` routes above; the host binds `Rawphp\Capabilities\Contracts\AuthTokenIssuer` and core only wraps its return value in the `ok: true` envelope.

| Step | Route | Your issuer returns (`data`) |
|---|---|---|
| Start | `POST …/auth/device` `{"client_id":"capabilities-cli"}` → `issueDeviceCode()` | `device_code`, `user_code`, `verification_uri`, `expires_in`, `interval` |
| Poll (every `interval` s, floored to 10 s by the CLI) | `POST …/auth/token` `{"grant_type":"urn:ietf:params:oauth:grant-type:device_code","device_code":…,"client_id":"capabilities-cli"}` → `issueToken()` | while undecided: `{"status":"authorization_pending"}` (or `{"error":"authorization_pending"}`); `{"status":"slow_down"}` adds 5 s; `{"status":"access_denied"}` / `{"status":"expired_token"}` end the login |
| Approved | same poll | `access_token`, `token_type`, `expires_in` — minted **with the CLI ability** (see below) |

The token your issuer mints (and any PAT a user pastes into `capabilities auth login --token`) must carry the ability mapped to caller `cli` in `clients.token_abilities` — by default `capabilities:cli`, e.g. `$user->createToken('cli', ['capabilities:cli'])->plainTextToken`. Core derives the caller from that ability alone (D-022): a token without it is an `http` caller, so capabilities exposed on `cli` but not `http` disappear from the CLI catalog and `run` returns `not_found` with nothing pointing at the credential. The same map decides the request's `authKind` (`cli_token` only for an exact match on a `cli`-mapped ability).

Pending statuses travel **inside** `ok: true` — do not throw or return an error envelope for them. The constants `AuthTokenIssuer::GRANT_DEVICE_CODE` and `AuthTokenIssuer::DEVICE_POLL_STATUSES` spell the wire values. Keep `interval >= 10` (the CLI polls no faster) unless `surfaces.http.auth_middleware` replaces the default `throttle:6,1,capabilities-auth` stack; an HTTP 429 makes the CLI back off by `Retry-After`.

Example invoke:

```http
POST /capabilities/create-invoice
Authorization: Bearer ***
Content-Type: application/json
Idempotency-Key: <optional-or-cli-generated>

{
  "customer_id": 1,
  "amount_cents": 2500,
  "currency": "USD"
}
```

## Config highlights

Publish: `php artisan vendor:publish --tag=capabilities-config`

| Area | Keys (defaults sketched) | Why you care |
|---|---|---|
| Discovery | `path` → `app/Capabilities` | Attribute discovery root |
| Agent/MCP profiles | `surfaces.*.profiles`, `require_profile`, tool count limits | Never dump full catalog by default (`name => list<string>` for MCP) |
| Peer mismatch | `on_incompatible` → `fail` \| `disable` | Boot fail vs soft-disable |
| MCP register errors | `surfaces.mcp.on_register_error` → `throw` (default) \| `disable` | Mid-mount adapter failure policy for non-empty plans |
| HTTP | `prefix`, `middleware`, `auth_middleware` | Route mount and auth. The product CLI appends `/capabilities/…` to its `--base-url`, so `prefix` must end in `capabilities` (`api/capabilities` → `--base-url=https://host/api`). The unauthenticated `auth/*` routes drop `auth:*` and get `throttle:6,1,capabilities-auth` unless `auth_middleware` replaces that stack |
| Approval | `store`, `ttl_hours`, `execution`, `resume.*` | Human-in-the-loop. With `execution=deferred` (default) and `resume.enabled`, the package schedules `capabilities:approvals-resume` every `resume.every_seconds` to finish approvals whose process died after `approved` — keep `schedule:run` in cron |
| Idempotency | `enabled` (false makes the guard inert: no lookup, no store), `driver` (default `database`; use `memory` only for single-process tests), `ttl_hours` (stored outcome lifetime, default 24), `header` (`Idempotency-Key`; the one HTTP header setting), `warn_missing_key` | Safe retries; AI proposal accept readiness pings this store |
| Transactions | `wrap_run` (default `false`) | Opt-in: runs `run()` inside a database transaction (a domain throw rolls back). With no connection wired the invoke fails closed with `not_configured`. Output validation, idempotency storage and audit happen after commit |
| Events | `enabled` | Bus events (`CapabilityInvoked`, `CapabilityFailed`, `CapabilityApproval*`) are dispatched to the app's event dispatcher after `run()`; listen with normal Laravel listeners and use `afterCommit()` when you touch the database |
| Audit | `enabled`, `mode` (`best_effort`), `driver` (`database`), `required` | Observability of invokes and approvals. Each entry carries `tool_profile` (the agent/MCP profile the call was gated under; `null` outside a profile). `driver=database` writes one row per entry to `capabilities_audit_outbox` (`DatabaseAuditWriter`; bind your own `Contracts\AuditWriter` to replace it). `mode=strict` or `required=true` without any writer fails boot (D-010). A write that fails at runtime is reported to your `ExceptionHandler` and counted as `audit_write_failed_total{mode}`; in `strict` the caller gets `audit_failed` with the fixed message `Audit failed.` (the driver exception never reaches the wire), in `best_effort` the invoke still succeeds. Approval audit records (`approval.requested/decided/executed`) are always best_effort. Bus-event listeners that throw after `run()` are reported (`bus_listener_failed_total`) and do not change the invoke outcome or its stored idempotency row. A single capability can force strict with `->audit(['mode' => 'strict'])` (or `audit: ['mode' => 'strict']` on the attribute); it can only tighten the global mode, never loosen it |
| Rate limits | `driver` (`cache` default — keep it on a shared cache store for multi-worker hosts; `memory` is process-local), `defaults.per_minute`, per-capability, agent turn max tools | Abuse control |
| Clients | `token_abilities` (e.g. `capabilities:cli` → `cli`), privilege order | Caller derivation |
| Peers | `peers.support` | Mirrors `PeerSupportMatrix` |

Env knobs used in the scaffold include `CAPABILITIES_SURFACE_*`, `CAPABILITIES_*_ON_INCOMPATIBLE`, `CAPABILITIES_AUDIT_MODE`, `CAPABILITIES_IDEMPOTENCY_DRIVER`, and related flags — see the published config file for the full list.

## Profiles

Agent and MCP tool exposure uses **profiles** (D-008). Configure named profile → capability name lists under the surface config. Selectors also understand forms such as `groups:…`, `only:…`, and `profile:…` via `ProfileSelector`.

Rules of thumb:

- Profiles limit **discovery** of tools — and execution: with `require_profile` (default) an adapter `handle()` with no registered or per-call profile returns `not_runnable` / `profile_required` instead of running a capability by name.
- `authorize()` still runs on every invoke.
- Messaging sets `agent_profile` so bots do not see the entire bus.

## Peers (`laravel/ai` / `laravel/mcp`)

| What | Where |
|---|---|
| Matrix source of truth | `src/Adapters/PeerSupportMatrix.php` |
| Config mirror | `peers.support` |
| Declared constraints (current scaffold) | `laravel/ai`: `^0.1`, `^1.0`; `laravel/mcp`: `^0.1`, `^1.0` |
| MCP auto-register (plan) | `Adapters\Mcp\McpServerRegistrar` + `surfaces.mcp.auto_register` / `profiles` / `servers` / planned `path_prefix` |

When agent or MCP is enabled and the peer is missing or `supportsInstalledPeer() === false`:

| `on_incompatible` | Behaviour |
|---|---|
| `fail` (default) | Boot exception — surface does not register |
| `disable` | Soft-disable + CRITICAL log + health `disabled_incompatible` |

**Never half-register tools.** Default package CI does not install live peers; honesty is matrix + unit contract fixtures. Live peer exercise is an optional **consumer app** path. Empty MCP plan (no profiles/servers, or `auto_register` false) soft-fails before peer evaluation so missing `laravel/mcp` does not hard-fail boot solely for an empty plan (see package CHANGELOG / ORI-801).

### MCP auto-register (plan + host wire)

With `surfaces.mcp.enabled` and a compatible `laravel/mcp` peer:

1. Define named **profiles** under `surfaces.mcp.profiles` (capability name lists — D-008 / D-024: **`name => list<string>` capability names only**), or explicit `servers` rows.
2. Leave **`auto_register` true** (default): production boot builds a **server plan** via `McpServerRegistrar` and may call `McpToolAdapter::register` for each planned profile. That loads profile tools on the adapter and returns planned server definitions (name, profile, planned `path`, tools).
3. **Allowlist validation** at register: profile capability names must exist and expose the MCP surface. Unknown or non-MCP names fail closed (throw) when a registry is provided.
4. **`on_register_error`** (`surfaces.mcp.on_register_error` / `CAPABILITIES_MCP_ON_REGISTER_ERROR`, default **`throw`**): when a **non-empty** plan hits an unexpected mid-mount adapter `Throwable`, rethrow (default) or soft-empty tools when set to **`disable`**. **Empty plan** remains soft-fail without peer evaluation (ORI-801) — distinct from mid-mount failures.
5. Production `bootMcpServers()` does **not** push those definitions into `laravel/mcp` (there is no peer sink analogous to `HttpRouteRegistrar::registerInto`). Integrators still **host-wire** peer MCP servers themselves (e.g. `Mcp::web` / peer docs) using the planned tools/profiles (or manual `Capability::mcpTools`).
6. Planned paths use `path_prefix` (default `/mcp`) only as plan metadata (`/mcp/{profile}`). Clients reach whatever routes the **host** actually mounts — not a package live auto-mount at `path_prefix`.
7. **Multi-profile residual:** sequential `adapter->register` overwrites the adapter’s active profile/tools (**last profile wins**). Once more than one distinct profile is registered, `handle()` without `options['profile']` fails closed (`not_runnable`, `normalized_code: profile_required`). For multiple live MCP servers, wire each peer server with its own tool set and pass its profile on every call.
8. Set `auto_register` false when you want no plan/register loop at boot and will select tools only via your own host wiring.

Maintainer filters: see [package README — Peer support](../README.md#peer-support--d-011-release-gate).

### Integration health vs HTTP health

Two different readiness signals — do not merge:

| Surface | What | Purpose |
|---------|------|---------|
| **Artisan** `php artisan capabilities:approvals-resume [--id=…] [--force]` | `ResumeApprovalsCommand` / `ResumeApprovedApprovals` | Crash-recovery sweep for approved-but-not-executed approvals (D-006). Scheduled automatically when `approval.execution=deferred` and `approval.resume.enabled` — and registered whenever it is scheduled, even with `surfaces.artisan.enabled=false`; `--force --id=…` is the operator repair path that ignores grace and lease |
| **Artisan** `php artisan capabilities:cache` / `capabilities:clear` | `CacheCapabilitiesCommand` / `ClearCapabilitiesCommand` / `Discovery\DiscoveryManifest` | Write / remove `bootstrap/cache/capabilities.php`, the cached `#[Capability]` class map boot uses instead of scanning `capabilities.path` (L-015). Registered whatever `surfaces.artisan.enabled` says; wired into `optimize` / `optimize:clear` |
| **Artisan** `php artisan capabilities:integration-health` | `IntegrationHealthChecker` / `IntegrationHealthCommand` | Host **product** readiness: bindings, audit writer wired into the registry (warn when audit is on but records would be dropped), AI-chat mode, MCP tool counts, proposals + AlwaysReady safety, live AI progress-store ping (`ai_progress_ready`), progress/queue ops checks when AI package config is present |
| **HTTP** `GET /{prefix}/health` (default `/capabilities/health`) | `CatalogHealth` / controller | **Surface/catalog** peer health for HTTP clients (D-011 / D-021), plus `api_version` (`RouteTable::API_VERSION`) that the product CLI checks before `run` |

**AI-chat mode** (integration-health only): `capabilities-ai.routes.enabled === true` **OR** non-empty `capabilities-ai.queue.name`. Core does not require the AI package to boot; AI rows appear when `capabilities-ai` config is present.

```bash
php artisan capabilities:integration-health
# exit 0 when fail set is clean; non-zero when any fail-level check fails
```

Greenfield AI-chat hosts use this after queue/progress/proposals config — see AI package user guide host-integration section (package repo docs).

## Approval and idempotency (operator view)

- **Approval:** a definition's `needsApproval` (fluent callable or class method) decides per invoke; `true` stores a pending row and returns `approval_required` without calling `run()`. Who may accept or reject is the capability's `approvalPolicy` when declared (stored on the row), otherwise `approval.default_policy`. HTTP accept/reject routes are on the capability prefix. Accept runs the capability once through the registry pipeline as the original requester (re-validate, re-authorize, `run()`, output contract) under the row's stamped scope — see [Scope (tenancy)](#scope-tenancy); a requester the host auth provider can no longer resolve fails closed as `forbidden` without running. Accepting or rejecting a row whose execution lease is still live returns `conflict` with `in_progress: true`. Pending rows are announced through every `ApprovalNotifier` the container knows — bind the contract, or tag implementations with `CapabilitiesServiceProvider::APPROVAL_NOTIFIER_TAG` (`capabilities.approval_notifiers`) when several channels apply; the messaging package registers its Telegram notifier this way. A notifier that throws is reported (`approval_notify_failed_total{notifier}`) and never changes the outcome: the row stays pending, the other notifiers still run, and the caller gets `approval_required`. `approval.ttl_hours` (capped per capability by `approvalTtlHours`) sets `expires_at`.
- **Idempotency:** when enabled and the definition uses it, repeated keys replay stored outcomes instead of double-applying. CLI always sends a key on `run`. A retry of an approval-gated invoke under the same key replays the one `approval_required` (same `approval_id`) rather than opening a second approval; the accepted execution runs under that key, so later retries replay the executed outcome.

**Telegram approval notifiers (upgrade):** For in-memory recording doubles (tests/fakes — **no** Bot API in core), use `RecordingTelegramApprovalNotifier` (`Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier`). Core still ships a **deprecated soft-landing** empty subclass `TelegramApprovalNotifier` of that recording double (still loadable; recording-only). Production Telegram Bot API delivery is the **messaging** package FQCN `Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier` — a different class, unchanged by this rename. Full consumer impact: package [CHANGELOG](../CHANGELOG.md) 0.5.0 **Breaking** and [README](../README.md) Telegram notifier / sibling notes. Pre-stable monorepo design surface — not a Packagist-stable API claim; soft-landing remains until a later removal.

Deep state machine detail (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md).

## Error codes

Every failure is a `CapabilityResult` with `ok: false` and an `error.code`. The source of truth is `src/Support/ErrorCodeMap.php` (D-018); the monorepo unit test [`ErrorCodesUserGuideTest`](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/packages/laravel-capabilities/tests/Unit/Errors/ErrorCodesUserGuideTest.php) fails if this table drifts from it.

How one code presents on each surface:

- **HTTP:** the response status is `error.http_status` and the body is the result envelope (`ok`, `error`, `meta`). A pipeline `rate_limited` carries `error.retry_after` (seconds until the tripped window frees) and the 429 response repeats it as `Retry-After`; the product CLI prints it as its backoff hint.
- **Product CLI:** `--json` prints the same envelope as HTTP; the process exits with `error.cli_exit` (success is `0`).
- **Agent / MCP:** tool handles return a structured error (`code`, `message`, `structured: true`, `retryable`, `details`). A few codes are renamed for tool callers (see the last column); `details` still carries the original registry error, including its `code`.
- **Job / direct `invoke`:** the `CapabilityResult` itself. Branch on `isRetryable()` and `isHardRefuse()`, not on message text.
- **Artisan `capability:run`** (in-server ops, not the product CLI): prints `error.message` and exits `1` for every code. `cli_exit` applies to the product CLI only. `--acting-as=<id>` runs as the real user returned by the default auth guard's user provider (the registry requester resolver); an unknown id, or a registry without a resolver, exits `1` without running anything. `--system=<name>` runs as a `SystemActor` the capability must allow.

| Code | HTTP | CLI exit | Retryable | Agent / MCP code |
|---|---|---|---|---|
| `validation_failed` | 422 | 2 | no | `schema_invalid` |
| `unauthenticated` | 401 | 3 | no | `unauthenticated` |
| `forbidden` | 403 | 3 | no | `unauthorized` |
| `self_delete` | 403 | 3 | no | `self_delete` |
| `capability_not_in_profile` | 403 | 3 | no | `not_in_profile` |
| `approval_required` | 202 | 4 | no | `approval_required` |
| `domain_error` | 422 | 5 | no | `domain_error` |
| `confirmation_failed` | 422 | 5 | no | `confirmation_failed` |
| `conflict` | 409 | 5 | no | `conflict` |
| `last_super_admin` | 409 | 5 | no | `last_super_admin` |
| `not_found` | 404 | 5 | no | `not_found` |
| `gone` | 410 | 5 | no | `gone` |
| `expired` | 410 | 5 | no | `expired` |
| `not_configured` | 501 | 5 | no | `not_configured` |
| `not_supported` | 501 | 5 | no | `not_supported` |
| `output_invalid` | 500 | 5 | no | `output_invalid` |
| `rate_limited` | 429 | 6 | yes | `rate_limited` |
| `internal` | 500 | 1 | yes | `internal` |
| `audit_failed` | 500 | 1 | no | `audit_failed` |
| `not_runnable` | 500 | 1 | no | `not_runnable` |

Notes:

- `approval_required` is not a failure to retry. It carries `error.approval_id`; resolve it through the approval accept/reject routes.
- Hard refuses (`forbidden`, `capability_not_in_profile`, `not_runnable`, `unauthenticated`) are terminal. Retrying the same call with the same credentials will not succeed.
- Retryable is a default. A result may override `retryable`, `http_status`, or `cli_exit` explicitly, so clients should read the fields on the error rather than hard-code this table.
- Unknown codes fall back to HTTP `500`, CLI exit `1`, not retryable.

## Testing helpers (D-020)

On registry and `Capability` facade:

### `assertSchemaSnapshot`

Locks **input_schema + output_schema** from the live catalog.

```php
use Rawphp\Capabilities\Facades\Capability;

// Durable file
Capability::assertSchemaSnapshot(
    'create-invoice',
    base_path('tests/fixtures/capability-schemas/create-invoice.schema.json'),
);

// Conventional directory → {dir}/{name}.schema.json
Capability::assertSchemaSnapshot(
    'create-invoice',
    null,
    base_path('tests/fixtures/capability-schemas'),
);
```

Name-only `assertSchemaSnapshot('create-invoice')` does **not** lock schemas — always pass a path, directory, or in-memory envelope in CI.

### `assertParity`

Same input → same success/deny **class** across listed surfaces (registry/adapter unit paths with mocks/fakes — not a live multi-surface HTTP suite).

```php
Capability::assertParity('create-invoice', [
    'input' => [
        'customer_id' => 1,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ],
    'surfaces' => ['http', 'registry', 'job'], // required, non-empty
]);
```

Surface labels include `http`, `cli`, `agent`, `mcp`, `job`, `artisan`, plus aliases `ai` → agent, `registry` → http. Empty options / missing `surfaces` throw. Approval-required counts as deny class for parity.

## How you know it worked

- Capability registers without conflicting double-define.
- `invoke` returns `ok` for authorized valid input and denies without calling `run()` when authorize fails.
- HTTP catalog lists the capability when the http surface and capability surfaces allow it.
- Schema snapshots stay green in app CI after intentional updates only.
- `php artisan capabilities:integration-health` reports a clean fail set for your intended mode (bus-only vs AI-chat vs MCP lab).

## If something goes wrong

Common boot, peer, and HTTP failures (monorepo): [Troubleshooting](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md). Peer matrix and D-020 details: [package README](../README.md).

## Related

- [Package README](../README.md) — install, peers, durable stores, D-020
- [CHANGELOG](../CHANGELOG.md)
- Messaging sibling: [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging)
- Product CLI: [rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli)
- Concepts (monorepo): [concepts.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/concepts.md)
