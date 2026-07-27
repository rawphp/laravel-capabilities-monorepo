# Laravel Capabilities (monorepo)

> **Status:** monorepo scaffold / 0.x pre-stable — **not published on Packagist**.  
> **Spec:** [docs/spec.md](docs/spec.md) · **Versioning & install:** [docs/versioning.md](docs/versioning.md)

Product capability bus for Laravel: define once, expose via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

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

Tests live **inside each package**, not at the monorepo root. The inventory and stubs are the contract scaffold; implemented tests become the source of truth for product behaviour.

```bash
python3 tools/generate_requirement_stubs.py   # after extending the catalog
composer test              # core + messaging unit todos
composer test:core
composer test:messaging
composer test:cli          # requires Go
```

See **Package layout (planned)** in `docs/spec.md` for the full `src/` map.
