# Changelog

All notable changes to `rawphp/laravel-capabilities-messaging` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

## [0.7.0] - 2026-10-01

No changes.

## [0.6.1] - 2026-09-30

No changes.

## [0.6.0] - 2026-09-30

### Breaking

- **`TelegramBotClient::answerCallbackQuery(string $callbackQueryId, string $text = '', array $payload = []): array`**
  is a new interface method (M-101). `HttpTelegramBotClient` and `FakeTelegramBotClient`
  implement it; **hosts with their own `TelegramBotClient` must add it** (acknowledge a tapped
  inline button; `text` shows as a toast).
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

- **Agent turns answer their tool results** — `Contracts\AgentTurn` gains
  `respondWithResults(array $message, array $toolResults): array{text}`. After messaging invokes
  the tool calls `respond()` returned, it passes each `['name', 'input', 'result' =>
  CapabilityResult]` to this method and sends its `text` as the reply. Before, the reply was the
  text `respond()` wrote before any tool ran, tool output never reached the agent or the user,
  and any result that was not ok (`approval_required`, `forbidden`, `validation_failed`, …) ended
  the update with no reply at all. Invocation still stops at the first result that is not ok;
  tool calls returned from `respondWithResults()` are ignored (one tool round per message). A
  retryable capability result no longer fails the queued job (a retry re-ran the LLM and could
  issue different tool calls); it goes to the agent like any other result. The update result
  reports the last non-ok tool code as `error` with `ok: false`, while still sending the reply.
  An unlinked user in a private chat in `code_link` mode now gets
  `ProcessTelegramUpdate::UNLINKED_REPLY` (how to link) instead of silence. **Consumer impact:**
  implement `respondWithResults()` on your `AgentTurn`; a handler passed to `TelegramAdapter`
  directly receives the follow-up as a message with a `tool_results` key.

### Security

- **Allowlist mode ignores code-bound links** — `IdentityLinker::resolve()` and `isLinked()`
  read the `LinkStore` only in `code_link` mode. Links are durable in the host cache, so before
  this, switching `identity.mode` to `allowlist` still let every user bound by `/start <code>`
  run tools. Now only `identity.allowlist` entries resolve in `allowlist` mode (fail closed);
  the stored links stay in the cache and come back if you switch to `code_link` again.
  `IdentityLinker::link()` now throws outside `code_link` mode instead of writing a link that
  would never resolve. **Consumer impact:** in `allowlist` mode, list every chat user you want
  in `identity.allowlist`.

