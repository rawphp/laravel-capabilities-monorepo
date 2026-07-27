# rawphp/laravel-capabilities

> **Status:** core package in a **unit-complete monorepo design** — **not Packagist-published**, **0.x pre-stable** (no stable public API claim).  
> Install today via monorepo **path** / VCS — see monorepo [docs/versioning.md](../../docs/versioning.md) and root [README readiness residuals](../../README.md#consumer-readiness-residuals).

Core product capability bus for Laravel.

Define a capability once (schema, authorization, `run`, approval, audit) and expose it via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

See monorepo [docs/spec.md](../../docs/spec.md). D-020 helpers (`assertSchemaSnapshot`, `assertParity`) are **implemented for unit-path DX** (durable schema snapshots; multi-surface success/deny class parity with mocks/fakes) — not a live multi-surface HTTP/feature suite.

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
