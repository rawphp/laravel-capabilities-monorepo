# Laravel Capabilities — agent instructions

Shared project instructions for all AI coding agents. **AGENTS.md is the source of truth**; vendor entry files (`CLAUDE.md`, …) only point here.

## Product / Purpose

- **Laravel Capabilities** is a **product capability bus** for Laravel apps in an agent-era world — not a chatbot, not an LLM SDK, not agent-native-with-PHP.
- Job: **one domain capability → many surfaces**, without dual mutation paths.
- A *capability* is a real product operation (create invoice, void subscription, …) defined once with name/description, schema, authorization, single `run()`, optional approval + audit — then exposed as needed.
- Surfaces: in-app agent (`laravel/ai`), MCP (`laravel/mcp`), HTTP, product CLI (downloadable client), jobs. Messaging is a **sibling package**, not core.
- Working titles: `rawphp/laravel-capabilities` (core), `rawphp/laravel-capabilities-messaging`, `rawphp/capabilities-cli` (Go).
- **Status:** monorepo **unit-complete design** (package unit suites largely cover v0.1–v0.5 scope) — **not Packagist-published**, **not a stable public API**. Spec remains the design oracle where tests are silent; unit-green ≠ shipped product. See root README readiness residuals.
- **Ship model:** develop here; on push to `main` / tags `v*`, CI mirrors each `packages/*` tree to its public package repo (`.github/workflows/split-packages.yml`). Package docs must stay **self-contained** after split (no relative links into monorepo-only `docs/`).

One sentence: *Define what the product can do once; let every agent-era channel invoke it under the same law.*

## Essential files

- `@docs/spec.md` — full design: philosophy, config, pipeline, decisions D-002–D-023, package layout, roadmap  
  **Read when:** implementing, changing architecture, debating surfaces/governance, writing APIs or adapters
- `@README.md` — monorepo map and install intent  
  **Read when:** orienting a new session
- `@packages/laravel-capabilities/` — core bus (registry, adapters, approval, audit, contracts)  
  **Read when:** core package work
- `@packages/laravel-capabilities-messaging/` — Telegram (then Slack/…); implements core contracts only  
  **Read when:** conversation surfaces
- `@packages/capabilities-cli/` — Go product CLI (HTTP client; D-016)  
  **Read when:** CLI auth/catalog/run/MCP stdio

## Monorepo packages

| Path | Package | Public remote (after split) | Owns |
|---|---|---|---|
| `packages/laravel-capabilities` | `rawphp/laravel-capabilities` | `rawphp/laravel-capabilities` | Registry, schema, HTTP API, AI/MCP/job adapters, approval SM, audit, scope, idempotency, **conversation ingress contracts** |
| `packages/laravel-capabilities-messaging` | `rawphp/laravel-capabilities-messaging` | `rawphp/laravel-capabilities-messaging` | Telegram first: webhooks, identity, threads, chat approval notifier |
| `packages/capabilities-cli` | `rawphp/capabilities-cli` | `rawphp/capabilities-cli` | Downloadable Go client: auth + catalog + run + optional MCP stdio |

Namespaces: `Rawphp\Capabilities\` (core), `Rawphp\CapabilitiesMessaging\` (messaging).

Root `composer.json` path-requires the PHP packages for local work. CLI is Go (`go.mod`), not Composer. Consumer VCS/Packagist targets are the **package remotes**, not this monorepo.

## Constraints (MUST NOT)

- Do **not** create a second code path that mutates the same business state as a capability `run()` — surfaces are adapters; registry is the choke point.
- Do **not** put Telegram/Slack/WhatsApp Bot API, identity links, or thread stores **inside core** — messaging is a sibling package (D-007). No `Messaging/` “until we extract.”
- Do **not** reimplement LLM clients or MCP wire protocol — compose `laravel/ai` and `laravel/mcp` as adapters (D-011).
- Do **not** treat Artisan as the product CLI. Product CLI is a **remote HTTP client** (`caller: cli`); Artisan is optional in-server ops only.
- Do **not** add a second HTTP invoke/controller tree for the CLI (D-009). One capability HTTP API.
- Do **not** trust client-claimed caller, tenant, or ambient identity. Caller is **server-derived** from credentials/adapters (D-022). Resource IDs are untrusted until re-resolved under scope (D-003).
- Do **not** authorize queue/job invokes as “null user = allow.” Jobs need an explicit **SystemActor** (or real user) (D-002).
- Do **not** dump the full capability catalog into agent/MCP tool lists by default — **profiles/groups only** (D-008). Meta list+run inherits the same profile.
- Do **not** register half a surface when disabled or when a required peer is missing/incompatible — fail closed or soft-disable loudly (boot rules + D-011).
- Do **not** skip server re-validation because “CLI already checked.” CLI validates portable JSON Schema for UX; server is law.
- Do **not** use Laravel rule strings as the only schema source of truth (not CLI-safe). DTOs → JSON Schema for wire/catalog.
- Do **not** make messaging call Eloquent/domain `run()` outside the registry/agent tools.
- Do **not** invent a third capability discovery path beyond class `#[Capability]` + fluent `Capability::define` (D-017).
- Do **not** commit secrets except `.env.example`; do not use `git commit --no-verify`.
- Do **not** delete or clobber `docs/spec.md` when scaffolding or refactoring.
- Do **not** add relative links from `packages/*/README.md` or `packages/*/docs/*` into monorepo-only paths (`../../docs/…`, monorepo root README) — those break after the three-package split. Use in-package paths or absolute monorepo GitHub URLs.
- Do **not** add feature tests, HTTP/browser tests, database-backed tests, or any suite that requires a real DB, Redis, queue worker, or full Laravel app boot for assertions. **Unit tests only** (see Testing).

