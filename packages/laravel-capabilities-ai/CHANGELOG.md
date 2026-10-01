# Changelog

All notable changes to `rawphp/laravel-capabilities-ai` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy (install paths, tags, Packagist checklist):  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

## [0.7.0] - 2026-10-01

### Changed

- **`TurnClaim` contract:** adds `isRunning(string $turnUlid): bool` and drops
  `isCancelled()`, which nothing called. **Hosts** with their own `TurnClaim`
  implement `isRunning()` and may delete `isCancelled()`.

### Fixed

- **A reaped turn stops calling tools.** `TurnRunner` checks the turn is still
  `running` before each model round and each tool call. Once the stale-turn reaper marks
  it failed, or it is cancelled, the next bus invoke does not happen. A call already in
  flight still finishes.

## [0.6.1] - 2026-09-30

No changes.

## [0.6.0] - 2026-09-30

### Added

- **Turn-scoped time budget:** new optional `Contracts\DeadlineAwareLlmClient` (extends `LlmClient`: `withDeadline(int $deadlineNs)`, `MIN_REQUEST_SECONDS = 10`), implemented by `AnthropicLlmClient`. `TurnRunner` (new trailing args `turnBudgetSeconds`, default `claim_ttl` via `ContainerBindings::makeTurnRunner`, and an optional monotonic `clock`) fixes the turn deadline when the job starts and hands it to the client, which caps each request's transport timeout (429 retries included) at the time left minus 2s. A round is refused only when under 10s are left: the turn then fails as a `RetryableLlmException` (`error` `retryable: true` + `terminal` `failed`) without calling the client. With the defaults (110s timeout, 120s `claim_ttl`) a multi-round tool turn whose real total time is under `claim_ttl` completes; later rounds just get shorter timeouts. Previously only the first round was protected: the timeout and retry checks measured from the start of each call, so a multi-round turn could outlive its `claim_ttl` job, get the worker killed mid-request and hang until the stale-turn reaper ran. The `llm.anthropic.timeout < claim_ttl` build check is unchanged. **Hosts:** custom `LlmClient`s get no turn budget unless they implement `DeadlineAwareLlmClient`.

