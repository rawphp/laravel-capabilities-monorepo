# Laravel Capabilities (monorepo)

> **Status:** monorepo **unit-complete design** (v0.1–v0.5 surfaces largely covered by package unit tests) — **not a stable public API**, **not published on Packagist**.  
> **Spec:** [docs/spec.md](docs/spec.md) · **Versioning & install:** [docs/versioning.md](docs/versioning.md)  
> Unit-green ≠ shipped product: treat path/VCS install + package CHANGELOGs as pre-release readiness, not Packagist release.

Product capability bus for Laravel: define once, expose via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

## Consumer readiness (residuals)

Honest picture of what the monorepo has vs what a production consumer still lacks. Markers match code and docs at this commit — not marketing “stable.”

| Area | State | Notes |
|---|---|---|
| **Packaging / Packagist publish** | residual | Install via monorepo **path** repository or VCS only; no Packagist listing or signed binary releases |
| **Release notes** | done | Per-package [CHANGELOG](packages/laravel-capabilities/CHANGELOG.md) + [docs/versioning.md](docs/versioning.md) (0.x pre-stable policy) |
| **First-capability tutorial** | residual | No guided “define your first capability” path (fluent vs attribute vs multi-surface) yet |
| **D-020 helpers** (`assertSchemaSnapshot`, `assertParity`) | done | Full unit-path DX: durable input+output schema snapshots; multi-surface success/deny class parity via registry/adapters with mocks/fakes — **not** a live multi-surface HTTP/feature suite |
| **Live peer CI** (`laravel/ai`, `laravel/mcp`) | residual | Default package CI is unit-only (matrix + contract fixtures). Live peer minors remain an optional **consumer-app** path (D-011) |

## Packages

| Path | Composer / artifact | Role | Changelog |
|---|---|---|---|
| `packages/laravel-capabilities` | `rawphp/laravel-capabilities` | Core bus: registry, schema, HTTP, AI/MCP/job adapters, approval, audit, scope, idempotency, conversation contracts | [CHANGELOG](packages/laravel-capabilities/CHANGELOG.md) |
| `packages/laravel-capabilities-messaging` | `rawphp/laravel-capabilities-messaging` | Sibling: Telegram (then Slack/…); webhooks, identity, threads — **not in core** (D-007) | [CHANGELOG](packages/laravel-capabilities-messaging/CHANGELOG.md) |
| `packages/capabilities-cli` | `rawphp/capabilities-cli` (Go) | Downloadable client: auth + catalog + run + MCP stdio (D-016) | [CHANGELOG](packages/capabilities-cli/CHANGELOG.md) |

### Install today (path / VCS)

Packages are **not** on Packagist yet. Use a monorepo **path** repository or VCS require — full policy, 0.x caveats, and Composer `branch-alias` notes: **[docs/versioning.md](docs/versioning.md)**.

Intended end-state (after publish) **on the server**:

```bash
composer require rawphp/laravel-capabilities
# optional peers
composer require laravel/ai laravel/mcp
# optional conversation surfaces
composer require rawphp/laravel-capabilities-messaging
```

Install the **CLI** on the user’s machine (not the server) — binary name: `capabilities` (build from `packages/capabilities-cli` until binary releases exist).

## Layout

```text
packages/
  laravel-capabilities/           # core PHP package (+ tests/Unit, phpunit.xml, CHANGELOG.md)
  laravel-capabilities-messaging/ # messaging PHP package (+ tests/Unit, phpunit.xml, CHANGELOG.md)
  capabilities-cli/               # Go product CLI (+ *_test.go, CHANGELOG.md)
docs/
  spec.md
  versioning.md                   # 0.x policy, path/VCS install, changelog locations
  requirements-inventory.md       # complete contract checklist (happy/fail/edge)
tools/
  generate_requirement_stubs.py   # regenerates inventory + package unit stubs 1:1
```

Tests live **inside each package**, not at the monorepo root. The inventory and stubs are the contract scaffold; implemented unit tests are the behavioural source of truth for monorepo design — they do **not** prove Packagist readiness or a frozen public API.

```bash
python3 tools/generate_requirement_stubs.py   # after extending the catalog
composer test              # core + messaging unit suite
composer test:core
composer test:messaging
composer test:cli          # requires Go
```

See **Package layout** and **Roadmap (indicative)** in `docs/spec.md` for the full `src/` map and phase vs residual status.
