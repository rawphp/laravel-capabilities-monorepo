# rawphp/laravel-capabilities-ai

> **Status:** 0.x pre-stable — **not Packagist-published**.  
> **Install:** package VCS or monorepo path.

Conversation / turn / proposal runtime for the [Laravel Capabilities](https://github.com/rawphp/laravel-capabilities) bus.

**Monorepo path:** `packages/laravel-capabilities-ai/` in [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo).

## Scope (this package)

| | |
|---|---|
| **Is** | Optional **turn / proposal runtime**: queue a turn, claim it, loop LLM → tools, stream progress (array/Redis); tool side effects **only** via `CapabilityBus::invoke`; host seams for conversation context and tool catalog; thin `LlmClient` (fake + Anthropic) for turns and host completions that must not embed domain rules |
| **Multimodal** | User message `content` on `ConversationContextProvider` / `LlmClient` may be a **string** or a **list of provider content blocks** (text + base64 image for Anthropic vision). **Hosts hydrate attachment bytes into context** — this package does **not** store, claim, or fetch chat attachment files. End-to-end photo coach flows still need host upload + context hydration. |
| **Is not** | The capability bus / registry; chat channel bots (use [messaging](https://github.com/rawphp/laravel-capabilities-messaging)); product CLI; a general app-wide LLM SDK replacing `laravel/ai`; domain `run()`; generative UI or agent-native OS |

Requires [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) at the **same version** (`self.version` lockstep: this package at `v0.Y.Z` installs only with core `v0.Y.Z`). Consumers install **this package repo**, not the monorepo.

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| **Upgrade (accept/reject wire)** | [docs/user-guide.md#upgrade-for-hosts-acceptreject-wire](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#proposal-acceptreject-wire) |
| **Upgrade (chat HTTP non-proposal routes)** | [docs/user-guide.md#upgrade-for-hosts-chat-http-non-proposal-routes](docs/user-guide.md#upgrade-for-hosts-chat-http-non-proposal-routes) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#chat-http-non-proposal-routes-stub--real) (history / showTurn / cancelTurn / turnEvents / destroyConversation; **404** / **409**; `routes.enabled`) |
| **Upgrade (LlmClient / tool rounds)** | [docs/user-guide.md#upgrade-for-hosts-llmclient--tool-rounds](docs/user-guide.md#upgrade-for-hosts-llmclient--tool-rounds) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#llmclient-supportstoolrounds-compile--runtime) |
| **Upgrade (tool progress + tool messages)** | [docs/user-guide.md#upgrade-for-hosts-tool-progress--tool-messages](docs/user-guide.md#upgrade-for-hosts-tool-progress--tool-messages) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#tool-progress--tool-role-message-content) |
| **Upgrade (Anthropic default model ID)** | [docs/user-guide.md#upgrade-for-hosts-anthropic-default-model-id](docs/user-guide.md#upgrade-for-hosts-anthropic-default-model-id) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#anthropic-default-model-id) |
| **Upgrade (manual DI / constructor / job handle)** | [docs/user-guide.md#upgrade-for-hosts-manual-di--constructor--job-handle](docs/user-guide.md#upgrade-for-hosts-manual-di--constructor--job-handle) · [CHANGELOG 0.5.1 Breaking](CHANGELOG.md#manual-di--constructor--job-handle) |
| Core package | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) |
| Messaging sibling | [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |

## Install

```bash
composer require rawphp/laravel-capabilities-ai
```

`Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider` is auto-discovered (`extra.laravel.providers`).

**Tests and contributions:** the unit suite, `phpunit.xml`, and dev tooling live only in the [monorepo](https://github.com/rawphp/laravel-capabilities-monorepo); this package remote is a read-only split and ships no tests. Open issues and PRs against the monorepo and run `composer test:ai` there.

## Config

Publish:

```bash
php artisan vendor:publish --tag=capabilities-ai-config
php artisan vendor:publish --tag=capabilities-ai-migrations
```

Key defaults (`config/capabilities-ai.php`):

| Key | Default |
|-----|---------|
| `table_prefix` | `capabilities_ai_` |
| `progress.driver` | `array` (or `redis`) — prod: **`redis`**; `array` outside testing throws unless `CAPABILITIES_AI_ALLOW_UNSAFE=1` |
| `progress.redis_connection` / `progress.redis_key_prefix` | `default` (`CAPABILITIES_AI_PROGRESS_REDIS`) / `capabilities_ai:progress:` (`CAPABILITIES_AI_PROGRESS_PREFIX`) |
| `progress.ttl_seconds` | `86400` (`CAPABILITIES_AI_PROGRESS_TTL`) — Redis progress key lifetime after a turn's last event |
| `llm.driver` | `fake` (set `CAPABILITIES_AI_LLM_DRIVER=anthropic` or bind `LlmClient` for production) — `fake` outside testing throws unless `CAPABILITIES_AI_ALLOW_UNSAFE=1` |
| `llm.anthropic.api_key` | `ANTHROPIC_API_KEY` |
| `llm.anthropic.model` | `claude-sonnet-4-6` (`CAPABILITIES_AI_ANTHROPIC_MODEL`) |
| `llm.anthropic.base_url` | `https://api.anthropic.com` (`CAPABILITIES_AI_ANTHROPIC_BASE_URL`) |
| `llm.anthropic.max_tokens` | `64000` (`CAPABILITIES_AI_ANTHROPIC_MAX_TOKENS`) — a ceiling, not a target. Requests are non-streaming, so a turn only gets what the model writes within `llm.anthropic.timeout`; a reply that needs longer fails the turn as a retryable timeout. For very long replies raise `timeout` and `claim_ttl` together |
| `llm.anthropic.max_retries` | `2` (`CAPABILITIES_AI_ANTHROPIC_MAX_RETRIES`) — Anthropic 429 retries per request; waits `Retry-After` seconds (capped at 60) or 1s, 2s, 4s…; `0` disables. A retry runs with its `timeout` capped to what is left of the turn's `claim_ttl` (counted from the job start, across all rounds) and is skipped when the wait would leave under 10s; the 429 then fails the turn as retryable |
| `user_model` | null → falls back to `auth.providers.users.model` (`CAPABILITIES_AI_USER_MODEL`) |
| `llm.anthropic.timeout` | `110` (`CAPABILITIES_AI_ANTHROPIC_TIMEOUT`) — seconds per Anthropic request (Laravel's HTTP default is 30s). Must be below `claim_ttl`, or the anthropic `LlmClient` refuses to build (`InvalidArgumentException`); a turn with several tool rounds makes several requests inside one job timeout. Each request's `timeout` is capped to what is left of `claim_ttl` minus 2s, so the worker is never killed mid-request; a round is refused, failing the turn as retryable (`error` + `terminal` events), only when under 10s are left. Raise `claim_ttl` for long multi-round turns |
| `claim_ttl` | **`120`** (`CAPABILITIES_AI_CLAIM_TTL`) — seconds; worker heartbeat and `RunTurnJob` timeout, so the budget for the **whole turn** (every LLM round and 429 retry). `TurnRunner` hands it to a `DeadlineAwareLlmClient` as the turn deadline |
| `queue.connection` | null (`CAPABILITIES_AI_QUEUE_CONNECTION`) — applied to default `RunTurnJob` dispatch when set |
| `queue.name` | null (`CAPABILITIES_AI_QUEUE_NAME`) — applied to default dispatch; also marks **AI-chat** for core `capabilities:integration-health` when non-empty |
| `proposals.enabled` | `true` Phase-1 BC (`CAPABILITIES_AI_PROPOSALS_ENABLED`) — **greenfield: set `false`** |
| `reaper.stale_queued_minutes` | `30` (`CAPABILITIES_AI_REAPER_STALE_QUEUED`) |
| `reaper.stale_running_grace_seconds` | `60` (`CAPABILITIES_AI_REAPER_RUNNING_GRACE`) |
| `allow_unsafe` | `false` (`CAPABILITIES_AI_ALLOW_UNSAFE`) — local demos only |
| `turns_per_minute` | `20` (`CAPABILITIES_AI_TURNS_PER_MINUTE`) — D-013 per-user message (turn) budget via core `RateLimiter`; over it, message create returns **429** `rate_limited` and persists/dispatches nothing; `0` disables |
| `max_concurrent_turns` | `0` = unlimited (`CAPABILITIES_AI_MAX_CONCURRENT_TURNS`) — at the ceiling of queued + running turns, message create returns **429** `rate_limited` (D-018 envelope, `retryable: true`) and persists/dispatches nothing |
| `max_message_chars` | `32000` (`CAPABILITIES_AI_MAX_MESSAGE_CHARS`) — longest accepted chat message `content` in characters; longer → **422** `validation_failed`, nothing persisted or dispatched; `0` = no cap |
| `max_tool_rounds` | `8` (`CAPABILITIES_AI_MAX_TOOL_ROUNDS`) — LLM rounds per turn; a turn still asking for tools after the last round **fails** (`max_tool_rounds (N) reached without a final reply`, `retryable: false`) |
| `routes.enabled` | `false` (`CAPABILITIES_AI_ROUTES_ENABLED`) |
| `routes.prefix` | `capabilities-ai/chat` (`CAPABILITIES_AI_ROUTE_PREFIX`) |
| `routes.middleware` | `['api', 'auth:sanctum']` — must authenticate a user; every chat route acts as `$request->user()` |

Progress events live in array/Redis — **not** MySQL product tables.

**Bus principal (tool + accept invokes):** `TurnRunner` and `ProposalService` resolve the conversation’s Laravel user via `user_model` / auth provider and pass that user as `actor` on `CapabilityBus::invoke`. Turn tool calls use `caller=agent` (D-022), so the agent surface flag, per-capability `surfaces` narrowing and agent approval rules apply; proposal accept uses `caller=job`. Missing/unresolvable `conversation.user_id` fails closed (no silent default user). When `CapabilityBus` is bound, provider boot also fails closed if the user model is unset, missing, or has no `query()` (class check only, no DB). Tool invokes pass `idempotency_key` only when the model supplies it as a tool argument (D-005; stripped from capability input) — otherwise they carry no key. Each tool invoke also passes a 1-based `agent_turn_tool_calls` count, so core `rate_limits.agent_turn.max_tool_calls` (D-013) caps tool calls per turn; past it the model gets a `rate_limited` tool result.

### Host integration (D-024 seams)

Happy-path AI-chat hosts **configure** — they do not rebind package runtime for queue or progress:

| Seam | Do | Do not |
|------|----|--------|
| Queue | Set `CAPABILITIES_AI_QUEUE_NAME` / `CAPABILITIES_AI_QUEUE_CONNECTION` | Full `ConversationService` rebind only to pick a queue |
| Progress side-effects | `app()->extend(ProgressStore::class, …)` in **`boot()`** after package bind | `singleton(ProgressStore::class, …)` replacing redis/array |
| Idempotency readiness | Leave SP default **`StoreBoundIdempotencyReadiness`** (live core store ping; fail closed when unbound) | Bind **`AlwaysReadyIdempotency`** in production (tests-only) |
| Progress readiness | Leave SP default **`StoreBoundProgressStoreReadiness`** (read-only ping of the bound `ProgressStore`; increments `ai_progress_store_not_ready_total` on core `Metrics` when down) | Treat a skipped `ai_progress_ready` health row as proof Redis is reachable |
| Proposals | `CAPABILITIES_AI_PROPOSALS_ENABLED=false` on greenfield | Assume routes-only gate — flag also skips TurnRunner fence extract + history proposals |
| Stale turns | Schedule `php artisan capabilities-ai:reap-stale-turns` | Host reapers on wrong tables / dual chat stores without a kill date |
| Product HTTP UX | **Host routes** → bus / AI services | Package route surgery or hijacking package chat HTTP for product UX |
| Diagnostics | Core `php artisan capabilities:integration-health` | Confuse with HTTP `GET …/capabilities/health` |

Full greenfield checklist, kill-list template, and extend snippet: [docs/user-guide.md](docs/user-guide.md#host-integration-greenfield).

## Host seams

Bind before running turns:

- `Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider` — messages for the model
- `Rawphp\CapabilitiesAi\Contracts\ToolCatalog` — tools the model may call (names = capability names). TurnRunner resolves the list once per turn; a `tool_call` for a name outside it is answered with a `capability_not_in_profile` tool result and never reaches the bus (D-008). `AnthropicLlmClient` sends dotted names as `__` (`pane.list` → `pane__list`) and decodes them on `tool_use`; a name that cannot round-trip (it contains `__`, or `_` next to `.`) throws `InvalidArgumentException` and fails the turn rather than risk invoking a different capability
- `Rawphp\Capabilities\Contracts\CapabilityBus` — already provided by core

```php
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;

// Testing default
$app->bind(LlmClient::class, fn () => new FakeLlmClient);

// Production: prefer CAPABILITIES_AI_LLM_DRIVER=anthropic, which builds the client from every
// llm.anthropic.* key and claim_ttl. A manual bind like this one uses constructor defaults
// (timeout 110s, 2 retries, 120s deadline) for anything you do not pass.
$app->bind(LlmClient::class, fn () => new AnthropicLlmClient(
    apiKey: config('capabilities-ai.llm.anthropic.api_key'),
    model: config('capabilities-ai.llm.anthropic.model'),
));
```

**Custom `LlmClient`:** implement `supportsToolRounds()`. Prefer `use LlmClientDefaults` (returns false) and override to `true` **only** if the client accepts tool-result messages on the next `complete()` (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Lying opens a bus-then-crash path. (PHP interfaces still cannot ship method bodies on supported PHP; the trait is the fail-closed default for hosts.) **Host upgrade callouts:** [user guide](docs/user-guide.md#upgrade-for-hosts-llmclient--tool-rounds) · [CHANGELOG Breaking](CHANGELOG.md).

**Turn time budget:** a custom client can implement `Contracts\DeadlineAwareLlmClient` (`withDeadline()`: cap every request and retry to end before the turn deadline, and start none with under `MIN_REQUEST_SECONDS` left) so `TurnRunner` holds its rounds and retries to `claim_ttl` the way it does for `AnthropicLlmClient`. Clients without it get no turn budget: keep their total turn time under `claim_ttl` yourself.

**Transient LLM errors:** throw `RetryableLlmException` (rate limit, overload, 5xx, connection) from `complete()`; `AnthropicLlmClient` already does. The turn still ends `failed`, but its progress `error` event carries `retryable: true` (+ `retry_after_seconds` when the provider sent one) so callers can try again; other errors report `retryable: false`.

**Usage and telemetry:** `LlmClient::complete()` may return `usage` (`input_tokens`, `output_tokens`). `TurnRunner` stores a `usage` list on the turn row, one `{latency_ms, input_tokens?, output_tokens?}` entry per round, on completed, failed and cancelled turns (run the `add_usage_to_capabilities_ai_turns_table` migration). When core `Metrics` / `Tracer` are bound, `AnthropicLlmClient` records `capabilities_ai_llm_duration_ms`, `capabilities_ai_llm_tokens_total{type=input|output}`, `capabilities_ai_llm_failures_total{reason=transport|http_<status>}` and a `capabilities_ai.llm.complete` span (D-019).

**MVS product default:** multi-round tools are **off** until a client opts in. `AnthropicLlmClient` and `FakeLlmClient` opt in (`supportsToolRounds() === true`); hosts using `LlmClientDefaults` stay fail-closed until they override. Empty tool defs + refuse-before-bus is defense-in-depth for non-tool-round clients, not a second product surface.

**Proposals (single accept/reject model):** Gated by **`proposals.enabled`** (`CAPABILITIES_AI_PROPOSALS_ENABLED`). When **false**: accept/reject routes are not registered, TurnRunner **skips** fence → proposal extract, and history omits/empties proposals. When **true**: Accept returns typed `AcceptOutcome` for every known status (rejected/expired → `refuse`); HTTP maps outcomes + 404 when missing. Reject uses CAS + RuntimeException → 409 for non-pending. **Greenfield:** set `false` until you need proposals. **Host upgrade callouts:** [user guide](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire) · [CHANGELOG Breaking](CHANGELOG.md).

- **Accept:** atomic CAS `pending → accepting`, then `target_capability` must be in the host `ToolCatalog` list for the proposal's turn (D-008; checked on every execute, no catalog bound → refuse) or the proposal fails with **403** `capability_not_in_profile` and no invoke; a fenced proposal stamps `schema_hash` (sha256 of the target tool's `parameters`) at creation, and if the live tool schema no longer matches, accept fails with **409** `conflict` (`reason: schema_changed`) and no invoke, so schema drift is not reported as a malformed payload (null hash on legacy rows skips the check); then bus invoke with `idempotency_key=proposal:{ulid}` (D-005). Live **`StoreBoundIdempotencyReadiness`** probe of core `IdempotencyStore` (fail closed when unbound) — not a constructor stamp; **`AlwaysReadyIdempotency` is unit-tests only**. Branch `isApprovalRequired()` then `isHardRefuse()` then `isRetryable()`; approval/retry leave status `accepting` for host re-drive. Hard non-retryable → `failed` + `last_error`. Success → atomic `accepting → accepted`, clear `last_error`. Returns typed `AcceptOutcome` (`accepted` | `approval_required` | `retryable` | `failed` | `refuse`).
- **Reject:** atomic CAS `pending → rejected` only; already-rejected is idempotent; accepting/accepted/failed/expired refuse (HTTP 409).
- **Recovery:** stuck `accepting` is intentional (approval / retry / crash mid-accept). Package does **not** TTL-expire or reclaim; host re-drives accept under the same D-005 key (`proposal:{ulid}`). Hosts must wire core **`IdempotencyStore`** (not an AI-package store) so the bus actually dedupes; readiness not ready → 503 without invoke. Tool bus invokes carry an `idempotency_key` only when the model passes one as a tool argument; accept always sets the proposal key. Both tool and accept invokes still carry the job+user principal (above).
- **Stale turns:** schedule `php artisan capabilities-ai:reap-stale-turns` (host owns the schedule; package does not auto-schedule). Thresholds: `reaper.stale_queued_minutes`, `reaper.stale_running_grace_seconds` (running age uses max(`claim_ttl`, grace)). Each run increments `capabilities_ai_reaped_turns_total{status=queued|running}` on core's `Metrics` contract (D-019), so alert on its rate rather than console output.

Env: `ANTHROPIC_API_KEY` (never required in CI — tests use `Http::fake` / `FakeLlmClient`).

## Flow

1. **Cheap create** — `ConversationService::createUserMessage` inserts message + queued turn, dispatches `RunTurnJob` (**no LLM**).
2. **Claim + run** — `TurnClaim` atomic update; `TurnRunner` loops LLM → tools via `CapabilityBus::invoke` only.
3. **Proposals** — `ProposalService::accept` / `reject` as above (bus-only side effects on accept).

## ProgressStore

Package binds `ProgressStore` in `register()` when unbound (`array` or `redis` from config). Hosts that need side-effects (TTS, metrics, …) **must wrap with `extend` in `boot()`** so the package store is the `$inner`:

```php
// AppServiceProvider::boot — after CapabilitiesAiServiceProvider has registered
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;

$this->app->extend(ProgressStore::class, function (ProgressStore $inner, $app) {
    return new TtsDispatchingProgressStore($inner, $app->make(TtsService::class));
});
```

**Forbidden:** host `singleton(ProgressStore::class, …)` that replaces redis/array wiring (rebind, not extend).

```php
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\RedisProgressStore;

$store = new ArrayProgressStore;
$store->append($turnUlid, ['kind' => 'status', 'data' => ['status' => 'running']]);
$events = $store->since($turnUlid, $cursor);
```

Kinds: `status` | `token` | `tool` | `proposal_invalid` | `error` | `terminal`.

## License

MIT

## Non-chat / MVS host jobs

Hosts may resolve `LlmClient` **without** a Conversation (e.g. Macro Validation Suite jobs):

```php
/** @var \Rawphp\CapabilitiesAi\Contracts\LlmClient $llm */
$llm = app(\Rawphp\CapabilitiesAi\Contracts\LlmClient::class);
$result = $llm->complete([
    ['role' => 'user', 'content' => 'Summarize this payload…'],
]);
```

The `LlmClient` interface has **no conversation-only dependency**. Testing default is `FakeLlmClient` (no network).
