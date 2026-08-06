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

Builder highlights (non-exhaustive): `description`, `surfaces`, `input`, `output`, `aliases`, deprecation fields, `groups`, `tags`, `idempotent`, `authorize`, `run`, approval-related setters, `register`.

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
- Domain must not collide with reserved CLI meta-commands (`auth`, `catalog`, `describe`, `run`, `mcp`, `approvals`, `version`, `help`)
- Two definitions claiming the same `(domain, verb)` → register/boot failure (server authoritative)
- Omit `cli` when unmapped — entry stays valid; clients use `run <name>` only
- `cli` is **routing only** — JSON Schema remains the sole input/output contract


### Attribute + discovery (canonical path)

Place classes under `config('capabilities.path')` (default `app/Capabilities`) with `#[Rawphp\Capabilities\Attributes\Capability]` implementing `Rawphp\Capabilities\Contracts\DefinesCapability`. Boot discovery runs through the service provider — do not invent a third registration mechanism.

Full teaching sample (monorepo): [First capability tutorial](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/tutorials/first-capability.md).

### Input / output DTOs

Extend `Rawphp\Capabilities\Support\CapabilityData`. Use `#[Field(...)]` attributes for JSON Schema-facing constraints. Schema on the wire comes from types (portable for CLI/catalog), not from Laravel rule strings alone.

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
]);

if ($result->ok) {
    // success payload in $result->data
}

