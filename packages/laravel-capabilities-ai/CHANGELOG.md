# Changelog

All notable changes to `rawphp/laravel-capabilities-ai` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

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
| `AnthropicLlmClient` | **false** (trait) | Tools **not** advertised; bus tool path **refused before mutation** |
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

**Tool-role message `content` (JSON string):**

| Shape | Old expectation | Current wire |
|-------|-----------------|--------------|
| Success / failure | Always `{"ok":true,"name":…}` (or similar always-ok stub) | Full `CapabilityResult::toArray()` plus `name` (includes `ok`, `data` or `error`, `meta`) |
| Failure | Masked as ok | Honest `ok: false` + `error` (code/message) |

Authoritative: `TurnRunner` progress append + `encodeToolResult` (see package unit tests).

#### Anthropic default model ID

Default Anthropic model ID changed (0.x pre-stable). Hosts that rely on package defaults without pinning hit a **different model** at runtime.

| Site | Old default | New default |
|------|-------------|-------------|
| `config/capabilities-ai.php` (`CAPABILITIES_AI_ANTHROPIC_MODEL`) | `claude-sonnet-4-20250514` | `claude-sonnet-4-6` |
| `AnthropicLlmClient` constructor `model` parameter | `claude-sonnet-4-20250514` | `claude-sonnet-4-6` |

**Host impact:** package-default hosts receive `claude-sonnet-4-6` instead of `claude-sonnet-4-20250514` (different model behaviour / cost / latency).

**Mitigation (pin previous ID):** set env `CAPABILITIES_AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514`, or pass constructor `model: 'claude-sonnet-4-20250514'` when constructing `AnthropicLlmClient` directly.

#### Manual DI / constructor / job handle

Constructor and job-handle DI tightened for hosts that construct services outside the package service provider (0.x pre-stable). Preferred path remains **`CapabilitiesAiServiceProvider` / `ContainerBindings`** — manual `new` is advanced. Full host upgrade: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-manual-di-constructor-job-handle).

| Site | Old expectation | Current |
|------|-----------------|--------|
| **`TurnRunner` ctor** | `?ProgressStore $progress = null` (optional; often last/optional dep) | **`ProgressStore $progress` required** — 3rd ctor arg after `TurnClaim $claim`, `LlmClient $llm` (then optional context / tools / bus / …) |
| **`ConversationService` ctor** | Silent default / optional progress (e.g. in-ctor `ArrayProgressStore`) | **`ProgressStore $progress` required** (2nd arg after `$dispatch`; no silent array default) |
| **`RunTurnJob::handle`** | Empty `handle()` / no method injection | **`handle(TurnRunner $runner): void`** — queue workers **must** resolve via container method injection; empty `handle()` is no longer valid |
| **`ProposalService` ctor** | Accept without readiness dep / frozen stamp | **Requires `IdempotencyReadiness $idempotency`** (2nd arg after `CapabilityBus`). SP default: **`AlwaysReadyIdempotency`**; hosts rebind a **live probe** evaluated at accept time |

**Preferred path:** register `CapabilitiesAiServiceProvider` and resolve services from the container (`ContainerBindings` factories wire ProgressStore, AlwaysReady default, TurnRunner, ConversationService, ProposalService). Do not hand-roll `new TurnRunner(...)` / `new ConversationService(...)` / `new ProposalService(...)` unless you pass every required dep.

**Client impact:**

- Hosts constructing `TurnRunner` or `ConversationService` outside SP without an explicit `ProgressStore` **break** (type / argument count).
- Queue workers or custom job runners that call `handle()` with no container method injection for `TurnRunner` **break**.
- Hosts constructing `ProposalService` without `IdempotencyReadiness` **break**. Production hosts that need fail-closed accept must rebind `IdempotencyReadiness` (not rely on AlwaysReady forever).

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
