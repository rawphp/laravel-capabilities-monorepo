# First capability tutorial

Define one product operation once, register it on the bus, invoke it through the registry, and lock it with D-020 test helpers.

This walkthrough is for **app integrators**. Packages are **not** on Packagist yet; the public API is **0.x pre-stable**. Install via monorepo **path** or **package-repo VCS** — see [docs/versioning.md](../versioning.md) and the root [README readiness residuals](../../README.md#consumer-readiness-residuals).

Samples use real namespaces from the core package (`Rawphp\Capabilities\…`). They are teaching skeletons, not a feature/DB suite.

---

## 1. Install

### Package-repo VCS (integrators)

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities"
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "dev-main"
  }
}
```

### Monorepo path (contributors)

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-capabilities-monorepo/packages/laravel-capabilities",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "*@dev"
  }
}
```

```bash
composer update rawphp/laravel-capabilities
```

Full policy, branch-alias, split remotes, and 0.x caveats: **[docs/versioning.md](../versioning.md)**. Do not pretend public Packagist `composer require rawphp/laravel-capabilities` works until that residual is closed.

Auto-discovery loads `Rawphp\Capabilities\CapabilitiesServiceProvider`. Publish config when you need to override defaults:

```bash
php artisan vendor:publish --tag=capabilities-config
```

Default discovery path is `app/Capabilities` (`config/capabilities.php` → `path`).

### Durable stores (approvals / idempotency)

When you enable **database** drivers, the package uses a first-party `Rawphp\Capabilities\Persistence\QueryTableGateway` (Illuminate query builder / connection — not Eloquent models) as the **default** `TableGateway` for each store table. There is **no** silent `ArrayTableGateway` fallback on the database path: missing connection fails closed.

| Config key | Role | Package default |
|---|---|---|
| `approval.store` | Approval store driver (`memory` / `database` / aliases) | `database` |
| `approval.connection` | Optional Illuminate connection name for approvals | `null` → app default (`db`) |
| `idempotency.driver` | Idempotency store driver | `database` (aligned with approval; set `memory` only for single-process tests) |
| `idempotency.connection` | Optional connection name for idempotency | `null` → app default |

Env mirrors: `CAPABILITIES_APPROVAL_CONNECTION`, `CAPABILITIES_IDEMPOTENCY_DRIVER`, `CAPABILITIES_IDEMPOTENCY_CONNECTION`.

**Migrations** (tables `capabilities_approvals`, `capabilities_idempotency`, `capabilities_audit_outbox` — see `MigrationCatalog`):

```bash
php artisan vendor:publish --tag=capabilities-migrations
php artisan migrate
```

For production durability: keep the package defaults (`approval.store` = `database`, `idempotency.driver` = `database`). The service provider builds a **per-table** `QueryTableGateway` for each store from the resolved connection.

**Host override** (custom gateway, or `ArrayTableGateway` for unit isolation). Bind `TableGateway` before the package factories run — a host binding wins over the QueryTableGateway construction path:

```php
// app/Providers/AppServiceProvider.php — register()
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\TableGateway;

public function register(): void
{
    // Optional: override the package QueryTableGateway default for database drivers.
    // Unbound = QueryTableGateway per table (capabilities_approvals / capabilities_idempotency).
    $this->app->singleton(TableGateway::class, fn () => new ArrayTableGateway);
    // Or: $this->app->singleton(TableGateway::class, fn () => new App\Persistence\MyGateway(...));
}
```

Prefer leaving `TableGateway` **unbound** in production so dual-table QueryTableGateway wiring stays correct. A single host-bound gateway is shared by both database stores when present.

---

## 2. Define input / output DTOs

Wire shapes are package-native DTOs extending `Rawphp\Capabilities\Support\CapabilityData`. JSON Schema for catalog/tools/CLI is derived from the types (D-015). Laravel rule strings are **server enrichment only**, not the portable schema source of truth.

```php
<?php

namespace App\Capabilities;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class CreateInvoiceInput extends CapabilityData
{
    public function __construct(
        #[Field(description: 'Customer id within the active tenant', minimum: 1)]
        public int $customer_id,
        #[Field(minimum: 1)]
        public int $amount_cents,
        #[Field(minLength: 3, maxLength: 3)]
        public string $currency,
        #[Field(maxLength: 500)]
        public ?string $memo = null,
    ) {}
}

final class CreateInvoiceResult extends CapabilityData
{
    public function __construct(
        public int $invoice_id,
    ) {}
}
```

---

## 3. Primary path: fluent `Capability::define`

For a first capability, use the **fluent** builder (`Rawphp\Capabilities\Capability::define`). It builds the same `CapabilityDefinition` shape as attributes and registers explicitly on the registry (D-017 alternate path).