## Testing (IMPORTANT — non-negotiable)

**These packages are unit-tested only.** There are **zero feature tests**. No database is required. Dependencies that touch IO, HTTP, DB, queues, peers, or time are **mocked or faked**.

### Hard rules

| Rule | Meaning |
|---|---|
| **Unit tests only** | Every test is a unit test (Pest/PHPUnit unit, or Go unit tests for the CLI). |
| **Zero feature tests** | No `tests/Feature`, no `Http::` end-to-end app routes, no `RefreshDatabase`, no full request lifecycle “as integration.” |
| **No database required** | Tests must pass with **no MySQL/Postgres/SQLite**, no migrations run, no schema. Do not configure a test DB for this monorepo. |
| **Mock external boundaries** | Mock/fake: Eloquent/query builders, stores (approval, idempotency, audit), HTTP clients, `laravel/ai`, `laravel/mcp`, queue/bus, filesystem, clock, config where needed. |
| **No live peers in CI for package truth** | Adapter “contract” behaviour is still **unit-tested** against mocks or narrow in-memory fakes of peer interfaces — not against a full installed peer + DB app unless explicitly revised in this file. |
| **≥95% coverage** | Line (and preferably branch) coverage for each PHP package under `packages/*/src` must be **95% or higher**. Same bar for Go CLI packages under test when CI lands. Below 95% is a **failed** task — not a warning. |

### Coverage floor (blocking)

- **Minimum: 95%** unit-test coverage on package source (`packages/laravel-capabilities/src`, `packages/laravel-capabilities-messaging/src`, and Go packages for the CLI).
- Measured with Pest/PCOV (or Xdebug) for PHP and `go test -cover` for the CLI — exact CI commands live with the test harness when added.
- Coverage is **necessary but not sufficient**: 95% of weak asserts does not pass review. Tests must exercise real behaviour (happy path + intentional edges).
- Do **not** game coverage with empty asserts, `@codeCoverageIgnore` on production logic, or shipping dead code to “raise %.”
- New code that drops the package below 95% must not be committed. Raise tests (or delete dead paths) before marking done.
- Exclude only true generated/boilerplate if CI allowlists them explicitly — default is **no exclusions** for domain/registry/adapter logic.

### What to write instead of feature tests

- Registry pipeline: inject fakes for store/validator/authorizer; assert order, early exit, and that `run()` is not called on deny/invalid.
- Controllers/adapters: call methods with mocked registry + request-like DTOs; assert mapping to registry invokes and response shaping.
- Schema: pure DTO → JSON Schema + validation of arrays/objects in memory.
- Approval/idempotency: in-memory fake stores implementing the same interfaces as production drivers.
- Messaging: mock HTTP Bot API / webhook payload objects; no real Telegram.
- Go CLI: `net/http/httptest` or interface mocks for the API client; local schema validate without a server.

### Forbidden in this repo

- `tests/Feature/**` (do not create or revive)
- `RefreshDatabase`, `DatabaseMigrations`, `DatabaseTransactions` for package tests
- `uses(Tests\TestCase::class)` patterns that boot a full app **and** hit a real DB
- Pest/PHPUnit tests that fail when `DB_*` is unset
- Requiring `orchestra/testbench` **with** a database connection to green the suite (prefer pure unit construction; if a minimal container is used, it must still be DB-free)
- “We’ll add feature tests later for confidence” — **no**. Expand unit coverage and fakes instead.

### Layout (tests live **inside each package**)

```text
packages/laravel-capabilities/
  phpunit.xml
  tests/Pest.php
  tests/Unit/…          # only allowed PHP automated tests for core

packages/laravel-capabilities-messaging/
  phpunit.xml
  tests/Pest.php
  tests/Unit/…          # only allowed PHP automated tests for messaging

packages/capabilities-cli/
  **/*_test.go          # Go unit tests next to code; no live network by default

docs/requirements-inventory.md  # every spec requirement → TODO unit test checklist
```

Do **not** put package behaviour tests under monorepo-root `tests/`. Each Composer/Go package owns its suite.

### Spec → TODO tests

