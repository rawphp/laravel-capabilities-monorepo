# Laravel Capabilities (monorepo)

> **Status:** monorepo **unit-complete design** (v0.1–v0.5 surfaces largely covered by package unit tests) — **not a stable public API**, **not published on Packagist**.  
> **User docs:** [docs/README.md](docs/README.md) · **Spec:** [docs/spec.md](docs/spec.md) · **Versioning & install:** [docs/versioning.md](docs/versioning.md)  
> Unit-green ≠ shipped product: treat path/VCS install + package CHANGELOGs as pre-release readiness, not Packagist release.

Product capability bus for Laravel: define once, expose via agent, MCP, HTTP, product CLI, jobs, chat, and optional AI turns — same rules, one `run()`.

## Scope (product boundary)

### Umbrella vs packages

| Layer | What it is | What consumers install |
|---|---|---|
| **This monorepo** (`laravel-capabilities-monorepo`) | **Development umbrella only** — four packages, shared design docs, inventory tools, split/release automation | **Nothing.** Not a Composer package for apps. Not on Packagist. |
| **Split package repos** | The **products** — each `packages/*` tree mirrored to its own public repo | Path/VCS (today) or Packagist (residual) against **package** remotes |

**Rule:** if work does not fit one of the four package jobs below, it is out of scope for this family (or needs an explicit new package + split remote — not a silent dump into core).

Internal design notes and roadmaps may sketch later ideas ([docs/spec.md](docs/spec.md)). **Sketches are not the product boundary.** Until a surface is listed under a package **Is** section and shipped in that package tree, it is out of scope.

### Family job (all packages)

> Define what the product can *do* once; every channel invokes it under the same law.

Hard invariants shared across packages:

- **One `run()`** — domain mutation lives in the capability (or app code it calls); surfaces must not open a second write path.
- **Registry is the choke point** — adapters stay thin.
- **Compose official peers** — wrap `laravel/ai` / `laravel/mcp`; do not reimplement them in core.
- **CLI is a client** — no domain logic on the laptop.
- **Messaging and AI are siblings** — optional packages; core stays a thin capability bus (D-007).

### Per package: is / is not

#### 1. `rawphp/laravel-capabilities` (core)

| | |
|---|---|
| **Is** | Capability registry; typed DTO → schema; authorize / approval / audit / scope / idempotency / rate limits; thin adapters for agent, MCP, HTTP, jobs, Artisan ops surface; HTTP capability API the CLI uses; conversation **contracts** for siblings; unit-path D-020 parity helpers |
| **Is not** | LLM client or model loop product; MCP protocol server implementation; Telegram/Slack/WhatsApp bot runtime; downloadable product CLI binary; conversation turn/proposal store; chat UI / Livewire kit / SaaS template gallery; A2A multi-app mesh; replacement for controllers, Form Requests, or domain services; Artisan-as-the-product-CLI |

#### 2. `rawphp/laravel-capabilities-messaging`

| | |
|---|---|
| **Is** | Conversation **ingress** (Telegram first): webhooks, identity link/allowlist, threads (process-local today), approval notifiers; feeds the agent; tools are registry capabilities |
| **Is not** | Domain `run()` or second write path; full multi-tenant identity product (durable identity/threads still residual L-006); core bus governance; product CLI; general notification platform for non-capability flows |

#### 3. `rawphp/laravel-capabilities-ai`

| | |
|---|---|
| **Is** | Optional **conversation / turn / proposal runtime** that drives the model and may call tools **only** via `CapabilityBus::invoke`; host-bound context/tool catalog; progress events (array/Redis); thin `LlmClient` seam (fake + Anthropic) for turns and host jobs that need completions without embedding domain rules |
| **Is not** | The capability bus itself; a general-purpose LLM SDK to replace `laravel/ai` app-wide; chat channel bots (that is messaging); product CLI; a place for domain `run()`; generative UI / agent-native OS |

#### 4. `rawphp/capabilities-cli` (binary `capabilities`)

| | |
|---|---|
| **Is** | Downloadable Go HTTP client for humans and local agents: auth profiles, catalog, run, schema validate-before-send, auto idempotency against the same remote HTTP capability API |
| **Is not** | A second backend or domain runtime; Artisan; in-server ops CLI; Packagist PHP package; product MCP / MCP stdio server (use server `laravel/mcp` via core auto-register) |

### Family is not (umbrella non-goals)

Do **not** grow these into any package without an explicit product decision (and usually a new package remote):

- Agent-native / multi-app workspace runtime (A2A mesh)
- Cloneable SaaS / template gallery / Livewire chat product
- Result caches, change-based test selection, or CI platforms (wrong product line)
- Shipping chat bots or turn engines **inside core** to avoid a second Composer require
- Treating this monorepo as the install target for applications

Package READMEs restated these boundaries for post-split consumers (package root = repo root). Design depth: [docs/spec.md](docs/spec.md) · mental model: [docs/concepts.md](docs/concepts.md).

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
| **Split remotes** | done (workflow) | Push to monorepo `main` / tags mirrors package trees; consumer-facing repos are the **four** package remotes above |

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
