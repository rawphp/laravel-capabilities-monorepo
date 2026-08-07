# Changelog

All notable changes to `rawphp/laravel-capabilities-ai` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Fixed

- **Redis progress under Laravel phpredis (coach turns):** `resolveRedisClientOrNull` now unwraps the Illuminate Redis connection to the native ext-redis/predis client (`connection()->client()`). `RedisProgressStore` also accepts Laravel connection wrappers that only expose `rpush`/`lrange` via `__call`. Without this, hosts with `CAPABILITIES_AI_PROGRESS_DRIVER=redis` failed every turn with `Redis client missing rPush` (SSE progress never appended; coach chat returned temporary-problem failures).
- **Bus invoke principal (ORI-775):** `TurnRunner` tool invokes and `ProposalService` accept invokes now pass `caller=job` + conversation User as `actor` (legacy coach / `RunCoachCommandHandler` shape). Missing or unresolvable `conversation.user_id` fails closed (no `ResolveActor::defaultUser()` / silent id=1). Config: `capabilities-ai.user_model` (fallback `auth.providers.users.model`).

### Added

- **Host integration seams (UR-062 / D-024):** queue-on-default-dispatch, live idempotency readiness, proposals full gate, stale-turn reaper, phase-3 unsafe-driver guards. Greenfield checklist + ProgressStore `extend` order + residual kill-list: [docs/user-guide.md](docs/user-guide.md#host-integration-greenfield). Core companion: `php artisan capabilities:integration-health` (≠ HTTP `/capabilities/health`) and MCP `on_register_error` — see [rawphp/laravel-capabilities CHANGELOG](https://github.com/rawphp/laravel-capabilities/blob/main/CHANGELOG.md).
  - **`capabilities-ai.queue.{name,connection}`** (`CAPABILITIES_AI_QUEUE_NAME`, `CAPABILITIES_AI_QUEUE_CONNECTION`) — default `RunTurnJob` dispatch sets Laravel public `$queue` / `$connection` when non-empty. No `ConversationService` rebind for queue routing.
  - **`StoreBoundIdempotencyReadiness`** — production SP default for `IdempotencyReadiness`: live probe of core `IdempotencyStore` when bound; else `isReady()=false`. **`AlwaysReadyIdempotency` is unit-tests only** (not production default).
  - **`proposals.enabled`** (`CAPABILITIES_AI_PROPOSALS_ENABLED`, Phase-1 BC default **true**; **greenfield: false**) — gates accept/reject routes, TurnRunner fence → proposal extract, and history proposals.
  - **`capabilities-ai:reap-stale-turns`** + `reaper.stale_queued_minutes` / `reaper.stale_running_grace_seconds` — host schedules the command; package does not auto-schedule. Running threshold uses max(`claim_ttl`, grace). **`claim_ttl` default remains 120**.
  - **`CAPABILITIES_AI_ALLOW_UNSAFE`** / `allow_unsafe` — outside `APP_ENV=testing`, `progress.driver=array` and `llm.driver=fake` throw unless the escape hatch is set (local demos only).
- **Multimodal (vision) user content (UR-051):** `LlmClient` / `ConversationContextProvider` message `content` may be a **string** or a **list of content blocks** (e.g. Anthropic `{ type: "text" }` + `{ type: "image", source: { type: "base64", media_type, data } }`). `AnthropicLlmClient` passes user block arrays through to the Messages API unchanged and still stringifies pure text turns. **Hosts must supply image bytes** in context (package does not store or fetch attachments).
- **Anthropic multi-round tools (ORI-730):** `AnthropicLlmClient::supportsToolRounds()` is **true**. Package tool defs map to Anthropic `tools` (`name`, `description`, `input_schema`). Responses parse `tool_use` into `tool_calls` **with `id`**. Request encoding maps assistant `tool_calls` → `tool_use` blocks and `role=tool` → user `tool_result` blocks (`tool_use_id`). Empty API key / HTTP errors stay fail-closed. `TurnRunner` now re-appends the assistant `tool_calls` turn into the transcript before `role=tool` results so providers can correlate.
- **Anthropic max_tokens host parity (ORI-739):** `AnthropicLlmClient` no longer hard-codes `max_tokens => 1024`. Constructor default and package config `llm.anthropic.max_tokens` (`CAPABILITIES_AI_ANTHROPIC_MAX_TOKENS`) default to **64000**. `ContainerBindings::makeLlmClient` wires the config value.


### Breaking (upgrade for hosts)

#### Proposal accept/reject wire

Wire contract changes on **proposal accept/reject** (0.x pre-stable). Hosts coded against older “always reject” / throw-as-API accept paths must update clients. Full tables: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire).