$result->assertOk(); // throws on deny/fail
```

Facade: `Capability::invoke(...)` when the container binding is present.

For unit tests without a full app boot, construct `CapabilityRegistry` with fakes (in-memory approval/idempotency/audit) as the package suite does.

## Surfaces

Global switches live in published `config/capabilities.php` under `surfaces.*`:

| Surface | Config key | Notes |
|---|---|---|
| Agent | `surfaces.agent` | Needs `laravel/ai` when enabled + `require_package` |
| MCP | `surfaces.mcp` | **Product MCP (server):** needs `laravel/mcp`; named profiles; optional `auto_register` (default true) via `McpServerRegistrar`; auth under `surfaces.mcp.auth` |
| HTTP | `surfaces.http` | Default prefix `capabilities`; middleware `api`, `auth:sanctum` — also the transport the product CLI uses |
| CLI | `surfaces.cli` | Marks capabilities available to product CLI **HTTP** callers (not an MCP bridge) |
| Job | `surfaces.job` | Queue/job invokes need an explicit actor (not “null user = allow”) |
| Artisan | `surfaces.artisan` | Optional **in-server** ops — not the downloadable product CLI |
| Messaging | `surfaces.messaging` | Conversation channel flag; implementation is the **sibling** package |

A capability’s `->surfaces([...])` list only **narrows** what global config already allows.

### Product MCP vs product CLI

| | **Product MCP** | **Product CLI** |
|---|---|---|
| Where | Server (`laravel/mcp` + this package) | Laptop binary `capabilities` |
| How tools appear | Boot **plans** servers from `surfaces.mcp.profiles` / `servers` (`auto_register`) and may call `McpToolAdapter::register`; host still wires peer MCP routes (e.g. `Mcp::web` / peer docs) or uses manual `Capability::mcpTools(profile: …)` | HTTP `catalog` / `run` / domain verbs only |
| Hosts | Cursor, Claude Desktop, other MCP clients → **app** MCP endpoints the **host** mounts (plan rows include a planned `path` under `path_prefix`, default `/mcp/{profile}` — not a live auto-mount by this package) | Shell agents / humans over the capability HTTP API |
| Not | The CLI binary | An MCP stdio server — `capabilities mcp` was **removed** |

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

Product CLI is a remote client of **this** API. Do not add a second invoke controller tree.

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
| Agent/MCP profiles | `surfaces.*.profiles`, `require_profile`, tool count limits | Never dump full catalog by default |
| Peer mismatch | `on_incompatible` → `fail` \| `disable` | Boot fail vs soft-disable |
| HTTP | `prefix`, `middleware` | Route mount and auth |
| Approval | `store`, `ttl_hours`, `execution`, `resume.*` | Human-in-the-loop |
| Idempotency | `enabled`, `driver` (default `database`; use `memory` only for single-process tests), `header` (`Idempotency-Key`) | Safe retries |
| Audit | `enabled`, `mode` (`best_effort`), `driver` | Observability of invokes |
| Rate limits | `defaults.per_minute`, per-capability, agent turn max tools | Abuse control |
| Clients | `token_abilities` (e.g. `capabilities:cli` → `cli`), privilege order | Caller derivation |
| Peers | `peers.support` | Mirrors `PeerSupportMatrix` |

Env knobs used in the scaffold include `CAPABILITIES_SURFACE_*`, `CAPABILITIES_*_ON_INCOMPATIBLE`, `CAPABILITIES_AUDIT_MODE`, `CAPABILITIES_IDEMPOTENCY_DRIVER`, and related flags — see the published config file for the full list.

## Profiles

Agent and MCP tool exposure uses **profiles** (D-008). Configure named profile → capability name lists under the surface config. Selectors also understand forms such as `groups:…`, `only:…`, and `profile:…` via `ProfileSelector`.

Rules of thumb:

- Profiles limit **discovery** of tools.
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

1. Define named **profiles** under `surfaces.mcp.profiles` (capability name lists — D-008), or explicit `servers` rows.
2. Leave **`auto_register` true** (default): production boot builds a **server plan** via `McpServerRegistrar` and may call `McpToolAdapter::register` for each planned profile. That loads profile tools on the adapter and returns planned server definitions (name, profile, planned `path`, tools).
3. Production `bootMcpServers()` does **not** push those definitions into `laravel/mcp` (there is no peer sink analogous to `HttpRouteRegistrar::registerInto`). Integrators still **host-wire** peer MCP servers themselves (e.g. `Mcp::web` / peer docs) using the planned tools/profiles (or manual `Capability::mcpTools`).
4. Planned paths use `path_prefix` (default `/mcp`) only as plan metadata (`/mcp/{profile}`). Clients reach whatever routes the **host** actually mounts — not a package live auto-mount at `path_prefix`.
5. **Multi-profile residual:** sequential `adapter->register` overwrites the adapter’s active profile/tools (**last profile wins**). For multiple live MCP servers, wire each peer server with its own tool set rather than relying on a single shared adapter state after multi-profile boot.
6. Set `auto_register` false when you want no plan/register loop at boot and will select tools only via your own host wiring.

Maintainer filters: see [package README — Peer support](../README.md#peer-support--d-011-release-gate).

## Approval and idempotency (operator view)

- **Approval:** definitions may require approval before `run()` finishes. HTTP accept/reject routes are on the capability prefix. Notifier contracts allow CLI/HTTP/Telegram-style prompts; messaging package supplies conversation-side notify implementation.
- **Idempotency:** when enabled and the definition uses it, repeated keys replay stored outcomes instead of double-applying. CLI always sends a key on `run`.

**Telegram approval notifiers (upgrade):** For in-memory recording doubles (tests/fakes — **no** Bot API in core), use `RecordingTelegramApprovalNotifier` (`Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier`). Core still ships a **deprecated soft-landing** empty subclass `TelegramApprovalNotifier` of that recording double (still loadable; recording-only). Production Telegram Bot API delivery is the **messaging** package FQCN `Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier` — a different class, unchanged by this rename. Full consumer impact: package [CHANGELOG](../CHANGELOG.md) Unreleased **Breaking** and [README](../README.md) Telegram notifier / sibling notes. Pre-stable monorepo design surface — not a Packagist-stable API claim; soft-landing remains until a later removal.

Deep state machine detail (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md).

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

## If something goes wrong

Common boot, peer, and HTTP failures (monorepo): [Troubleshooting](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md). Peer matrix and D-020 details: [package README](../README.md).

## Related

- [Package README](../README.md) — install, peers, durable stores, D-020
- [CHANGELOG](../CHANGELOG.md)
- Messaging sibling: [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging)
- Product CLI: [rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli)
- Concepts (monorepo): [concepts.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/concepts.md)
