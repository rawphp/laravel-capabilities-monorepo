# Laravel Capabilities (monorepo)

> **Status:** monorepo **unit-complete design** (v0.1–v0.5 surfaces largely covered by package unit tests) — **not a stable public API**, **not published on Packagist**.  
> **User docs:** [docs/README.md](docs/README.md) · **Spec:** [docs/spec.md](docs/spec.md) · **Versioning & install:** [docs/versioning.md](docs/versioning.md)  
> Unit-green ≠ shipped product: treat path/VCS install + package CHANGELOGs as pre-release readiness, not Packagist release.

Product capability bus for Laravel: define once, expose via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

## How this monorepo ships

Development happens here. On every push to `main` (and every monorepo tag `v*`), [`.github/workflows/split-packages.yml`](.github/workflows/split-packages.yml) mirrors each package tree into its **own public repo** (package root = repo root). Packagist and consumer VCS installs target those package repos — not this monorepo.

**Cut a release locally:** [`scripts/release.sh`](scripts/release.sh) (quality gates → optional squash → annotated `v*` tag → push). Agent command: `/release`. Details: [docs/versioning.md](docs/versioning.md#local-release-command-maintainer).

| Monorepo path | Public package repo | Artifact |
|---|---|---|
| `packages/laravel-capabilities` | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) | Composer `rawphp/laravel-capabilities` |
| `packages/laravel-capabilities-messaging` | [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) | Composer `rawphp/laravel-capabilities-messaging` |
| `packages/laravel-capabilities-ai` | [rawphp/laravel-capabilities-ai](https://github.com/rawphp/laravel-capabilities-ai) | Composer `rawphp/laravel-capabilities-ai` |
| `packages/capabilities-cli` | [rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli) | Go module + binary `capabilities` |

**Docs split the same way:** monorepo-only design/install guides live under [`docs/`](docs/). Package-facing guides live under `packages/*/docs/` and ship with each split repo. Do not link package READMEs into monorepo-only paths with relative `../../docs/` URLs — those break after split.

## Documentation

Start at the index (install, concepts, package guides, design, troubleshooting):

**[docs/README.md](docs/README.md)**

## Consumer readiness (residuals)

Honest picture of what the monorepo has vs what a production consumer still lacks. Markers match code and docs at this commit — residual-driven honesty only (not a marketing score or “stable” claim). **Packagist remains residual until a human completes publish**; closed monorepo gaps below do not change that.

| Area | State | Notes |
|---|---|---|
| **Registry factory (`makeRegistry`) config wiring** | done | `ContainerBindings::makeRegistry` applies config and injects approval store, idempotency store, audit settings, and scope resolver; SP registry / `ApprovalManager` / `IdempotencyStore` share store instances (no bare SystemClock-only factory) |
| **Durable persistence / TableGateway** | done | First-party `QueryTableGateway` for database drivers (`approval.store`, `idempotency.driver` + connection keys); publish `capabilities-migrations`; host override docs in [tutorial](docs/tutorials/first-capability.md) + [core README](packages/laravel-capabilities/README.md#durable-persistence-querytablegateway). Unit-tested with fakes — not a live-DB feature suite |
| **Release prep (0.x metadata)** | done | Branch-alias / 0.x-dev policy, CHANGELOG scaffolds, tag naming in [docs/versioning.md](docs/versioning.md); per-package [CHANGELOG](packages/laravel-capabilities/CHANGELOG.md). Prep only — **does not** mean tagged or Packagist-published |
| **Packaging / Packagist publish** | residual | **Residual until human completes** the Packagist + git tag publish **checklist** in [docs/versioning.md](docs/versioning.md#packagist--git-tag-publish-checklist-human-steps) (submit package repos, webhook, first tag, `composer show` / clean `composer require`). Install today via monorepo **path** or package-repo **VCS**. CLI is **not** Packagist: **unsigned** multi-arch GitHub Releases on `rawphp/capabilities-cli` are automated after monorepo `v*` tag + split; **signed** binaries residual only until child-repo signing secrets are configured |
| **First-capability tutorial** | done | [docs/tutorials/first-capability.md](docs/tutorials/first-capability.md) — path/VCS install, fluent define + attribute alternate, durable stores (`QueryTableGateway` / host `TableGateway` override), registry invoke, HTTP, D-020 helpers |
| **D-020 helpers** (`assertSchemaSnapshot`, `assertParity`) | done | Full unit-path DX: durable input+output schema snapshots; multi-surface success/deny class parity via registry/adapters with mocks/fakes — **not** a live multi-surface HTTP/feature suite |
| **Live peer CI** (`laravel/ai`, `laravel/mcp`) | residual | Default package CI is unit-only (matrix + contract fixtures). Live peer minors remain an optional **consumer-app** path (D-011) |
| **Split remotes** | done (workflow) | Push to monorepo `main` / tags mirrors package trees; consumer-facing repos are the three package remotes above |

### Install today

- **Contributors:** path-require from this monorepo clone — [docs/versioning.md](docs/versioning.md).
- **App integrators:** VCS-require the **package** repos (or path the monorepo package dirs). Public Packagist `composer require` is still residual.

Intended end-state (after Packagist publish) **on the server**:

```bash
composer require rawphp/laravel-capabilities
# optional peers
composer require laravel/ai laravel/mcp
# optional conversation surfaces
composer require rawphp/laravel-capabilities-messaging
# optional AI turns
composer require rawphp/laravel-capabilities-ai
```

Install the **CLI** on the user’s machine (not the server) — binary name: `capabilities`. Prefer [GitHub Releases](https://github.com/rawphp/capabilities-cli/releases) after a monorepo `v*` tag + split; or build from `packages/capabilities-cli` / `rawphp/capabilities-cli`.

## Layout

```text
packages/
  laravel-capabilities/           # → github.com/rawphp/laravel-capabilities
  laravel-capabilities-messaging/  # → github.com/rawphp/laravel-capabilities-messaging
  laravel-capabilities-ai/         # → github.com/rawphp/laravel-capabilities-ai
  # (messaging line continued below — fix) # → github.com/rawphp/laravel-capabilities-messaging
  capabilities-cli/               # → github.com/rawphp/capabilities-cli
docs/                             # monorepo-only: design, install, tutorials, inventory
  README.md                       # documentation index
  getting-started.md
  concepts.md
  troubleshooting.md
  versioning.md                   # 0.x, path/VCS, split remotes, Packagist checklist
  tutorials/first-capability.md
  spec.md
  requirements-inventory.md
.github/workflows/split-packages.yml
tools/                            # inventory + stub generators
```

Tests live **inside each package**, not at the monorepo root. The inventory and stubs are the contract scaffold; implemented unit tests are the behavioural source of truth for monorepo design — they do **not** prove Packagist readiness or a frozen public API.

```bash
python3 tools/generate_requirement_stubs.py   # after extending the catalog
composer test              # core + messaging unit suite
composer test:core
composer test:messaging
composer test:ai
composer test:cli          # requires Go
```

See **Package layout** and **Roadmap (indicative)** in `docs/spec.md` for the full `src/` map and phase vs residual status.