| Path | Old expectation | Current wire |
|------|-----------------|--------------|
| **Reject** non-pending (`accepting` / `accepted` / `failed` / `expired`) | Often force-set `rejected` / always 200 | Atomic CAS **pending→rejected** only; refuse → **HTTP 409** (`RuntimeException` in domain, mapped by `ChatController`) |
| **Reject** already-`rejected` | Varies | **Idempotent success** (still 200) — do not treat as error |
| **Accept** rejected / expired / failed / other terminals | Often `RuntimeException` / **500** | Typed `AcceptOutcome` + JSON body with `outcome` (no throw-as-API for known statuses) |
| **Accept** missing proposal | Often 500 / throw | **HTTP 404** |
| **Accept** bus invoke | Bare invoke / optional key | **Always** `idempotency_key=proposal:{ulid}` (D-005). Host must wire core **`IdempotencyStore`** so double-accept / resume dedupe; readiness not ready → **503** `failed` (no bus). Conversation/tool invokes stay **bare** (`idempotency_key` null) — not proposal keys |

Accept HTTP (from `AcceptOutcome.httpStatus` when set, else controller kind defaults):

| Outcome | Typical HTTP | Notes |
|---------|--------------|--------|
| `accepted` | **200** | Done |
| `approval_required` | **202** | Stays `accepting`; re-drive |
| `retryable` | **429** / **409** | Stays `accepting`; `httpStatus` from result (rate_limited → 429; kind default 409) |
| `failed` | **422** / **503** | Terminal failed, or idempotency not ready (503) |
| `refuse` (bus hard) | **403** | Terminal |
| `refuse` (already rejected) | **409** | Do not re-drive |
| `refuse` (expired) | **410** | Do not re-drive |
| missing | **404** | — |

#### LlmClient `supportsToolRounds()` (compile / runtime)

`LlmClient` now **requires** `supportsToolRounds(): bool`. Custom host implementors fail type-check / runtime until they add the method or `use LlmClientDefaults` (returns **false**). Full host upgrade: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-llmclient-tool-rounds).

| Client | `supportsToolRounds()` | Effect |
|--------|------------------------|--------|
| Host custom (no method) | **Break** until implemented | PHP interface missing method |
| `LlmClientDefaults` trait | **false** | Fail-closed default for hosts |
| `AnthropicLlmClient` | **true** (override) | Tools advertised; multi-round `tool_result` / `tool_use` supported |
| `FakeLlmClient` | **true** | Multi-round tool unit tests opt in |

Honesty rule: return **true** only if the next `complete()` accepts tool-result messages (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Lying opens bus-then-crash after mutation.

#### Tool progress + tool-role message content

Progress `kind=tool` events and multi-round tool-role message `content` are **honest bus wire** (0.x pre-stable). Hosts that assumed always-ok tool content or a `{name,payload}`-only progress shape must adapt. Full tables: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-tool-progress-and-tool-messages).

**Progress `kind=tool` `data`:**

| Field | Old expectation | Current wire |
|-------|-----------------|--------------|
| `name` | capability name | unchanged |
| `payload` | invoke input array | unchanged |
| `ok` | often absent / assumed true | **bool** from `CapabilityResult::$ok` |
| `error_code` | absent | **string\|null** from `CapabilityResult::errorCode()` (null when ok) |
| `tool_call_id` | absent | **string** correlating to model `tool_calls[].id` |

**Tool-role message (multi-round transcript):**

| Shape | Old expectation | Current wire |
|-------|-----------------|--------------|
| `content` (JSON string) | Always `{"ok":true,"name":…}` (or similar always-ok stub) | Full `CapabilityResult::toArray()` plus `name` (includes `ok`, `data` or `error`, `meta`) |
| Failure content | Masked as ok | Honest `ok: false` + `error` (code/message) |
| Correlation fields | content-only | Message also carries `tool_call_id` and `id` (same id; empty model ids get a round-local fallback) |

Authoritative: `TurnRunner` progress append + tool-role append (see package unit tests).

#### Anthropic default model ID

Default Anthropic model ID changed (0.x pre-stable). Hosts that rely on package defaults without pinning hit a **different model** at runtime.