- **Package `LICENSE` (MIT) and `SECURITY.md`** now ship in the package root, so the split `rawphp/laravel-capabilities-ai` remote carries its license notice and a private vulnerability-report path like its siblings.
- **LLM telemetry (D-019):** `AnthropicLlmClient` accepts optional core `Metrics` / `Tracer`. Around the outbound `/v1/messages` call it records `capabilities_ai_llm_duration_ms`, `capabilities_ai_llm_tokens_total{type=input|output}` (from response `usage`), `capabilities_ai_llm_failures_total{reason=transport|http_<status>}` and a `capabilities_ai.llm.complete` span. The service provider passes container-bound core `Metrics` / `Tracer` through `ContainerBindings::makeLlmClient`.
- **Anthropic 429 retry:** `AnthropicLlmClient` retries rate-limited (429) requests up to `llm.anthropic.max_retries` times (default **2**, `CAPABILITIES_AI_ANTHROPIC_MAX_RETRIES`; `0` disables) via Laravel's `Http::retry`, waiting the `Retry-After` seconds (capped at 60) or 1s, 2s, 4s… when the header is missing. A transient rate limit no longer fails the whole turn. Other error statuses are still not retried; exhausted retries throw the same `Anthropic API error: 429 (…)`. Retries stay inside the turn job: `ContainerBindings::makeLlmClient` passes `claim_ttl` as the client's `deadlineSeconds`, a retry runs with its timeout capped to what is left of that deadline, and it is skipped when the wait would leave under 10s. The 429 then fails the turn as a `RetryableLlmException` carrying its `Retry-After`, instead of the worker being killed mid-request.
- **Visible proposal-fence drift:** when `proposals.enabled` and the assistant emits a ```` ```proposal ```` fence whose body is not a decodable JSON object, `TurnRunner` appends a progress event `kind=proposal_invalid` (no proposal is created; the turn still completes). Previously a malformed fence was indistinguishable from no fence. `ProposalFenceExtractor::parse()` returns a typed `ProposalFence` (absent / invalid / valid); `extract()` is unchanged.
- **Per-round LLM usage on turns:** `LlmClient::complete()` may return optional `usage` (`input_tokens`, `output_tokens`). `TurnRunner` stores a `usage` list on the Turn — one `{latency_ms, input_tokens?, output_tokens?}` entry per `complete()` round, latency measured by the runner — on completed, failed, and cooperatively cancelled turns. `AnthropicLlmClient` reports the API's token counts. **Hosts:** run the new `add_usage_to_capabilities_ai_turns_table` migration (idempotent); custom clients need no change.
- **Retryable LLM failures:** new `RetryableLlmException` (extends `RuntimeException`; `status`, `retryAfterSeconds`). `AnthropicLlmClient` throws it for 408 / 409 / 429 / 5xx (incl. 529 overloaded), parsing a seconds `Retry-After`, and for connection failures; 4xx client errors stay plain `RuntimeException`. `TurnRunner` still marks the turn `failed`, but the progress `error` event now carries `retryable` (bool) and, when known, `retry_after_seconds`, and the typed exception is rethrown to the job/caller. **Hosts:** custom `LlmClient`s should throw `RetryableLlmException` for transient provider errors; UIs can offer "try again" on `retryable: true`.
- **`ProgressStoreReadiness` contract** with SP default **`StoreBoundProgressStoreReadiness`**: read-only `since()` ping of the bound `ProgressStore` (Redis in production). Not ready → `isReady()=false` and `ai_progress_store_not_ready_total` (label `store`) on core `Metrics` when bound. Host-prebound readiness is preserved. Read by core `capabilities:integration-health` (`ai_progress_ready`).
- **Per-user chat rate limit (D-013):** `ConversationService::createUserMessage()` enforces `turns_per_minute` (default **20**, `CAPABILITIES_AI_TURNS_PER_MINUTE`; `0` disables) per `userId` through core's `RateLimiter` contract (key `rl:ai:user:{id}`), before any query. Over the limit it throws `TurnRateLimitedException` and `POST messages` returns **429** `rate_limited`; nothing is persisted or dispatched. The provider passes the container-bound core `RateLimiter`; server-side creates without a `userId` are not limited. **Hosts:** on by default — raise or disable `turns_per_minute` if 20 messages/min per user is too low.
- **`ConversationService` persistence behind a port:** new `Contracts\ConversationStore` (conversation / message / queued-turn rows, active-turn counts, close) with the production `Support\EloquentConversationStore`, passed as an optional last constructor arg (and `ContainerBindings::makeConversationService(store: …)`), defaulting to Eloquent. Behaviour is unchanged; conversation rules (capacity, rate limit, closed, ownership, history) are now unit-tested against an in-memory store with no database, and only the Eloquent adapter's SQL is tested on sqlite.
- **Turn, proposal and actor persistence behind ports (no DB in domain tests):** turn claims go through the `Contracts\TurnClaim` port (implementation `Support\EloquentTurnClaim`; see the `TurnClaim` BREAKING entry under Changed), whose compare-and-set transitions now also cover turn cancel and the stale-turn reaper's guarded fails. `Contracts\ConversationStore` gains turn reads (`turn`, `ownedTurn`) and proposal rows (`createProposal`, `proposal`, `proposalOwnedBy`, `transitionProposal`). New `Contracts\ActorLookup` (default `Support\EloquentActorLookup`) wraps `ResolveConversationActor`'s user-model query. `TurnRunner`, `TurnService`, `ProposalService` and `StaleTurnReaper` take these as optional trailing constructor args (Eloquent defaults). The provider binds `ConversationStore` / `TurnClaim` unless the host already bound them, and every service shares those two instances. Behaviour is unchanged. All turn, proposal, cancel, reaper, job and chat HTTP rules are now unit-tested against in-memory fakes. Only the two Eloquent adapters' SQL/CAS tests and the migration tests still use sqlite.

### Changed

- **Core constraint is lockstep:** `require.rawphp/laravel-capabilities` is now `self.version` instead of `*`. This package at tag `v0.Y.Z` installs only with core `v0.Y.Z` (and `dev-main` with core `dev-main`). **Hosts:** require the same version of core and this package.
- **BREAKING — `TurnClaim` is an interface:** the final class `Domain\TurnClaim` is replaced by the interface `Contracts\TurnClaim`, implemented by `Support\EloquentTurnClaim`, so `new TurnClaim(...)` no longer works. `claim()` returns `bool` (was `?Turn`); load the turn from `ConversationStore::turn()` after a successful claim. `Support\DatabaseConnection` is removed. **Hosts:** replace `new TurnClaim` with `new EloquentTurnClaim`, type-hint `Rawphp\CapabilitiesAi\Contracts\TurnClaim`, and treat `claim()`'s result as a bool.
- **BREAKING — chat message owner is the authenticated user (D-022):** `ChatController::storeMessage` sets `conversation.user_id` from `$request->user()->getAuthIdentifier()` and ignores any body `user_id`. No authenticated user → **401**, nothing created. Posting to a `conversation_ulid` owned by another user → **404** (`ConversationService::createUserMessage` scopes an existing conversation to `userId` when one is given). Previously any authenticated caller could name another user as owner, and every turn tool call / proposal accept then ran as that user. **Hosts** that create conversations for another user (integrations, back-office) call `ConversationService::createUserMessage(userId: …)` from server code instead of the package route.
- **BREAKING — chat HTTP is scoped to the authenticated user (D-022 / D-003):** `ConversationService::history()`/`destroy()` and `TurnService::show()`/`cancel()`/`events()` take a required `$ownerId` and only find conversations/turns whose `conversation.user_id` matches; `createUserMessage()` only appends to a conversation owned by `$userId`. `ChatController` takes the owner from `$request->user()->getAuthIdentifier()`: no authenticated user → **401**, another user's (or an ownerless) conversation/turn → **404**, and `POST messages` **ignores body `user_id`**. Previously any authenticated caller could read, cancel, or close any conversation/turn by ULID, and could create conversations owned by (and invoking capabilities as) any user id. **Hosts:** route middleware must authenticate a user; callers of the services pass the owner id.
- **Breaking — chat HTTP error envelope (D-018):** `ChatController` 404 / 409 branches (history, message create, turn show/cancel/events, conversation destroy, proposal accept/reject) now return the core capability error envelope (`ok: false`, `error.code` = `not_found` / `conflict`, `error.message`, `retryable`, `http_status`, `cli_exit`, `meta`) instead of a bare `{message}` body. Message create with an unknown `conversation_ulid` now returns 404 `not_found` instead of an uncaught 500. **Hosts:** read `error.message` / `error.code`, not top-level `message`.
- **BREAKING — proposal accept/reject are owner-only (D-022 / D-003):** `ChatController::acceptProposal()`/`rejectProposal()` take the `Request` and act as `$request->user()->getAuthIdentifier()`: no authenticated user → **401**; a proposal whose conversation belongs to another user (or has no owner) → **404** before `ProposalService` runs (no status change, no bus invoke). New `ProposalService::ownedBy($proposalUlid, $ownerId)`. Previously any authenticated caller could accept (invoking the capability as the conversation owner) or reject any proposal by ULID. **Hosts:** proposal routes must authenticate the conversation owner.
- **Proposal accept stays inside the tool profile (D-008):** `ProposalService` takes an optional host `ToolCatalog` (SP passes the bound one) and, on every accept execute, requires `target_capability` to be in `toolsForTurn(conversation, turn)`. Outside the profile — including a profile narrowed after the proposal was made — or no `ToolCatalog` bound → proposal `failed`, `AcceptOutcome::refuse` **403** `capability_not_in_profile`, no bus invoke. **Hosts:** a capability the model may propose must be in that turn's tool list; proposal-only targets outside it now refuse.
- **Turn tool calls stay inside the offered tool list (D-008):** `TurnRunner` checks each model `tool_call` name against the `toolsForTurn()` list it sent the LLM for that turn. A name outside it (hallucinated, stale, or never offered) gets a `capability_not_in_profile` tool result the model can react to, is recorded as a failed `tool` progress event, and never reaches `CapabilityBus::invoke` or the `agent_turn_tool_calls` count. **Hosts:** every capability the model may call must be in that turn's `ToolCatalog` list.
- **BREAKING — turn tool calls invoke as `caller=agent`:** `TurnRunner` now passes `caller=agent` (was `job`) on every LLM-chosen bus invoke, per D-022 (in-process AI adapter). The core agent surface flag, per-capability `surfaces` narrowing and `needsApproval` rules keyed on `agent` now apply to AI turns, as they already did for messaging turns. `ProposalService` accept keeps `caller=job`. `ResolveConversationActor::invokeOptions()` now takes the caller explicitly (`CALLER_AGENT` / `CALLER_JOB`) and `$extra` can no longer override caller or actor. **Hosts:** capabilities the model should call must allow the `agent` surface.
- **BREAKING — every chat error uses the D-018 envelope:** `ChatController` 401 now returns `error.code=unauthenticated`, 422 returns `validation_failed` with field errors in `error.violations` (`[{field, message}]`), and the `max_concurrent_turns` 429 returns `rate_limited` (`retryable: true`) — all as `{ok: false, error: {...}, meta}` like 404/409 already did. Previously these were three ad-hoc bodies (`{message}`, `{message, errors}`, `{message, outcome: retryable}`). **Clients:** branch on `error.code`. Proposal accept bodies (`outcome`) are unchanged.

### Fixed

- **Long messages fit, oversize input is a 422, and the Anthropic timeout must fit the turn job:** new migration `2026_09_30_000001_widen_capabilities_ai_messages_content` changes `messages.content` from `text` to `longText`; on MySQL a reply over 65,535 bytes (about 16k tokens) failed the insert after the model call and its tool invokes had run. `POST messages` rejects `content` longer than the new `max_message_chars` (default **32000** characters, `CAPABILITIES_AI_MAX_MESSAGE_CHARS`; `0` = no cap) with **422** `validation_failed` and creates nothing. `ContainerBindings::makeLlmClient` now throws `InvalidArgumentException` when `llm.anthropic.timeout` is not below `claim_ttl` (the turn job timeout), which the config previously only asked for in a comment. `llm.anthropic.max_tokens` keeps its **64000** host-parity default and is documented as a ceiling: with non-streaming requests, reply length is bounded by `timeout`. **Hosts:** run the migration; if you raised `timeout`, raise `claim_ttl` above it.
- **A turn that hits `max_tool_rounds` fails instead of completing empty:** when every round up to `max_tool_rounds` returned tool calls, `TurnRunner` left the loop and marked the turn `completed` with no assistant message, so a looping model looked like a successful turn with no reply. It now fails the turn (`error` event `max_tool_rounds (N) reached without a final reply`, `retryable: false`, then a `failed` terminal); usage for every round is kept.
- **`CAPABILITIES_AI_ALLOW_UNSAFE=no` no longer opens the escape hatch:** the package config cast the raw value with `(bool)`, so `no`, `off` or `false`-like words other than `false` turned `allow_unsafe` on and let `progress.driver=array` / `llm.driver=fake` run outside testing. `allow_unsafe` is now parsed as a boolean (`1`, `true`, `yes`, `on` enable it; anything else keeps it closed), and the service provider reads only the `allow_unsafe` config key instead of also re-reading the process env, so cached config is honoured. **Hosts** with an older published config should re-publish it or set `allow_unsafe` explicitly.
- **Chat `storeMessage` validates its body:** `POST messages` now requires `content` to be a non-empty string and an optional `conversation_ulid` to be a 26-character uppercase ULID. Invalid input returns **422** `{message, errors}` before any conversation, message, or turn row is created or a turn job is dispatched; a well-formed but unknown `conversation_ulid` returns **404** instead of a 500. Previously an empty body queued an empty-message turn.
- **Redis progress indexes under concurrent appends:** `RedisProgressStore` now takes each event's `index` from its position in the Redis list instead of counting the list before `rPush`. Two appends for the same turn (retried job, live worker, reaper) could read the same count and store the same index, so an SSE client resuming with `since($cursor)` skipped or repeated events. `append()` no longer reads the list first.
- **Agent turn budget (D-013) now caps AI turns:** `TurnRunner` passes a 1-based per-turn `agent_turn_tool_calls` count on every bus invoke, so core `rate_limits.agent_turn.max_tool_calls` limits total tool calls per turn (the model gets a structured `rate_limited` result past the cap). Previously only `max_tool_rounds` bounded AI turns.
- **Proposal accept with an unresolvable actor:** when the conversation owner is missing or deleted between proposal and accept, `ProposalService::accept` now marks the proposal `failed` and returns `AcceptOutcome::refuse` (HTTP 403, `forbidden`) instead of throwing after the pending→accepting claim (which left the proposal stuck in `accepting` and surfaced as a 500). Resolver misconfiguration (no usable user model) still throws and leaves the proposal `accepting`, so it can be re-driven after the config fix. New `UnresolvedConversationActorException` (extends `RuntimeException`) marks the principal-refusal case.
- **Cancel stops a running turn:** `TurnRunner` re-checks the turn status before each LLM round and before each tool call and stops once it is `cancelled` (usage so far is kept on the turn). Previously it only looked after the loop, so a cancelled turn kept calling the LLM and invoking (possibly mutating) capabilities for up to `max_tool_rounds`, appending tool events after the cancelled terminal. The completed/failed writes are now compare-and-set on `status=running` (`TurnClaim::complete()` / `fail()`), so a cancel landing just before them is no longer overwritten.
- **Queued progress event precedes the turn's own events:** `ConversationService::createUserMessage()` appends `status=queued` before dispatching `RunTurnJob`. Previously it appended after dispatch, so with the sync queue driver (or a fast worker) the stream ended with `queued` after `running`/`terminal`.
- **Closed conversations stay closed:** `ConversationService::createUserMessage()` throws `ConversationClosedException` for a conversation closed by `destroy()`, before creating a message or dispatching a turn; `POST messages` maps it to **409** `conflict`. Previously a closed conversation silently accepted new messages and paid LLM turns.
- **Redis progress keys expire and polls read from the cursor:** `RedisProgressStore` sets `EXPIRE` on the turn key after every append (`progress.ttl_seconds`, default **86400**, `CAPABILITIES_AI_PROGRESS_TTL`) and `since($turn, $cursor)` issues `LRANGE key cursor -1` instead of downloading the whole list. Previously every turn left a permanent key and each poll cost grew with turn length. **Hosts:** a custom Redis client passed to the store must support `expire` (ext-redis, predis and Laravel connections do).
- **Anthropic request timeout:** `AnthropicLlmClient` now sets an explicit per-request timeout, `llm.anthropic.timeout` (default **110** s, `CAPABILITIES_AI_ANTHROPIC_TIMEOUT`), passed through `ContainerBindings::makeLlmClient`. Previously Laravel's 30s HTTP client default applied, so any reply that took longer to generate (likely with `max_tokens=64000`) failed the turn as a retryable connection error. Keep it below `claim_ttl`; non-positive values throw.
- **`table_prefix` honours the host config:** `TableNames` (models + migrations) now reads `config('capabilities-ai.table_prefix')`, so a published/overridden value and `php artisan config:cache` are respected. Previously it re-required the package's own config file and read `CAPABILITIES_AI_TABLE_PREFIX` via `getenv` on every call, so cached-config workers could query unprefixed tables that migrations had created prefixed. The package file is now only the fallback when no config repository is bound.

## [0.5.1] - 2026-08-09

Also collects entries first shipped in earlier tags (`v0.2.0` through `v0.5.0`), which did not
get per-tag sections; this file's git history shows the tag each entry first shipped in.

### Fixed

- **Anthropic dotted tool names:** `AnthropicLlmClient` encodes package/capability ids for the Anthropic wire (`pane.list` → `pane__list`, matching `^[a-zA-Z0-9_-]{1,128}$`) and decodes on inbound `tool_use` so `TurnRunner` still invokes the bus by capability name. Without this, hosts that advertise dotted tools (LivePane Assistant) failed every turn with Anthropic 400 `tools.N.custom.name` pattern errors.
- **Bus invoke principal (ORI-775):** `TurnRunner` tool invokes and `ProposalService` accept invokes now pass `caller=job` + conversation User as `actor` (legacy coach / `RunCoachCommandHandler` shape). Missing or unresolvable `conversation.user_id` fails closed (no `ResolveActor::defaultUser()` / silent id=1). Config: `capabilities-ai.user_model` (fallback `auth.providers.users.model`).
- **Redis progress under Laravel phpredis (coach turns):** `resolveRedisClientOrNull` now unwraps the Illuminate Redis connection to the native ext-redis/predis client (`connection()->client()`). `RedisProgressStore` also accepts Laravel connection wrappers that only expose `rpush`/`lrange` via `__call`. Without this, hosts with `CAPABILITIES_AI_PROGRESS_DRIVER=redis` failed every turn with `Redis client missing rPush` (SSE progress never appended; coach chat returned temporary-problem failures).

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

`LlmClient` now **requires** `supportsToolRounds(): bool`. Custom host implementors fail type-check / runtime until they add the method or `use LlmClientDefaults` (returns **false**). Full host upgrade: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-llmclient--tool-rounds).

| Client | `supportsToolRounds()` | Effect |
|--------|------------------------|--------|
| Host custom (no method) | **Break** until implemented | PHP interface missing method |
| `LlmClientDefaults` trait | **false** | Fail-closed default for hosts |
| `AnthropicLlmClient` | **true** (override) | Tools advertised; multi-round `tool_result` / `tool_use` supported |
| `FakeLlmClient` | **true** | Multi-round tool unit tests opt in |

Honesty rule: return **true** only if the next `complete()` accepts tool-result messages (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Lying opens bus-then-crash after mutation.

#### Tool progress + tool-role message content

Progress `kind=tool` events and multi-round tool-role message `content` are **honest bus wire** (0.x pre-stable). Hosts that assumed always-ok tool content or a `{name,payload}`-only progress shape must adapt. Full tables: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-tool-progress--tool-messages).

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

Constructor and job-handle DI tightened for hosts that construct services outside the package service provider (0.x pre-stable). Preferred path remains **`CapabilitiesAiServiceProvider` / `ContainerBindings`** — manual `new` is advanced. Full host upgrade: [docs/user-guide.md](docs/user-guide.md#upgrade-for-hosts-manual-di--constructor--job-handle).

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

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.
Tags without their own section recorded no entries for this package.

[Unreleased]: https://github.com/rawphp/laravel-capabilities-ai/compare/HEAD...HEAD