- **CallbackHandler approver binding** — a non-empty signed `approver_hint` now binds the
  callback to that product principal id (the linked user's `id`, else `getAuthIdentifier()`).
  A different linked Telegram user clicking a forwarded/leaked button gets
  `status: forbidden`, `message: approver_mismatch` before the approval gateway is touched.
  Empty hint keeps the old behaviour (approval policy decides). **Consumer impact:** hosts
  that put a Telegram user id (or anything other than the product user id) in
  `approver_hint` must switch to the product user id or send an empty hint.

- **Bot API requests carry Bot API fields only** — `TelegramApprovalNotifier` sent the full
  signed accept/reject payloads (including `approver_hint`, the product user id) as extra
  `sendMessage` parameters, and replies forwarded the internal `thread_id`.
  `HttpTelegramBotClient` now sends `chat_id`, `text` (and `message_id` on edits) plus only
  `message_thread_id`, `reply_markup` and `parse_mode` from the payload; the notifier sends only
  `reply_markup`. `TelegramAdapter::reply()` reads `chat_id`, `text` and `topic_id` and ignores
  every other key. **Consumer impact:** tests that read `accept_payload` / `signed_buttons` from
  a bot recorder should decode `reply_markup` button `callback_data` with
  `TelegramCallbackSigner::decode()` instead.

- **Per-user link revocation** — `IdentityLinker::unlink($telegramUserId)` and
  `unlinkUser($userId, $tenantId)` revoke one code-bound link, in any identity mode, without
  touching other users. Before this the only revocations were switching the whole install to
  `allowlist` or flushing the cache. A product user now has at most one stored link per tenant:
  binding a code (or `link()`) from another Telegram account revokes the earlier link instead of
  leaving both active. **Consumer impact:** `Identity\LinkStore` gains `forgetLink()` and
  `findTelegramUserId()`, and `putLink()` must keep the reverse index; custom stores implement
  them. Links stored before this change have no reverse index: `unlinkUser()` finds them only
  after the user links again (`unlink()` works on them now).

### Changed

- **`TelegramApprovalNotifier::notifyPending()` skips rows with no chat target** instead of
  throwing `Approval notify requires messaging.chat_id.` (M-101). Now that core fires notifiers
  for every `approval_required`, HTTP / CLI / job approvals — which have no conversation to put
  buttons in — pass through silently; only rows carrying `messaging.chat_id` (or `chat_id`) send.
- **Core constraint is lockstep:** `require.rawphp/laravel-capabilities` is now `self.version` instead of `*`. This package at tag `v0.Y.Z` installs only with core `v0.Y.Z` (and `dev-main` with core `dev-main`). **Hosts:** require the same version of core and this package.

### Added

- **The Telegram approval loop is wired end to end (M-101, D-006 step 4).**
  - *Notify:* the provider tags `TelegramApprovalNotifier` with core's
    `ApprovalNotifier::CONTAINER_TAG` (`capabilities.approval_notifiers`) in addition to the
    contract alias, and core now attaches every tagged notifier to its single `ApprovalManager`.
    Approval rows requested from a chat carry the originating `messaging` meta (channel,
    `chat_id`, `message_id`, …), so the buttons land in that conversation.
  - *Decide:* `ProcessTelegramUpdate` routes `callback_query` updates to the new container-bound
    `Telegram\CallbackHandler` (`handleCallbackData()` decodes the compact token, then
    accept / reject through core's `ApprovalGateway`) and acknowledges the tap with
    `answerCallbackQuery` (`Approved.`, `Rejected.`, `already decided`, `not allowed`,
    `no longer valid`). The token never reaches the host `AgentTurn`; a tap is not an agent
    turn and spends no chat turn budget. With no `ApprovalGateway` bound (core absent) or no
    callback secret, the tap is answered as unavailable and logged — the worker never crashes.
    The handler is built lazily so no secret is required at boot (D-021).
- **Audited tool profile** — Telegram tool calls pass the configured `agent_profile` as the
  `tool_profile` invoke option, so core audit entries record which profile gated the call.
- **Allowlist user check in setup validation** — `TelegramSetup::validate()` / `runOrFail()`
  take an optional host user lookup `(laravelUserId, tenantId) => ?object`. Each
  `identity.allowlist` entry whose `laravel_user_id` does not resolve fails setup loudly
  (index, user id, telegram id in the message) instead of linking a user that does not exist.
  Entries missing `telegram_user_id` or `laravel_user_id` (previously skipped silently at
  runtime) now fail setup with or without a lookup.

### Fixed

- **A linked user can approve or reject from Telegram with the default core wiring (M-301).**
  Core placed the tapping `LinkedUser` by a `tenant_id` attribute it does not have, so every
  approval button answered "You are not allowed to decide this approval." unless the host set
  both `tenant_id` and `current_tenant_id` on its principals. Core now resolves the approver
  with the same `ScopeResolver` that stamped the row and reads `LinkedUser::$tenantId` as
  membership; no messaging change, but this package needs a `rawphp/laravel-capabilities`
  with that fix. A user linked in another tenant still resolves to no one.
- **A redelivered Telegram update never starts a second agent turn** — only a transient reply
  failure was guarded, so a worker timeout, crash or deploy mid-turn (two LLM calls easily pass
  the worker's default 60s) or a repeated webhook ran a fresh turn whose tool calls replayed only
  if the new answer matched the old one. `ProcessTelegramUpdate` now claims a per-update marker
  (`capabilities-messaging:turn:telegram:<chat>:<update_id>`, atomic cache `add`, one hour)
  before the turn; a redelivery that finds it ends as `turn_already_started` (warning, no reply)
  without calling the agent. `ProcessTelegramUpdateJob` declares `$timeout = 120`; the queue
  connection's `retry_after` must be longer.

- **Long and empty agent replies reach the chat** — Telegram rejects text over 4096 characters
  or empty text with a 400, which ended the update with no reply after the agent turn and its
  tool calls had already run. `TelegramAdapter::reply()` now sends long text as consecutive
  messages (split at paragraph, line, then word breaks; new `Support\TelegramText`), and sends
  nothing for blank text. `ProcessTelegramUpdate` replaces an empty answer with `Done.` (or
  `That did not go through (<code>).` after a failed tool call), and a pending reply records how
  many parts were delivered so a retry never repeats earlier parts. The approval message is cut
  with the same UTF-16-aware limit.

- **The Telegram approval message shows what is being approved** — it used to read
  `Approval required: <capability>` and nothing else (no code sets `summary`), so an approver
  had only the agent's chat reply, which is LLM text, to go on. The message now lists the row's
  stored input with sensitive keys redacted (core `Redactor`), one `key: <JSON value>` line
  each, after the optional `summary`, cut to 4096 characters. The buttons are posted into the
  forum topic the request came from instead of General.

- **A Telegram approval tap reports what core actually did** — `CallbackHandler` returned
  `ok` whatever `ApprovalGateway::accept()` / `reject()` answered, so a linked member the approval
  policy refuses, a lost double-tap race, an expired row, or a failed run all showed `Approved.`.
  Non-ok results now map to `forbidden` (the approval stays pending), `already_handled`
  (`conflict` / `expired`), `not_found`, or the new `failed` status (toast `Approved, but the
  action did not complete.`), and the update ends `ok=false` with the core error code.

- **Telegram misconfiguration no longer breaks HTTP / CLI approvals** — `TelegramApprovalNotifier`
  checked the bot token and webhook secret before looking for a chat target, so with
  `telegram.enabled=true` and a secret missing every approval threw, including ones requested
  over HTTP or the CLI. Approvals with no chat target now return before the secret check.

- **A failed reply retries only the send** — a transient Bot API failure (429/5xx) sending the
  reply used to fail the job and re-run the whole update on retry: the chat turn limit again,
  another LLM call through `AgentTurn`, and tool calls that only replayed if the new answer
  matched the old one. `ProcessTelegramUpdate` now keeps the reply in the host cache (key
  `capabilities-messaging:reply:telegram:<chat>:<update_id>`, one hour) before failing the job,
  and the retry sends that reply and nothing else. Without a cache store (container-free
  `MessagingBindings::build()`), a transient reply failure is terminal instead of a second turn.

- **Replies land in the forum topic the user wrote in** — replies (and link confirmations) to a
  message in a forum supergroup topic now set `message_thread_id`, so they no longer land in the
  General topic.

- **Queue workers no longer grow with chat traffic** — `ProcessTelegramUpdate` kept every user
  message and reply in the container-singleton `ThreadStore`, in process memory with no bound,
  and nothing read it back. The `map_thread` step now only derives the id
  (`ThreadStore::threadIdFor()`) and stores nothing. The `thread_id` passed to `AgentTurn` is
  unchanged. **Consumer impact:** messaging keeps no thread history; an `AgentTurn` that needs
  earlier turns stores them itself, keyed by `thread_id`.

- **Link codes and identity links are shared across processes** — `/start <code>` runs on the
  queue worker, but codes and links lived in the process-local `IdentityLinker`, so a code
  issued in a web request never bound and links vanished on restart. They now live behind
  `Identity\LinkStore`: the container binds `CacheLinkStore` on the host cache repository
  (`Illuminate\Contracts\Cache\Repository`), with code TTL from `identity.code_ttl_seconds` and
  an atomic claim so a code binds at most once across workers. `InMemoryLinkStore` stays the
  default for a bare `new IdentityLinker(...)` and `MessagingBindings::build()`. Allowlist
  entries stay in config. **Consumer impact:** links persist in your cache without expiry — use
  a persistent store, or bind `LinkStore` to `new CacheLinkStore(Cache::store(...))`.

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