| Site | Old default | New default |
|------|-------------|-------------|
| `config/capabilities-ai.php` (`CAPABILITIES_AI_ANTHROPIC_MODEL`) | `claude-sonnet-4-20250514` | `claude-sonnet-4-6` |
| `AnthropicLlmClient` constructor `model` parameter | `claude-sonnet-4-20250514` | `claude-sonnet-4-6` |

**Host impact:** package-default hosts receive `claude-sonnet-4-6` instead of `claude-sonnet-4-20250514` (different model behaviour / cost / latency).

**Mitigation (pin previous ID):** set env `CAPABILITIES_AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514`, or pass constructor `model: 'claude-sonnet-4-20250514'` when constructing `AnthropicLlmClient` directly.

#### Chat HTTP non-proposal routes (stub → real)

Wire contract changes on **non-proposal** chat HTTP routes (0.x pre-stable). Only applies when hosts enable the optional route table (`capabilities-ai.routes.enabled` / `CAPABILITIES_AI_ROUTES_ENABLED`; **default false**). Clients written against always-200 / empty stubs must handle real service payloads and **404** / **409**. Proposal accept/reject have their own Breaking tables — see [Proposal accept/reject wire](#proposal-acceptreject-wire) (do not re-document here). Full host tables: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-chat-http-non-proposal-routes).

| Route action | Old expectation | Current wire |
|--------------|-----------------|--------------|
| **history** (`GET …/conversations/{ulid}`) | Empty messages / always **200** | Real history payload from `ConversationService`; missing conversation → **HTTP 404** (`ModelNotFoundException` → `ChatController`) |
| **showTurn** (`GET …/turns/{ulid}`) | Stub body `{turn_ulid}` / always **200** | Real turn payload from `TurnService`; missing turn → **HTTP 404** |
| **cancelTurn** (`POST …/turns/{ulid}/cancel`) | Always **200** cancelled stub | Real cancel; missing → **HTTP 404**; not cancellable / conflict → **HTTP 409** + `message` (`RuntimeException`) |
| **turnEvents** (`GET …/turns/{ulid}/events`) | Empty events / always **200** | Real progress events; query `cursor` (default **0**); body `{turn_ulid, events}`; missing turn → **HTTP 404** |
| **destroyConversation** (`DELETE …/conversations/{ulid}`) | Always **200** deleted stub | Real destroy; missing → **HTTP 404**; conflict (e.g. active turns) → **HTTP 409** + `message` (`RuntimeException`) |

**Host impact:** clients that treated these endpoints as always-**200** empty/stub bodies will mis-handle missing resources and conflicts once routes are enabled. Expect real domain payloads on success and branch on **404** / **409**.

**Gate:** no Breaking surface while `routes.enabled` remains **false** (package default). Enabling the route table is the upgrade trigger.

Authoritative mapping: `ChatController` (`history`, `showTurn`, `cancelTurn`, `turnEvents`, `destroyConversation`).

#### Manual DI / constructor / job handle

Constructor and job-handle DI tightened for hosts that construct services outside the package service provider (0.x pre-stable). Preferred path remains **`CapabilitiesAiServiceProvider` / `ContainerBindings`** — manual `new` is advanced. Full host upgrade: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-manual-di-constructor-job-handle).

| Site | Old expectation | Current |
|------|-----------------|--------|
| **`TurnRunner` ctor** | `?ProgressStore $progress = null` (optional; often last/optional dep) | **`ProgressStore $progress` required** — 3rd ctor arg after `TurnClaim $claim`, `LlmClient $llm` (then optional context / tools / bus / …) |
| **`ConversationService` ctor** | Silent default / optional progress (e.g. in-ctor `ArrayProgressStore`) | **`ProgressStore $progress` required** (2nd arg after `$dispatch`; no silent array default) |
| **`RunTurnJob::handle`** | Empty `handle()` / no method injection | **`handle(TurnRunner $runner): void`** — queue workers **must** resolve via container method injection; empty `handle()` is no longer valid |
| **`ProposalService` ctor** | Accept without readiness dep / frozen stamp | **Requires `IdempotencyReadiness $idempotency`** (2nd arg after `CapabilityBus`). SP default: **`StoreBoundIdempotencyReadiness`** (live core store ping; fail closed when unbound). **`AlwaysReadyIdempotency` is tests-only** |