```php
<?php

namespace App\Providers;

use App\Capabilities\CreateInvoiceInput;
use App\Capabilities\CreateInvoiceResult;
use App\Models\Invoice;
use Illuminate\Support\ServiceProvider;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;

final class CapabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var CapabilityRegistry $registry */
        $registry = $this->app->make(CapabilityRegistry::class);
        // Or: $this->app->make('capabilities.registry')
        // Facade: Rawphp\Capabilities\Facades\Capability

        Capability::define('create-invoice')
            ->description('Create an invoice for a customer.')
            ->surfaces(['agent', 'mcp', 'http', 'cli', 'job'])
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->groups(['billing'])
            ->idempotent('optional')
            ->authorize(function (CreateInvoiceInput $input, CapabilityContext $ctx): bool {
                $user = $ctx->user();
                if ($user === null) {
                    return false;
                }

                // Re-resolve under tenant — never trust client ids alone (D-003)
                $customer = $ctx->scope()
                    ->query(\App\Models\Customer::class)
                    ->whereKey($input->customer_id)
                    ->first();

                return $customer !== null
                    && $user->can('create', Invoice::class);
            })
            ->run(function (CreateInvoiceInput $input, CapabilityContext $ctx): CreateInvoiceResult {
                $customer = $ctx->scope()
                    ->query(\App\Models\Customer::class)
                    ->whereKey($input->customer_id)
                    ->firstOrFail();

                $invoice = Invoice::query()->create([
                    'tenant_id' => $ctx->tenantId(),
                    'customer_id' => $customer->id,
                    'amount_cents' => $input->amount_cents,
                    'currency' => $input->currency,
                    'memo' => $input->memo,
                    'created_via' => $ctx->caller(),
                ]);

                return new CreateInvoiceResult(invoice_id: $invoice->id);
            })
            ->register($registry);
    }
}
```

Key rules:

- **One `run()`** — domain mutation lives here (or in an app action the closure calls). Surfaces must not open a second write path.
- **`authorize` before `run`** — deny never reaches domain code.
- **Surfaces only narrow** global `config/capabilities.php` flags; they do not invent channels.
- Caller / tenant are **server-derived** (D-022 / D-003), not taken from untrusted wire claims.

Register the provider in `bootstrap/providers.php` (Laravel 11+) if it is not auto-discovered.

---

## 4. Alternate path: `#[Capability]` (canonical discovery)

D-017’s **canonical** form is a class with `#[Rawphp\Capabilities\Attributes\Capability]` implementing `Rawphp\Capabilities\Contracts\DefinesCapability`, auto-discovered under `config('capabilities.path')` (default `app/Capabilities`).

```php
<?php

namespace App\Capabilities;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Support\CapabilityContext;

#[Capability(
    name: 'create-invoice',
    description: 'Create an invoice for a customer.',
    surfaces: ['agent', 'mcp', 'http', 'cli'],
    input: CreateInvoiceInput::class,
    output: CreateInvoiceResult::class,
)]
final class CreateInvoice implements DefinesCapability
{
    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        // same rules as the fluent authorize callback
        return true;
    }

    public function run(CreateInvoiceInput $input, CapabilityContext $ctx): CreateInvoiceResult
    {
        // same single run() as the fluent path
        return new CreateInvoiceResult(invoice_id: 1);
    }
}
```

Boot discovery runs via `Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot` / the service provider — put classes on the configured path; do not invent a third registration mechanism. Prefer **one** style per capability (attribute **or** fluent), never both for the same name.

