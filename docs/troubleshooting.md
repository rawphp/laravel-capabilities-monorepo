# Troubleshooting

Common boot, peer, auth, CLI, and messaging failures for Laravel Capabilities consumers. Verify against your published config and package version — this is 0.x pre-stable.

## Quick checks

1. Are you installing via **path** (monorepo) or **package-repo VCS**, not Packagist? Packagist publish is still a residual.
2. Did the app boot after enabling agent/MCP without installing peers?
3. Is the capability registered **once** (fluent **or** attribute, not both)?
4. For HTTP/CLI: is `surfaces.http.enabled` true and middleware auth satisfied?
5. For CLI: have you run `capabilities auth login --base-url=...` for this profile?
6. For Telegram: are secrets set **before traffic** (they are not required at boot)?

## Install and Composer

### `composer require rawphp/laravel-capabilities` fails from Packagist

**Cause:** Packages are not published on Packagist yet.  
**Fix:** Path or package-repo VCS — [versioning.md](versioning.md) · [Getting started](getting-started.md).

### Path repo not picking up changes

**Cause:** Composer mirror/cache or missing symlink option.  
**Fix:** Use `"options": { "symlink": true }` for path repos; `composer update rawphp/laravel-capabilities`. Confirm the package path URL matches your monorepo checkout.

### VCS require against the monorepo URL fails / wrong package root

