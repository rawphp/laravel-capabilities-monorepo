# Laravel Capabilities — documentation

> **Status:** 0.x pre-stable monorepo design. Packages are **not** on Packagist. Unit-green ≠ shipped product or frozen public API.

Task-based **user guides** live here alongside design docs and tutorials. Start with [Getting started](getting-started.md) if you are wiring the bus into an app.

**Product boundary:** this monorepo is a **dev umbrella** only. Consumers install **split packages**, not the monorepo. What each package is / is not (and family non-goals): root [README — Scope](../README.md#scope-product-boundary) and [Concepts](concepts.md#package-boundaries).

## Doc ownership (monorepo vs split packages)

| Layer | Lives | Ships with split repos? |
|---|---|---|
| Monorepo map, residuals, agent policy | Root [README](../README.md), [AGENTS.md](../AGENTS.md) | No |
| Install policy, concepts, troubleshooting, tutorials, design oracle | This `docs/` tree | No (monorepo only) |
| Per-package how-to | `packages/<name>/docs/user-guide.md` | **Yes** |
| Per-package entry + maintainer notes | `packages/<name>/README.md` | **Yes** |
| Per-package changelog | `packages/<name>/CHANGELOG.md` | **Yes** |

On push to monorepo `main` / tags `v*`, each `packages/*` tree is mirrored to its public repo ([workflow](../.github/workflows/split-packages.yml)). Package docs must stay **self-contained** (no relative links into monorepo-only `docs/` or the monorepo root README).

## User guides

| Page | Job |
|---|---|
| [Getting started](getting-started.md) | Install (path or package VCS) → first capability → optional messaging → optional CLI |
| [Concepts](concepts.md) | Mental model: bus, surfaces, `run()`, profiles, approval, idempotency, messaging, CLI |
| [Core package](../packages/laravel-capabilities/docs/user-guide.md) | Define, invoke, surfaces, config, peers, D-020 helpers |
| [Messaging package](../packages/laravel-capabilities-messaging/docs/user-guide.md) | Telegram sibling: webhooks, identity, agent profile |
| [AI package](../packages/laravel-capabilities-ai/README.md) | Optional turn / proposal runtime (bus-only tools) |
| [Product CLI](../packages/capabilities-cli/docs/user-guide.md) | Build `capabilities`, auth, catalog, run (HTTP only; product MCP is server-side) |
| [Troubleshooting](troubleshooting.md) | Boot, peers, auth, CLI, messaging failures |

## Tutorials

| Page | Job |
|---|---|
| [First capability](tutorials/first-capability.md) | End-to-end: DTOs, fluent + attribute define, registry invoke, HTTP, CI helpers |

## Design (not day-to-day how-tos)

| Page | Role |
|---|---|
| [spec.md](spec.md) | Design oracle: philosophy, pipeline, decisions D-002–D-023, layout, roadmap |
| [versioning.md](versioning.md) | 0.x policy, path/VCS, split remotes, branch-alias, Packagist checklist (human) |
| [requirements-inventory.md](requirements-inventory.md) | Contract checklist (happy / fail / edge) for package unit tests |

## Packages (code + package-shipped docs)

| Path | Public repo | Artifact | Boundary |
|---|---|---|---|
| [`packages/laravel-capabilities`](../packages/laravel-capabilities/) | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) | Composer `rawphp/laravel-capabilities` | [Core README — Scope](../packages/laravel-capabilities/README.md#scope-this-package) |
| [`packages/laravel-capabilities-messaging`](../packages/laravel-capabilities-messaging/) | [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) | Composer `rawphp/laravel-capabilities-messaging` | [Messaging README — Scope](../packages/laravel-capabilities-messaging/README.md#scope-this-package) |
| [`packages/laravel-capabilities-ai`](../packages/laravel-capabilities-ai/) | [rawphp/laravel-capabilities-ai](https://github.com/rawphp/laravel-capabilities-ai) | Composer `rawphp/laravel-capabilities-ai` | [AI README — Scope](../packages/laravel-capabilities-ai/README.md#scope-this-package) |
| [`packages/capabilities-cli`](../packages/capabilities-cli/) | [rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli) | Go module + binary `capabilities` | [CLI README — Scope](../packages/capabilities-cli/README.md#scope-this-package) |

Monorepo map, umbrella vs packages, readiness residuals: [root README](../README.md).

## How to read this set

1. **New integrator** → [Getting started](getting-started.md) → [First capability tutorial](tutorials/first-capability.md)  
2. **Need the model** → [Concepts](concepts.md)  
3. **Deep package work** → package user guide → package README (peers / D-020 / layout) → [spec.md](spec.md) when behaviour is ambiguous  
4. **Something broke** → [Troubleshooting](troubleshooting.md)

Design docs are the oracle. User guides summarize verified behaviour; they do not replace `spec.md`.