Full design: [docs/spec.md — Defining a capability](../spec.md#defining-a-capability).

---

## 5. Invoke via the registry

Every surface ends at `CapabilityRegistry::invoke` (or the facade). Adapters are dumb; the registry is the choke point.

```php
use Rawphp\Capabilities\Facades\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;

/** @var CapabilityRegistry $registry */
$registry = app(CapabilityRegistry::class);

$result = $registry->invoke('create-invoice', [
    'customer_id' => 1,
    'amount_cents' => 2500,
    'currency' => 'USD',
], [
    // caller is normally set by the adapter (http, agent, mcp, cli, job, …)
    'caller' => 'http',
    // actor / tenant / scope come from trusted request or job context — not client claims
]);

// Same entry via facade when the container is bound:
// $result = Capability::invoke('create-invoice', $input, $options);

/** @var CapabilityResult $result */
if ($result->ok) {
    // $result->data holds CreateInvoiceResult (or wire-shaped data)
}

// Or fail closed in tests / call sites:
$result->assertOk(); // throws CapabilityResultAssertionException on deny/fail
```

For unit tests without a full app boot, construct `CapabilityRegistry` with fakes (in-memory approval/idempotency/audit stores) as the package suite does — see monorepo AGENTS.md testing policy.

---

## 6. HTTP surface (already present)

When `config('capabilities.surfaces.http.enabled')` is true (default), the package exposes a **single** capability HTTP tree (`Rawphp\Capabilities\Http\RouteTable` → `CapabilityController`). Default prefix: `capabilities`.

| Method | URI | Role |
|---|---|---|
| `GET` | `/capabilities` | Catalog list |
| `GET` | `/capabilities/{name}` | Describe one capability |
| `POST` | `/capabilities/{name}` | **Invoke** (same registry `run()` as code) |
| `GET` | `/capabilities/health` | Health |

Example:

```http
POST /capabilities/create-invoice
Authorization: Bearer …
Content-Type: application/json

{
  "customer_id": 1,
  "amount_cents": 2500,
  "currency": "USD"
}
```

Do not add a second HTTP invoke tree for CLI or integrations (D-009). The product CLI is a remote HTTP client against this API (`caller: cli`). Auth middleware defaults include `api` + `auth:sanctum` — override in published config as needed.

---

## 7. Lock it in CI (D-020 helpers)

D-020 helpers are **real unit-path DX** on the registry and facade (not presence-only stubs). Full API notes: package README [Testing helpers (D-020)](../../packages/laravel-capabilities/README.md#testing-helpers-d-020).

| Helper | Role |
|---|---|
| `assertSchemaSnapshot` | Lock **input_schema + output_schema**; durable `.schema.json` file, conventional directory, or in-memory envelope; fail on drift |
| `assertParity` | Same input → same success/deny **class** across **listed** `surfaces` (registry/adapter unit paths with mocks/fakes) |

Facade signatures (`Rawphp\Capabilities\Facades\Capability`):

```php
// Preferred for app CI — conventional directory → create-invoice.schema.json
// (document must include input_schema + output_schema):
Capability::assertSchemaSnapshot(
    'create-invoice',
    null,
    base_path('tests/fixtures/capability-schemas'),
);

// Or absolute/relative file path as the second argument:
// Capability::assertSchemaSnapshot('create-invoice', base_path('…/create-invoice.schema.json'));

// Name-only assertSchemaSnapshot('create-invoice') does NOT lock schemas — always pass path, dir, or envelope.

Capability::assertParity('create-invoice', [
    'input' => [
        'customer_id' => 1,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ],
    'surfaces' => ['http', 'registry', 'job'], // required non-empty; empty options rejected
    'assert' => function ($result): void {
        // optional: runs only on successful results
    },
]);
```

These exercise registry / adapter unit paths with mocks — **not** a live multi-surface HTTP/feature suite. App CI should run schema snapshots for every capability before release. Design: [docs/spec.md D-020](../spec.md#d-020--parity-tests-and-schema-snapshots-as-package-features). Package notes: [packages/laravel-capabilities/README.md](../../packages/laravel-capabilities/README.md#testing-helpers-d-020).

---

## What this tutorial does **not** cover

- Messaging (Telegram/Slack) — sibling package `rawphp/laravel-capabilities-messaging` (D-007)
- Product CLI binary install — `packages/capabilities-cli` (Go client)
- Approval state machine deep-dive, rate limits, audit modes — see [docs/spec.md](../spec.md)
- Packagist publish or a stable 1.x API
- Live multi-surface feature/DB suites — package CI remains unit-only with gateway mocks/fakes

## Next reading

| Topic | Where |
|---|---|
| Docs index / IA | [docs/README.md](../README.md) |
| Getting started (messaging + CLI) | [docs/getting-started.md](../getting-started.md) |
| Concepts | [docs/concepts.md](../concepts.md) |
| Full design & decisions | [docs/spec.md](../spec.md) |
| Install / 0.x versioning | [docs/versioning.md](../versioning.md) |
| Durable stores / TableGateway | this tutorial § Durable stores · [core README](../../packages/laravel-capabilities/README.md#durable-persistence-querytablegateway) |
| Monorepo status & residuals | [README.md](../../README.md) |
| Core package user guide | [../packages/laravel-capabilities/docs/user-guide.md](../../packages/laravel-capabilities/docs/user-guide.md) |
| Core package peer/D-011 notes | [packages/laravel-capabilities/README.md](../../packages/laravel-capabilities/README.md) |
| D-020 parity & snapshots | [spec D-020](../spec.md#d-020--parity-tests-and-schema-snapshots-as-package-features) |
| Troubleshooting | [docs/troubleshooting.md](../troubleshooting.md) |
