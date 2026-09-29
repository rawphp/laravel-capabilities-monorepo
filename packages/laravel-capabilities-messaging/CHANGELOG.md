# Changelog

All notable changes to `rawphp/laravel-capabilities-messaging` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Breaking

- **Test scaffolding removed from production classes** — gone: `ProcessTelegramUpdate::runPipeline()`
  (and its `fail_at` injection), `domainBypassAttempted()` and the constant `domain_bypass` /
  `observable` result keys; `TelegramWebhookController::registryInvokeCount()`;
  `TelegramApprovalNotifier::notified()`, `edits()`, `capabilityExecuteCount()`,
  `domainServiceCalls()`; `TelegramAdapter::handled()`, `replies()`, `failIngress()`,
  `failReply()`; `ThreadStore::failNext()`; `TelegramBotClient::calls()` and
  `HttpTelegramBotClient::calls()` (the recorder stays on `FakeTelegramBotClient`). The recorder
  arrays grew for the life of a queue worker on container singletons. `ThreadStore` is no longer
  `final`. **Consumer impact:** custom `TelegramBotClient` implementations no longer need `calls()`.

- **Chat identities resolve to the host user model** — new `user_model` config key
  (`CAPABILITIES_MESSAGING_USER_MODEL`, falling back to `auth.providers.users.model`). The
  container-bound `IdentityLinker` now uses `Identity\ModelUserFactory`, which loads the linked
  id with `Model::query()->find()` and throws when no model is configured, the class is
  unusable, or the id does not resolve. Capabilities used to receive a `LinkedUser` DTO as the
  actor for Telegram tool calls. `LinkedUser` stays the default only when `IdentityLinker` is
  constructed without a factory (tests / `MessagingBindings::build()`).

- **Agent turn is a host binding; no echo default** — chat messages now reach the agent through
  `Contracts\AgentTurn` (`toolNames($profile)` + `respond($message)`), which the host binds (for
  example around a `laravel/ai` agent). The provider wires it into `TelegramAdapter` (ingress) and
  `ProcessTelegramUpdate` (profile tools). With nothing bound, `TelegramAdapter::handle()` throws
  `agent_turn_unbound`: no reply is sent and the failure is logged, where it used to echo the
  user's text back with no tools. The unused `$agentRunner` constructor argument of
  `ProcessTelegramUpdate` is removed. **Consumer impact:** bind `AgentTurn` to get replies.

### Security

