# rawphp/laravel-capabilities

> **Status:** core package in a **unit-complete monorepo design** — **not Packagist-published**, **0.x pre-stable** (no stable public API claim).  
> Install today via monorepo **path** / VCS — see monorepo [docs/versioning.md](../../docs/versioning.md) and root [README readiness residuals](../../README.md#consumer-readiness-residuals).

Core product capability bus for Laravel.

Define a capability once (schema, authorization, `run`, approval, audit) and expose it via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

**User documentation (monorepo):**

| Doc | Path |
|---|---|
| Docs index | [docs/README.md](../../docs/README.md) |
| Getting started | [docs/getting-started.md](../../docs/getting-started.md) |
| Core user guide | [docs/user-guide.md](docs/user-guide.md) |
| Concepts | [docs/concepts.md](../../docs/concepts.md) |
| Troubleshooting | [docs/troubleshooting.md](../../docs/troubleshooting.md) |
| First capability tutorial | [docs/tutorials/first-capability.md](../../docs/tutorials/first-capability.md) |

**Getting started:** monorepo [First capability tutorial](../../docs/tutorials/first-capability.md) (path/VCS install, fluent + attribute define, registry invoke, HTTP, D-020). Full design: [docs/spec.md](../../docs/spec.md). D-020 helpers (`assertSchemaSnapshot`, `assertParity`) are **implemented for unit-path DX** (durable schema snapshots; multi-surface success/deny class parity with mocks/fakes) — not a live multi-surface HTTP/feature suite.

## Peer support / D-011 release gate

This package composes `laravel/ai` and `laravel/mcp` as optional peers. Release honesty is **matrix + unit contract fixtures**, not live SDKs in default monorepo CI.

### Matrix location (source of truth)

| What | Where |
|---|---|
| Machine-readable matrix | [`src/Adapters/PeerSupportMatrix.php`](src/Adapters/PeerSupportMatrix.php) |
| Config mirror | `config/capabilities.php` → `peers.support` |
| Probe defaults | `PeerVersionProbe` uses the matrix (not open-ended `*`) |
| Design decision | monorepo [docs/spec.md D-011](../../docs/spec.md#d-011--peer-package-churn-laravelai--laravelmcp) |

Update `PeerSupportMatrix` whenever you add/drop a supported peer minor/major **after** contract fixtures stay green. Bumping the matrix without a green unit contract suite is a **release blocker**.

### Required unit contract suite filters

Default package CI does **not** install live `laravel/ai` or `laravel/mcp`. Contract coverage is mock/fake unit tests only.

Before shipping matrix or adapter changes, run (from monorepo root):

```bash
composer test:core -- --filter=PeerSupportMatrix
composer test:core -- --filter=PeerContract
composer test:core -- --filter=Adapter
composer test:core -- --filter=PeerReleaseGateDocs
```

Minimum intent (aligned with D-011 contract table):

| Filter / suite | Asserts |
|---|---|
| `PeerSupportMatrix` | Matrix non-empty; version match/mismatch without live peers |
| `PeerContract` | Frozen fixtures for AI/MCP tool shapes, probe classes, matrix cells |
| `Adapter` | `AdapterApi`, AI/MCP adapters, boot fail/disable paths |
| `PeerReleaseGateDocs` | This README section stays present (anchor guard) |

A matrix or adapter change without these green is **not shippable**.

### Fail / disable boot behaviour

When `agent` or `mcp` is enabled and the peer is missing or `supportsInstalledPeer() === false`:

| `on_incompatible` | Behaviour |
|---|---|
| `fail` (default) | **Boot exception** — surface does not register |
| `disable` | Soft-disable surface + **CRITICAL** log + health `disabled_incompatible` |

**Never half-register tools.** Partial tool lists on an incompatible peer are refused.

### AdapterApi bump rule

`AdapterApi` versions this package’s bridge shapes (not the peer package version).

- Bump `AdapterApi` when Tool/MCP mapping **call shapes** change (`requiresBump` is true when previous shape ≠ next shape).
- Keep `AdapterApi::CURRENT` and `supported()` in lockstep with [`PeerContractFixtures`](tests/Fixtures/PeerContractFixtures.php).
- Apps depend on stable catalog/tool surfaces; they must not hard-code adapter version selection for listing.

### Package CI policy (no live peers)

Default monorepo / package CI:

- Does **not** install live `laravel/ai` or `laravel/mcp`
- Uses unit tests with mocks/fakes only (see monorepo AGENTS.md)
- Package honesty = **PeerSupportMatrix** + **PeerContractFixtures** + adapter unit suite

Live “contract tests against real peer minors” remain **aspirational for consumer apps** that choose to install peers — not a default CI dependency of this package.

### Optional consumer peer-live checklist

Consumer applications that install real peers can run an app-owned peer-live path. This monorepo does not run it for you.

1. In the **app** (not this package’s default suite):

   ```bash
   composer require laravel/ai
   composer require laravel/mcp
   ```

2. Pin versions that fall inside the declared matrix cells in `PeerSupportMatrix` (or override `peers.support` deliberately and update your own tests).

3. Run **your** app test suite (including any invoke/agent/MCP flows you own).

4. Confirm each installed peer version still matches a supported matrix cell; if not, either pin back or open a package PR that extends the matrix **after** unit contract fixtures stay green.

5. Optionally keep a consumer-only CI job that installs peers and exercises app-level smoke tests — never required for package green.

### Maintainer release checklist (D-011)

- [ ] `PeerSupportMatrix` updated for any peer support change
- [ ] Unit contract suite filters above green
- [ ] No half-register paths introduced; fail/disable behaviour unchanged
- [ ] `AdapterApi` bumped if bridge shapes changed
- [ ] CHANGELOG / release notes list declared peer constraints
- [ ] Default package CI still free of live peer installs

---

## Testing helpers (D-020)

Consumer app CI should lock every capability’s catalog schema and, where dual-path risk matters, assert multi-surface **success/deny class** parity. Helpers live on `CapabilityRegistry` and the `Capability` facade.

**Scope honesty:** these exercise **registry / adapter unit paths** with mocks/fakes. They are **not** a live multi-surface HTTP/feature suite against real `laravel/ai` / `laravel/mcp` peers.

Full first-capability walkthrough: [docs/tutorials/first-capability.md](../../docs/tutorials/first-capability.md#7-lock-it-in-ci-d-020-helpers). Design: [docs/spec.md D-020](../../docs/spec.md#d-020--parity-tests-and-schema-snapshots-as-package-features).

### `assertSchemaSnapshot`

Locks **input_schema + output_schema** from the live catalog against a snapshot. Contract: returns `true` on match; throws `SchemaSnapshotException` on drift or missing snapshot file (names the capability and which side mismatched).

```php
use Rawphp\Capabilities\Facades\Capability;

// 1) Durable file path (recommended for app CI):
Capability::assertSchemaSnapshot(
    'create-invoice',
    base_path('tests/fixtures/capability-schemas/create-invoice.schema.json'),
);

// 2) Conventional directory → `{dir}/{name}.schema.json`:
Capability::assertSchemaSnapshot(
    'create-invoice',
    null,
    base_path('tests/fixtures/capability-schemas'),
);

// 3) In-memory envelope (unit convenience):
Capability::assertSchemaSnapshot('create-invoice', [
    'input_schema' => [/* JSON Schema */],
    'output_schema' => [/* JSON Schema */],
]);
```

Snapshot document shape:

```json
{
  "input_schema": { "type": "object", "properties": { } },
  "output_schema": { "type": "object", "properties": { } }
}
```

**Important:** `assertSchemaSnapshot('create-invoice')` with **no** path/envelope/directory only resolves the capability and returns `true` — it does **not** lock schemas. Always pass a file path, conventional directory, or envelope in CI.

App CI should run snapshots for every capability before release. Update the snapshot file intentionally when the schema change is deliberate.

### `assertParity`

Same valid (or deny-triggering) input → same **success/deny class** across listed surfaces via the registry choke point (`invoke` with surface-derived `caller`). Optional `assert` callback runs only on **successful** results.

```php
Capability::assertParity('create-invoice', [
    'input' => [
        'customer_id' => 1,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ],
    'surfaces' => ['http', 'registry', 'ai', 'job'],
    // optional shared invoke options:
    // 'actor' => $user, 'tenant_id' => 't-1', 'scope' => $scope,
    'assert' => function ($result): void {
        // runs only when the surface result is success
        expect($result->data['invoice_id'])->toBeInt();
    },
]);
```

| Option | Required | Role |
|---|---|---|
| `surfaces` | **yes** (non-empty) | Labels to invoke: `http`, `cli`, `agent`, `mcp`, `job`, `artisan`, plus aliases `ai` → agent, `registry` → http |
| `input` | recommended | Capability input array (defaults to `[]`) |
| `assert` | no | `callable(CapabilityResult): void` on success results only |
| `actor` / `tenant_id` / `scope` / `options` | no | Shared invoke context (job surface auto-fills a test job bag when missing) |

**Important:** empty options / missing `surfaces` throws `InvalidArgumentException`. There is **no** `assertParity()` no-arg form that proves multi-surface parity — you must list surfaces and supply real input for the scenario under test.

Mismatch across surfaces throws `ParityAssertionException` naming the capability, surfaces, and result classes (`success` vs `deny`). Approval-required counts as **deny** class for parity.