- **Complete contract scaffold:** every normative requirement from `docs/spec.md` (happy / fail / edge, including matrices) is listed in `@docs/requirements-inventory.md` and mirrored 1:1 as Pest `->todo()` / Go `t.Skip("TODO…")` stubs **inside the owning package**.
- **Generator (safe regenerate):** `python3 tools/generate_requirement_stubs.py` regenerates `docs/requirements-inventory.md` and only rewrites test files that are **missing** or still carry the `AUTO-GENERATED by tools/generate_requirement_stubs.py` marker. **Implemented unit tests (no marker) are never deleted or overwritten** — the run prints `written=` / `skipped=` counts and refuses overwrite of live suites. Extend the catalog in that script, then re-run; do not expect a blind re-run to wipe implemented tests. Pure stub files may still be regenerated; inventory markdown is always refreshed.
- **Inventory status sync:** `python3 tools/sync_requirements_inventory.py` marks inventory checkboxes from suite reality (static + matrix Pest titles, Go `Test*`).
- **Gap report (matrix-aware):** `python3 tools/report_inventory_gaps.py` prints remaining inventory gaps after the same matching (totals + by package; does not rewrite the inventory).
- **Source of truth:** when implemented, the tests define what the product is and is not. Spec text that has no scenario is incomplete; behaviour without a unit scenario is not specified for this monorepo.
- Implement todos with mocks/fakes; do not convert them into feature/DB tests.
- Run: `composer test:core` · `composer test:messaging` · `composer test:cli` (or `composer test` for both PHP packages).

### Quality bar

- New behaviour ships with unit tests in the **same** change.
- Package coverage stays **≥95%** after every change (blocking).
- Do not skip, disable, or comment out failing tests.
- Prefer TDD: failing unit test → implement → green.
- If a design seems to “need” a feature test or DB, redesign for injectability (interfaces + fakes) rather than weakening this policy.

## Locked decisions (from spec)

- **One `run()`.** Adapters are dumb; domain stays in app actions/services.
- **Surfaces default on** (agent, mcp, http, cli, job, artisan); **messaging defaults off** until messaging package is installed. Per-capability `surfaces` can only **narrow** global flags.
- **Governance is part of the capability:** authz, approval, audit, actor, scope apply on every surface.
- **Approvals** are a state machine with single execution + crash recovery (D-006 / P2-004) — not fire-and-forget.
- **Mutating invokes** support idempotency keys; store outcomes, not hope (D-005).
- **Audit:** default `best_effort` — domain success is not rolled back by audit failure unless `audit.mode = strict` (D-010).
- **CLI language: Go** (D-016). Binary name: `capabilities`. No multi-language CLI matrix in v0.2.
- **MCP principals** are explicit auth profiles: user_pat | integration | user_delegated (D-023) — not vague “token user.”
- **Peer support matrix + adapter contract tests** gate releases for `laravel/ai` / `laravel/mcp` (D-011). `AdapterApi` versions the bridge. Those contract tests are still **unit tests with mocks/fakes** — not feature/DB tests (see Testing).
- **Testing: unit only, zero feature tests, no DB required** — mock every external boundary. **≥95% coverage** is blocking. This is a monorepo policy, not a suggestion.
- **Roadmap (indicative):** v0.1 core bus → v0.2 Go CLI → v0.3 AI/MCP adapters → v0.4 approval/jobs/ops → v0.5+ messaging — phases are **unit-covered design targets** in-monorepo, not Packagist release labels (see `docs/spec.md` roadmap status columns + root README residuals).

## Conventions

- Prefer **typed package-native DTOs** (`CapabilityData`) on authorize/run/output; `array` only at wire edges.
- Catalog and tools share the **same JSON Schema** derived from DTOs — no hand-copied second schema.
- Fail **closed and obvious**: disabled surfaces register nothing; clear boot errors when peers missing while surface enabled.
- PHP: Laravel 11+ / 12, PHP ^8.2; **Pest unit tests only** (no feature suite); PHPStan max on package code when CI lands.
- Design for testability: depend on interfaces; production may bind DB drivers, tests bind in-memory fakes — same code paths.
- Go CLI: validate schema locally → ensure Idempotency-Key → POST single HTTP API; never embed domain logic; unit-test with mocked HTTP.
- When in doubt, re-read the matching **D-0xx** section in `docs/spec.md` rather than inventing behaviour.

## How to work here

- Spec-first: behaviour that conflicts with `docs/spec.md` is wrong until the spec is intentionally updated (and decisions recorded).
- Thin framework, fat domain: keep package glue thin; do not grow a second app framework, chat UI kit, or template gallery.
- Prefer **unit-test TDD** for new behaviour; implemented package tests (not empty stubs) define monorepo behaviour — do not invent second mutation paths outside the registry.
- Scope changes to the package that owns the concern (core vs messaging vs CLI). Cross-package only via published contracts/HTTP.
- If you catch yourself reaching for a feature test or a test database, stop and mock the boundary instead.

## Setup

- Shared skills hub / fleet health: `agent-doctor` skill / `agent-doctor status`.
- Vendor instruction files must **point at this file**; do not fork project policy into `CLAUDE.md` / `GEMINI.md` / etc.