- **CallbackHandler approver binding** — a non-empty signed `approver_hint` now binds the
  callback to that product principal id (the linked user's `id`, else `getAuthIdentifier()`).
  A different linked Telegram user clicking a forwarded/leaked button gets
  `status: forbidden`, `message: approver_mismatch` before the approval gateway is touched.
  Empty hint keeps the old behaviour (approval policy decides). **Consumer impact:** hosts
  that put a Telegram user id (or anything other than the product user id) in
  `approver_hint` must switch to the product user id or send an empty hint.

### Changed

- **Core constraint is lockstep:** `require.rawphp/laravel-capabilities` is now `self.version` instead of `*`. This package at tag `v0.Y.Z` installs only with core `v0.Y.Z` (and `dev-main` with core `dev-main`). **Hosts:** require the same version of core and this package.

### Added

- **Audited tool profile** — Telegram tool calls pass the configured `agent_profile` as the
  `tool_profile` invoke option, so core audit entries record which profile gated the call.
- **Allowlist user check in setup validation** — `TelegramSetup::validate()` / `runOrFail()`
  take an optional host user lookup `(laravelUserId, tenantId) => ?object`. Each
  `identity.allowlist` entry whose `laravel_user_id` does not resolve fails setup loudly
  (index, user id, telegram id in the message) instead of linking a user that does not exist.
  Entries missing `telegram_user_id` or `laravel_user_id` (previously skipped silently at
  runtime) now fail setup with or without a lookup.

### Fixed

- **Link codes and identity links are shared across processes** — `/start <code>` runs on the
  queue worker, but codes and links lived in the process-local `IdentityLinker`, so a code
  issued in a web request never bound and links vanished on restart. They now live behind
  `Identity\LinkStore`: the container binds `CacheLinkStore` on the host cache repository
  (`Illuminate\Contracts\Cache\Repository`), with code TTL from `identity.code_ttl_seconds` and
  an atomic claim so a code binds at most once across workers. `InMemoryLinkStore` stays the
  default for a bare `new IdentityLinker(...)` and `MessagingBindings::build()`. Allowlist
  entries stay in config. **Consumer impact:** links persist in your cache without expiry — use
  a persistent store, or bind `LinkStore` to `new CacheLinkStore(Cache::store(...))`. The L-006
  residual now covers `ThreadStore` history only.

- **Failures reach the host logger** — `TelegramWebhookController` and `ProcessTelegramUpdate`
  take an optional PSR-3 `logger` (the provider injects the bound `LoggerInterface`). Bad
  secrets, queue failures and every processing failure are logged with the D-019
  `tags` (`channel`, `chat_id`, `update_id`); unlinked users and rate-limited chats log as
  `warning`, everything else as `error`. The in-memory `logs()` recorders are removed.
  Adds `psr/log` to `require`.

- **Telegram updates are really queued** — `ProcessTelegramUpdateJob` now implements `ShouldQueue`
  (`tries = 3`, `backoff = [10, 60]`, public `queue` / `connection`). Before, the Laravel bus ran
  the whole update inline in the webhook request. Transient failures (retryable Bot API errors on
  reply, retryable registry results) now throw `Telegram\RetryableUpdateFailure` out of
  `ProcessTelegramUpdate::handle()` so the job fails and retries; terminal failures still return
  `ok: false`. **Consumer impact:** production needs a queue worker for messaging.

- **Approval buttons fit Telegram's 64-byte `callback_data` limit** — `TelegramCallbackSigner::encode()`
  now emits `{a|r}.{approval_id}.{exp base36}.{sig}` (HMAC-SHA256 truncated to 96 bits) instead
  of base64 JSON (195+ bytes, rejected by the Bot API with `BUTTON_DATA_INVALID`), and throws when
  a token would exceed 64 bytes. The approver hint is still signed but no longer transmitted:
  `CallbackHandler` re-verifies a hint-less token against the clicking user's principal id, so a
  token bound to another user returns `invalid` (not `approver_mismatch`). `FakeTelegramBotClient`
  now rejects oversized `callback_data` like the Bot API. **Consumer impact:** `sign()`'s `sig` is
  now 16 base64url chars (was 64 hex); tokens issued before upgrade no longer decode.

- **Callback signing key** — the container-bound `TelegramCallbackSigner` and approval notifier
  now sign with `telegram.callback_secret` (falling back to `telegram.webhook_secret`). Before,
  buttons were always signed with the webhook secret, so a distinct `TELEGRAM_CALLBACK_SECRET`
  broke every callback, and with no webhook secret the key was the literal `'deferred-unset'`.
  Resolving the signer with neither secret set now throws; the notifier resolves without secrets
  and checks them on notify (D-021). `MessagingBindings::build()` no longer returns a `signer`.

- **Chat-side code link** — in `code_link` mode (the default) `ProcessTelegramUpdate` recognises
  `/start <code>` and `/link <code>` before identity resolution, calls
  `IdentityLinker::bindWithCode()` and replies with `ProcessTelegramUpdate::LINKED_REPLY` or
  `LINK_FAILED_REPLY` (result `error: link_code_invalid`). No agent turn or tool runs for the
  command. Before, nothing consumed a code, so the default identity mode could not link anyone.

## [0.5.0] - 2026-08-07

Cumulative: entries shipped in tags `v0.1.0` through `v0.5.0`. Those tags did not get
per-tag sections; this file's git history shows the tag each entry first shipped in.

### Breaking

- **CallbackHandler ApprovalGateway (consumer impact)** — constructor third arg type-hint
  is now `?ApprovalGateway` (was `?ApprovalManager`). Runtime still accepts `ApprovalManager`
  because it implements the gateway, but static analysis / manual constructors hard-coding
  `ApprovalManager` as the third parameter type need updating. Pre-accept/reject lookup uses
  `ApprovalGateway::find()` (lazy pending TTL expiry, aligned with HTTP accept) instead of
  `store()->find()`. **Consumer impact:** (1) type-hint: hosts/static analysis using
  `ApprovalManager` on the third arg must switch to `ApprovalGateway`; (2) lazy TTL:
  Telegram callback outcomes for TTL-stale *pending* rows can change (`already_handled` /
  expired path vs a prior accept/reject attempt that ignored lazy expiry); (3) exception
  string: missing-approvals `RuntimeException` message renamed
  `ApprovalManager is required…` → `ApprovalGateway is required…` (host tests asserting the
  old string break).

### Changed

- **Internal extract** — `TelegramUpdateParser` peels pure Update field extraction from
  `ProcessTelegramUpdate` (pipeline behaviour unchanged; MSG-003 handler remains the public entry).

### Added

- **Laravel 13 / illuminate 13 support** — all `illuminate/*` requirements allow `^11.0|^12.0|^13.0`.
- Sibling conversation package for the capabilities bus (Telegram-first): webhooks, identity links,
  threads, and chat-side approval notification — implements **core contracts only** (D-007).
- No second mutation path: domain `run()` stays behind the core registry / agent tools.

### Notes

- **Not published on Packagist.** Depends on `rawphp/laravel-capabilities` from path or package VCS.
- Messaging surfaces default **off** in core until this package is installed and configured.
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities-messaging` on push.

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.
Tags without their own section recorded no entries for this package.

[Unreleased]: https://github.com/rawphp/laravel-capabilities-messaging
[0.x]: https://github.com/rawphp/laravel-capabilities-messaging