**Cause:** Composer expects `composer.json` at the repository root. The monorepo nests packages under `packages/`.  
**Fix:** VCS-require the **split package remotes** (`rawphp/laravel-capabilities`, `…-messaging`, `…-ai`, `capabilities-cli`) updated on monorepo push — [versioning.md](versioning.md#monorepo--four-package-remotes-on-push).

### Messaging cannot resolve core

**Cause:** Messaging requires `rawphp/laravel-capabilities`.  
**Fix:** Require **both** packages (path both monorepo dirs, or VCS both package remotes). Messaging’s constraint is `*` for monorepo path resolution — pin when you leave path install.

## Boot and peers

### Boot exception when agent or MCP is enabled

**Cause:** `surfaces.agent` / `surfaces.mcp` enabled with `require_package` and peer missing, or installed peer outside `PeerSupportMatrix` / `peers.support`, with `on_incompatible=fail` (default).  
**Fix:**

- Install a compatible `laravel/ai` and/or `laravel/mcp`, **or**
- Disable the surface in config/env (`CAPABILITIES_SURFACE_AGENT`, `CAPABILITIES_SURFACE_MCP`), **or**
- Set `on_incompatible` to `disable` for soft-disable + CRITICAL log (surface stays off; no half-registered tools).

Matrix source: `packages/laravel-capabilities/src/Adapters/PeerSupportMatrix.php`.

### Tools partially registered on incompatible peer

**Cause:** Should not happen — package refuses half-register.  
**Fix:** Treat any partial list as a bug; upgrade package or disable surface. Do not paper over with docs-only workarounds.

### MCP register or boot fails with non-empty plan

**Cause (common):**

1. Profile lists a capability that does not exist or does not expose the **MCP** surface (allowlist validation).
2. Mid-mount adapter `Throwable` while `surfaces.mcp.on_register_error` is **`throw`** (default).
3. Peer missing/incompatible with `on_incompatible=fail` on a **non-empty** plan.

**Fix:**

- Correct `surfaces.mcp.profiles` to **registered** capability names that include MCP on their surfaces.
- Install a compatible `laravel/mcp` peer for planned servers.
- For temporary soft-empty on unexpected mid-mount failures only: `CAPABILITIES_MCP_ON_REGISTER_ERROR=disable` (still no half-register). Prefer fixing the root cause.
- Empty plan (`profiles`/`servers` empty or `auto_register` false) soft-fails without requiring the peer (ORI-801) — that is not a mid-mount error.

### `capabilities:integration-health` fails

**What it is:** Artisan **product** readiness (`php artisan capabilities:integration-health`). **Not** HTTP `GET …/capabilities/health` (catalog/surface peer status).

**Common fail reasons when AI package is installed:**

| Signal | Typical fix |
|--------|-------------|
| AlwaysReady while `proposals.enabled` | Do **not** bind `AlwaysReadyIdempotency` in prod; leave SP **`StoreBoundIdempotencyReadiness`** and wire core `IdempotencyStore`. Or set `CAPABILITIES_AI_PROPOSALS_ENABLED=false` on greenfield |
| `progress.driver=array` under AI-chat | `CAPABILITIES_AI_PROGRESS_DRIVER=redis` (do not set `CAPABILITIES_AI_ALLOW_UNSAFE` in production) |
| AI-chat via routes only, empty `queue.name` | Set `CAPABILITIES_AI_QUEUE_NAME` for workers |
| `claim_ttl` ≤ 0 | Restore default **120** (`CAPABILITIES_AI_CLAIM_TTL`) |

AI-chat mode for this command: `capabilities-ai.routes.enabled` **OR** non-empty `capabilities-ai.queue.name`.

### AI progress / LLM throws outside testing

**Cause:** Phase-3 guards — `progress.driver=array` or `llm.driver=fake` outside `APP_ENV=testing` without escape hatch.  
**Fix:** Production: redis progress + real `LlmClient` / `CAPABILITIES_AI_LLM_DRIVER=anthropic` (or host bind). Local demos only: `CAPABILITIES_AI_ALLOW_UNSAFE=1`.

### Host rebind broke queue or progress

**Cause:** Full `ConversationService` rebind for queue, or `singleton(ProgressStore::class)` replacing package redis/array.  
**Fix:** Use `CAPABILITIES_AI_QUEUE_NAME` / `CAPABILITIES_AI_QUEUE_CONNECTION` on default dispatch. Side-effects: `app()->extend(ProgressStore::class, …)` in **`boot()`** after package bind. See AI package [host integration](../packages/laravel-capabilities-ai/docs/user-guide.md#host-integration-greenfield).

## Capability definition and discovery

### Capability missing from catalog / registry

**Cause:** Wrong discovery path; provider not booted; surfaces list excludes channel; define never called.  
**Fix:**

- Attribute classes under `config('capabilities.path')` (default `app/Capabilities`).
- Fluent path must `->register($registry)` from a provider `boot`.
- Check capability `surfaces` and global `surfaces.*` flags.
- Do not register the same name twice with two styles.

### Authorize always denies

**Cause:** Null user on job/HTTP; scope re-resolve failed; policy deny.  
**Fix:** Jobs need an explicit actor (system actor or real user) — null user must not mean allow. Re-resolve resource ids under tenant scope. Confirm Sanctum/auth middleware for HTTP.

### `run()` never called but no exception

**Cause:** Validation fail, authorize deny, or approval-required path returned a deny-class result.  
**Fix:** Inspect `CapabilityResult` (`ok`, error codes). For HTTP/CLI, map status/exit codes (CLI exit 4 = approval required).

## HTTP API

### 401 / 403 on `/capabilities/*`

**Cause:** Default middleware includes `auth:sanctum`; token missing, wrong guard, or ability mismatch.  
**Fix:** Authenticate as the app expects; for CLI tokens align with `clients.token_abilities` (for example `capabilities:cli` → caller `cli`). Override `surfaces.http.middleware` only if you understand the security tradeoff.

### 404 on capability name

**Cause:** Not registered, wrong name/alias, or not exposed on http surface.  
**Fix:** `GET /capabilities` list; check aliases and `surfaces` including `http`.

### CLI or integration added a second invoke API

**Cause:** App forked a parallel controller.  
**Fix:** Remove it. One capability HTTP tree only (D-009). Point clients at `/capabilities/{name}`.

## Idempotency and approval

### Double apply on retry

**Cause:** Idempotency disabled, definition not idempotent, missing key, or memory driver lost state across processes.  
**Fix:** Enable idempotency; send `Idempotency-Key`; keep the package default `idempotency.driver` = `database` (and a shared DB) on multi-node hosts — do not leave `memory` in production. CLI sends keys automatically on `run`.

### Stuck pending approval

**Cause:** No notifier path; accept/reject never called; resume worker not running when using deferred execution.  
**Fix:** Use HTTP `POST /capabilities/approvals/{id}/accept|reject`, CLI `capabilities approvals accept|reject`, or messaging notifier. Review `approval.execution` and `approval.resume.*` in config. See spec for state machine detail.

## Product CLI

### `auth login requires --base-url`

**Fix:** `capabilities auth login --base-url=https://your-app.example` (optional `--token` or `--code`).

### `missing base URL: run auth login --base-url=...`

**Cause:** Profile has token/context without base, or wrong profile.  
**Fix:** Re-login with `--base-url` or pass `--base-url` on the command; check `--profile`.

### Exit code 2 with no HTTP call

**Cause:** Local JSON Schema validation failed (or capability name / input flags missing).  
**Fix:** `capabilities describe <name>`; fix `--input` / `--input-file`; `--no-cache` to refresh schema.

### Exit code 3

**Cause:** Unauthenticated or forbidden from server (or local auth guard missing token).  
**Fix:** `auth status`; login again; verify server token abilities and HTTP auth.

### Exit code 4

**Cause:** `approval_required`.  
**Fix:** Complete approval via `approvals accept <id>` (or other notifier), then retry per product rules (often same idempotency key only when appropriate).

### Exit code 6

**Cause:** Rate limited.  
**Fix:** Back off; review `rate_limits` config on the server.

### `unknown command`

**Fix:** Use `capabilities help`. Runnable commands: `auth`, `catalog`, `describe`, `run`, `approvals`, `version`, `help` (plus domain/verb synthesis). Token `mcp` is **reserved forever** as a domain name but is **not** a runnable command (CLI MCP stdio was removed).

### Built binary works but MCP client sees no tools

**Cause:** Product MCP is **server-side** (`laravel/mcp` + capabilities plan/adapter via `McpServerRegistrar`), not the CLI. Common gaps: `surfaces.mcp` disabled; peer missing/incompatible; empty `profiles` / `auto_register` false; **host never wired peer routes** (package does not live-mount under `path_prefix`); host pointed at a local `capabilities mcp` process that no longer exists.  
**Fix:** Enable `surfaces.mcp`, install compatible `laravel/mcp`, define named profiles (and leave `auto_register` true or register tools manually). **Wire** peer MCP servers in the app (e.g. `Mcp::web` / peer docs) using the planned profile tools — `path_prefix` is plan metadata only. For shell agents, use HTTP CLI `catalog` / `run` with a working auth profile — do **not** run `capabilities mcp`.

## Messaging (Telegram)

### Webhook route missing

**Cause:** `capabilities-messaging.telegram.enabled` is false.  
**Fix:** Enable via config/env `CAPABILITIES_TELEGRAM`; confirm `MessagingServiceProvider` loaded routes.

### Webhook fails only on first real traffic / notify

**Cause:** By design, secrets are validated on first use (D-021), not boot.  
**Fix:** Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, and callback secret env vars; retry. Do not set `skip_boot_checks` in production (fails closed / ignored there).

### User cannot run tools in chat

**Cause:** Identity not linked; allowlist miss; agent profile empty or too tight; authorize deny.  
**Fix:** Complete `code_link` bind or fix allowlist; set `agent_profile` to a profile that includes the needed capabilities; fix authorize/scope on core capabilities.

### Bot appears to “do work” without registry

**Cause:** App code bypassing the bus.  
**Fix:** Remove dual write paths. Messaging must not call domain `run()` directly.

## D-020 helpers

### `assertSchemaSnapshot` always passes but CI did not lock schema

**Cause:** Name-only call does not lock.  
**Fix:** Pass file path, conventional directory, or envelope — see [core guide](../packages/laravel-capabilities/docs/user-guide.md#testing-helpers-d-020).

### `assertParity` throws `InvalidArgumentException`

**Cause:** Missing non-empty `surfaces` list.  
**Fix:** Pass `'surfaces' => ['http', 'registry', …]` and realistic `input`.

### Parity mismatch success vs deny

**Cause:** Surface-specific authorize/caller/actor differences or approval-only on some paths.  
**Fix:** Align actors/options; treat approval-required as deny class; fix real dual-path bugs rather than weakening tests.

## Still stuck

1. [Concepts](concepts.md) — confirm the model  
2. Package guides under each package `docs/` (see [docs/README.md](README.md#package-local-docs))  
3. [spec.md](spec.md) — design oracle when behaviour is ambiguous  
4. Package unit tests under `packages/*/tests` — behavioural contract for the monorepo design  

When filing an issue, include: install method (path/VCS), package paths/SHAs, enabled surfaces, peer versions, and the exact error or CLI exit code.
