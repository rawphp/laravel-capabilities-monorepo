# Changelog

All notable changes to `rawphp/laravel-capabilities-ai` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Breaking (upgrade for hosts)

Wire contract changes on **proposal accept/reject** (0.x pre-stable). Hosts coded against older “always reject” / throw-as-API accept paths must update clients. Full tables: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire).

| Path | Old expectation | Current wire |
|------|-----------------|--------------|
| **Reject** non-pending (`accepting` / `accepted` / `failed` / `expired`) | Often force-set `rejected` / always 200 | Atomic CAS **pending→rejected** only; refuse → **HTTP 409** (`RuntimeException` in domain, mapped by `ChatController`) |
| **Reject** already-`rejected` | Varies | **Idempotent success** (still 200) — do not treat as error |
| **Accept** rejected / expired / failed / other terminals | Often `RuntimeException` / **500** | Typed `AcceptOutcome` + JSON body with `outcome` (no throw-as-API for known statuses) |
| **Accept** missing proposal | Often 500 / throw | **HTTP 404** |

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

### Fixed

- **Proposal accept/reject split-brain:** one fail-closed SM that returns typed `AcceptOutcome` for all known accept statuses (rejected/expired → refuse outcomes; no throw-as-API on accept). Atomic CAS claim/reject helpers, D-005 `idempotency_key=proposal:{ulid}`, `isApprovalRequired` then `isHardRefuse` then `isRetryable`, `last_error` on terminal failed, clear on accepted. Reject remains RuntimeException → 409 for non-pending.
- Proposal accept: live `IdempotencyReadiness` probe (not a frozen constructor stamp / Closure ceremony).
- Proposal accept: `approval_required` / retryable keep `accepting` (resumeable); hard non-retryable only → terminal `failed` + `last_error`.
- Proposal accept: branch on typed `CapabilityResult` (`isApprovalRequired`, `isRetryable`) — no primary wire-array archaeology.
- Proposal accept: explicit match arms for rejected/expired (not “is not pending” default copy).
- Proposal accept: single safety system — claim `pending→accepting`, resume re-invokes under `proposal:{ulid}` (D-005); no local `accept_outcome` cache.
- Proposal reject: atomic `pending→rejected` only; refuse accepting/accepted/failed/expired (idempotent rejected).
- `LlmClientDefaults` trait (`supportsToolRounds() => false`) for host implementors; multi-round tools opt-in only.
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