**Preferred path:** register `CapabilitiesAiServiceProvider` and resolve services from the container (`ContainerBindings` factories wire ProgressStore, **`StoreBoundIdempotencyReadiness`**, TurnRunner, ConversationService, ProposalService). Do not hand-roll `new TurnRunner(...)` / `new ConversationService(...)` / `new ProposalService(...)` unless you pass every required dep.

**Client impact:**

- Hosts constructing `TurnRunner` or `ConversationService` outside SP without an explicit `ProgressStore` **break** (type / argument count).
- Queue workers or custom job runners that call `handle()` with no container method injection for `TurnRunner` **break**.
- Hosts constructing `ProposalService` without `IdempotencyReadiness` **break**. Production path is **`StoreBoundIdempotencyReadiness`** + core **`IdempotencyStore`** wired; do **not** bind AlwaysReady outside unit tests.

**Do not** restore nullable ProgressStore or change production DI to soften this — docs only catch hosts up to shipped code.

### Fixed

- **Proposals `last_error` column (upgrade hosts):** `last_error` was added in-place to the create migration after some hosts had already run it. Hosts whose `capabilities_ai_proposals` table lacks the column will SQL-error on accept fail/success paths that write or clear `last_error`. Run **`php artisan migrate`** so package migration `2026_08_04_000001_add_last_error_to_capabilities_ai_proposals_table` applies (idempotent ALTER; greenfield installs already get the column from the create migration). VCS/path consumers — not Packagist-required yet.
- **Proposal accept/reject split-brain:** one fail-closed SM that returns typed `AcceptOutcome` for all known accept statuses (rejected/expired → refuse outcomes; no throw-as-API on accept). Atomic CAS claim/reject helpers, D-005 `idempotency_key=proposal:{ulid}`, `isApprovalRequired` then `isHardRefuse` then `isRetryable`, `last_error` on terminal failed, clear on accepted. Reject remains RuntimeException → 409 for non-pending.
- Proposal accept: live `IdempotencyReadiness` probe (not a frozen constructor stamp / Closure ceremony).
- Proposal accept: `approval_required` / retryable keep `accepting` (resumeable); hard non-retryable only → terminal `failed` + `last_error`.
- Proposal accept: branch on typed `CapabilityResult` (`isApprovalRequired`, `isRetryable`) — no primary wire-array archaeology.
- Proposal accept: explicit match arms for rejected/expired (not “is not pending” default copy).
- Proposal accept: single safety system — claim `pending→accepting`, resume re-invokes under `proposal:{ulid}` (D-005); no local `accept_outcome` cache.
- Proposal reject: atomic `pending→rejected` only; refuse accepting/accepted/failed/expired (idempotent rejected).
- SP config: claim_ttl via `configFromApp` (one typed config path).
- `ProposalFenceExtractor`: brace-balanced nested JSON (no silent drop on nested objects).
- Cheap create passes `claim_ttl` into `RunTurnJob` timeout.

### Added (core consumer)

- `CapabilityResult::isRetryable()` — typed retry policy for accept / adapters.

### Added

- Package runtime for capability-bus AI turns: conversations, messages, turns, proposals.
- Cheap create path (persist + enqueue `RunTurnJob`) that never calls the LLM inline.
- Atomic turn claim + `TurnRunner` LLM loop with tools **only** via core `CapabilityBus::invoke`.
- Progress store (array + optional Redis) — not product MySQL.
- Pluggable `LlmClient` (`FakeLlmClient`, `AnthropicLlmClient`) + host seams
  (`ConversationContextProvider`, `ToolCatalog`).
- Config-driven container bindings (`CapabilitiesAiServiceProvider` / `ContainerBindings`) for
  LLM, progress, claim, runner, conversation/proposal/turn services.
- Optional HTTP route table under `capabilities-ai/chat` when `routes.enabled=true`
  (history, turns show/cancel/events, messages, proposals, destroy).

### Notes

- **Not published on Packagist.** Install from package-repo VCS or monorepo path.
- **Library API:** hosts must bind `ConversationContextProvider` and `ToolCatalog` before
  `TurnRunner::run` (fail closed). CapabilityBus comes from core.
- Queue workers resolve `RunTurnJob::handle(TurnRunner $runner)` via the container (DI from UR-017).
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities-ai` on push.

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0 (mirrored to this package remote), promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix.
-->

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/laravel-capabilities-ai/compare/HEAD...HEAD
