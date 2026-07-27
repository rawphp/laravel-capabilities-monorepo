# Laravel Capabilities — documentation

> **Status:** 0.x pre-stable monorepo design. Packages are **not** on Packagist. Unit-green ≠ shipped product or frozen public API.

Task-based **user guides** live here alongside design docs and tutorials. Start with [Getting started](getting-started.md) if you are wiring the bus into an app.

## User guides

| Page | Job |
|---|---|
| [Getting started](getting-started.md) | Install path/VCS → first capability → optional messaging → optional CLI |
| [Concepts](concepts.md) | Mental model: bus, surfaces, `run()`, profiles, approval, idempotency, messaging, CLI |
| [Core package](packages/laravel-capabilities.md) | Define, invoke, surfaces, config, peers, D-020 helpers |
| [Messaging package](packages/laravel-capabilities-messaging.md) | Telegram sibling: webhooks, identity, agent profile |
| [Product CLI](packages/capabilities-cli.md) | Build `capabilities`, auth, catalog, run, MCP stdio |
| [Troubleshooting](troubleshooting.md) | Boot, peers, auth, CLI, messaging failures |

## Tutorials

| Page | Job |
|---|---|
| [First capability](tutorials/first-capability.md) | End-to-end: DTOs, fluent + attribute define, registry invoke, HTTP, CI helpers |

## Design (not day-to-day how-tos)

| Page | Role |
|---|---|
| [spec.md](spec.md) | Design oracle: philosophy, pipeline, decisions D-002–D-023, layout, roadmap |
| [versioning.md](versioning.md) | 0.x policy, path/VCS install, branch-alias, Packagist checklist (human) |
| [requirements-inventory.md](requirements-inventory.md) | Contract checklist (happy / fail / edge) for package unit tests |

## Packages (code + short READMEs)

| Path | Artifact |
|---|---|
| [`packages/laravel-capabilities`](../packages/laravel-capabilities/) | Composer `rawphp/laravel-capabilities` |
| [`packages/laravel-capabilities-messaging`](../packages/laravel-capabilities-messaging/) | Composer `rawphp/laravel-capabilities-messaging` |
| [`packages/capabilities-cli`](../packages/capabilities-cli/) | Go module + binary `capabilities` |

Per-package changelogs live next to each package. Monorepo map and readiness residuals: [root README](../README.md).

## How to read this set

1. **New integrator** → [Getting started](getting-started.md) → [First capability](tutorials/first-capability.md)  
2. **Need the model** → [Concepts](concepts.md)  
3. **Deep package work** → package user guide → package README (peers / D-020 / layout) → [spec.md](spec.md) when behaviour is ambiguous  
4. **Something broke** → [Troubleshooting](troubleshooting.md)

Design docs are kept as the oracle. User guides summarize verified behaviour; they do not replace `spec.md`.
