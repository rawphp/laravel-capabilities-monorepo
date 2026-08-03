# Changelog

All notable changes to `rawphp/laravel-capabilities-ai` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Fixed

- Proposal accept: non-retryable bus errors → terminal `failed` + `last_error` (no accepting limbo).
- Proposal accept: single safety system — claim `pending→accepting`, resume re-invokes under `proposal:{ulid}` (D-005); removed `accept_outcome` two-phase cache.
- Proposal accept: fail closed by default (`idempotencyStoreReady=false`); SP readiness = `bound(IdempotencyStore::class)` (no AI-local config dialect).
- Proposal reject: atomic `pending→rejected` only; refuse accepting/accepted/failed (idempotent rejected).
- `LlmClientDefaults` trait (`supportsToolRounds() => false`) for host implementors; multi-round tools opt-in only.
- SP config: claim_ttl via `configFromApp` (one typed config path).
- `ProposalFenceExtractor`: brace-balanced nested JSON (no silent drop on nested objects).
- Cheap create passes `claim_ttl` into `RunTurnJob` timeout.

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
