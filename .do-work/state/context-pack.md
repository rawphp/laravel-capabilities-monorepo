# Project context pack — laravel-capabilities

## Architecture
Product capability bus monorepo: define product operations once (capability with schema, authz, single `run()`, approval, audit) and expose via many surfaces (agent, MCP, HTTP, CLI, jobs). Core package is the choke point; messaging and Go CLI are adapters/clients.

## Packages
- `packages/laravel-capabilities` — core bus (`Rawphp\Capabilities\`): registry, schema, HTTP, AI/MCP/job adapters, approval SM, audit, scope, idempotency, conversation contracts
- `packages/laravel-capabilities-messaging` — Telegram first (`Rawphp\CapabilitiesMessaging\`): webhooks, identity, threads; implements core contracts only
- `packages/capabilities-cli` — Go product CLI (`capabilities`): HTTP client for auth/catalog/run/MCP stdio

## Directory roles
- `docs/spec.md` — design bible (D-002–D-023, pipeline, surfaces)
- `docs/requirements-inventory.md` — every normative scenario → unit test checklist
- `tools/generate_requirement_stubs.py` — generator for inventory + Pest/Go stubs
- Root `composer.json` path-requires PHP packages; CLI is Go (`go.mod`)

## Key modules (core)
- `CapabilityRegistry` — single invoke choke point (all surfaces call this)
- Pipeline stages: validate → hydrate → actor → scope → idempotency → authorize → approval → rateLimit → run → output → audit
- Contracts for Approval/Idempotency/Audit stores — inject fakes in unit tests
- HTTP capability API (single tree; CLI is remote client)
- Surfaces default on except messaging off until package installed

## Testing (non-negotiable)
- **Unit tests only** — zero feature tests, no DB required
- Mock/fake all IO: stores, HTTP, laravel/ai, laravel/mcp, queues, clock
- Coverage ≥95% on each PHP package `src/`
- Run: `composer test:core` · `composer test:messaging` · `composer test:cli` · `composer test` (both PHP)
- Layout: each package owns `tests/Unit/**` (Pest) or `*_test.go`

## Naming & conventions
- PHP: Laravel 11+/12, PHP ^8.2; namespaces above; DTOs typed (`CapabilityData`); JSON Schema from DTOs
- Fail closed; no dual mutation paths; server-derived caller (never client-claimed)
- Go CLI validates schema for UX only; server re-validates

## Suite commands
- `composer test` (core + messaging)
- `composer test:core` / `test:messaging` / `test:cli`
- Worktree link: vendor, packages/laravel-capabilities/vendor, packages/laravel-capabilities-messaging/vendor
- Setup: `composer install --no-interaction`
- Note: Go may need PATH setup for CLI suite

## Constraints
- Do not put messaging Bot API inside core
- Do not reimplement LLM/MCP wire protocols
- Do not add Feature tests or require DB
- Spec conflicts: update tests intentionally with docs/spec.md as oracle; do not prune inventory scenarios silently
