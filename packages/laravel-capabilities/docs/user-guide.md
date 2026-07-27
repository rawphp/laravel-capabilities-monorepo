# Core package: rawphp/laravel-capabilities

> Ships with the **laravel-capabilities** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Define a product capability once (schema, authorization, `run`, optional approval and audit) and expose it through agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

**Namespace:** `Rawphp\Capabilities\`  
**Status:** 0.x pre-stable, path or package-repo VCS install (not Packagist-published)

Peer matrix, durable persistence, and D-020 detail live in the [package README](../README.md). Full design oracle (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md).

## Before you start

- Install via package VCS or monorepo path — see [README install](../README.md#install)
- PHP ^8.2, Laravel 11/12 illuminate components as declared in package `composer.json`
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
| MCP | `surfaces.mcp` | Needs `laravel/mcp`; auth profile settings under `surfaces.mcp.auth` |
| HTTP | `surfaces.http` | Default prefix `capabilities`; middleware `api`, `auth:sanctum` |
| CLI | `surfaces.cli` | Marks capabilities available to product CLI callers |
| Job | `surfaces.job` | Queue/job invokes need an explicit actor (not “null user = allow”) |
| Artisan | `surfaces.artisan` | Optional **in-server** ops — not the downloadable product CLI |
| Messaging | `surfaces.messaging` | Conversation channel flag; implementation is the **sibling** package |

A capability’s `->surfaces([...])` list only **narrows** what global config already allows.

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
| Idempotency | `enabled`, `driver` (default `memory`), `header` (`Idempotency-Key`) | Safe retries |
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

When agent or MCP is enabled and the peer is missing or `supportsInstalledPeer() === false`:

| `on_incompatible` | Behaviour |
|---|---|
| `fail` (default) | Boot exception — surface does not register |
| `disable` | Soft-disable + CRITICAL log + health `disabled_incompatible` |

**Never half-register tools.** Default package CI does not install live peers; honesty is matrix + unit contract fixtures. Live peer exercise is an optional **consumer app** path.

Maintainer filters: see [package README — Peer support](../README.md#peer-support--d-011-release-gate).

## Approval and idempotency (operator view)

- **Approval:** definitions may require approval before `run()` finishes. HTTP accept/reject routes are on the capability prefix. Notifier contracts allow CLI/HTTP/Telegram-style prompts; messaging package supplies conversation-side notify implementation.
- **Idempotency:** when enabled and the definition uses it, repeated keys replay stored outcomes instead of double-applying. CLI always sends a key on `run`.

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
