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

Requires [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities). Consumers install **this package repo**, not the monorepo.

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| **Upgrade (accept/reject wire)** | [docs/user-guide.md#upgrade-for-hosts-acceptreject-wire](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire) · [CHANGELOG Unreleased Breaking](CHANGELOG.md) |
| **Upgrade (chat HTTP non-proposal routes)** | [docs/user-guide.md#upgrade-for-hosts-chat-http-non-proposal-routes](docs/user-guide.md#upgrade-for-hosts-chat-http-non-proposal-routes) · [CHANGELOG Unreleased Breaking](CHANGELOG.md) (history / showTurn / cancelTurn / turnEvents / destroyConversation; **404** / **409**; `routes.enabled`) |
| **Upgrade (LlmClient / tool rounds)** | [docs/user-guide.md#upgrade-for-hosts-llmclient-tool-rounds](docs/user-guide.md#upgrade-for-hosts-llmclient-tool-rounds) · [CHANGELOG Unreleased Breaking](CHANGELOG.md) |
| **Upgrade (tool progress + tool messages)** | [docs/user-guide.md#upgrade-for-hosts-tool-progress-and-tool-messages](docs/user-guide.md#upgrade-for-hosts-tool-progress-and-tool-messages) · [CHANGELOG Unreleased Breaking](CHANGELOG.md) |
| **Upgrade (Anthropic default model ID)** | [docs/user-guide.md#upgrade-for-hosts-anthropic-default-model-id](docs/user-guide.md#upgrade-for-hosts-anthropic-default-model-id) · [CHANGELOG Unreleased Breaking](CHANGELOG.md) |
| **Upgrade (manual DI / constructor / job handle)** | [docs/user-guide.md#upgrade-for-hosts-manual-di-constructor-job-handle](docs/user-guide.md#upgrade-for-hosts-manual-di-constructor-job-handle) · [CHANGELOG Unreleased Breaking](CHANGELOG.md#manual-di--constructor--job-handle) |
| Core package | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) |
| Messaging sibling | [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |

## Install (path package)

```bash
# monorepo root already path-wires this package
composer update rawphp/laravel-capabilities-ai
composer test:ai
```

Host app: require `rawphp/laravel-capabilities-ai` and register `Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider` (auto-discovery via `extra.laravel.providers`).

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
| `llm.driver` | `fake` (set `CAPABILITIES_AI_LLM_DRIVER=anthropic` or bind `LlmClient` for production) — `fake` outside testing throws unless `CAPABILITIES_AI_ALLOW_UNSAFE=1` |
| `llm.anthropic.model` | `claude-sonnet-4-6` (`CAPABILITIES_AI_ANTHROPIC_MODEL`) |
| `llm.anthropic.max_tokens` | `64000` (`CAPABILITIES_AI_ANTHROPIC_MAX_TOKENS`) |
| `user_model` | null → falls back to `auth.providers.users.model` (`CAPABILITIES_AI_USER_MODEL`) |
| `claim_ttl` | **`120`** (seconds; worker heartbeat / job timeout window) |
| `queue.connection` | null (`CAPABILITIES_AI_QUEUE_CONNECTION`) — applied to default `RunTurnJob` dispatch when set |
| `queue.name` | null (`CAPABILITIES_AI_QUEUE_NAME`) — applied to default dispatch; also marks **AI-chat** for core `capabilities:integration-health` when non-empty |
| `proposals.enabled` | `true` Phase-1 BC (`CAPABILITIES_AI_PROPOSALS_ENABLED`) — **greenfield: set `false`** |
| `reaper.stale_queued_minutes` | `30` (`CAPABILITIES_AI_REAPER_STALE_QUEUED`) |
| `reaper.stale_running_grace_seconds` | `60` (`CAPABILITIES_AI_REAPER_RUNNING_GRACE`) |
| `allow_unsafe` | `false` (`CAPABILITIES_AI_ALLOW_UNSAFE`) — local demos only |
| `max_tool_rounds` | `8` |
| `routes.enabled` | `false` |

Progress events live in array/Redis — **not** MySQL product tables.

**Bus principal (tool + accept invokes):** `TurnRunner` and `ProposalService` resolve the conversation’s Laravel user via `user_model` / auth provider and pass `caller=job` plus that user as `actor` on `CapabilityBus::invoke`. Missing/unresolvable `conversation.user_id` fails closed (no silent default user). README “bare” tool invokes means **no `idempotency_key`** — not “no invoke options.”

### Host integration (D-024 seams)

Happy-path AI-chat hosts **configure** — they do not rebind package runtime for queue or progress:

| Seam | Do | Do not |
|------|----|--------|
| Queue | Set `CAPABILITIES_AI_QUEUE_NAME` / `CAPABILITIES_AI_QUEUE_CONNECTION` | Full `ConversationService` rebind only to pick a queue |
| Progress side-effects | `app()->extend(ProgressStore::class, …)` in **`boot()`** after package bind | `singleton(ProgressStore::class, …)` replacing redis/array |
| Idempotency readiness | Leave SP default **`StoreBoundIdempotencyReadiness`** (live core store ping; fail closed when unbound) | Bind **`AlwaysReadyIdempotency`** in production (tests-only) |
| Proposals | `CAPABILITIES_AI_PROPOSALS_ENABLED=false` on greenfield | Assume routes-only gate — flag also skips TurnRunner fence extract + history proposals |
| Stale turns | Schedule `php artisan capabilities-ai:reap-stale-turns` | Host reapers on wrong tables / dual chat stores without a kill date |
| Product HTTP UX | **Host routes** → bus / AI services | Package route surgery or hijacking package chat HTTP for product UX |
| Diagnostics | Core `php artisan capabilities:integration-health` | Confuse with HTTP `GET …/capabilities/health` |

Full greenfield checklist, kill-list template, and extend snippet: [docs/user-guide.md](docs/user-guide.md#host-integration-greenfield).

## Host seams

Bind before running turns:

- `Rawphp\CapabilitiesAi\Contracts\ConversationContextProvider` — messages for the model
- `Rawphp\CapabilitiesAi\Contracts\ToolCatalog` — tools the model may call (names = capability names)
- `Rawphp\Capabilities\Contracts\CapabilityBus` — already provided by core

```php
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;

// Testing default
$app->bind(LlmClient::class, fn () => new FakeLlmClient);

// Production
$app->bind(LlmClient::class, fn () => new AnthropicLlmClient(
    apiKey: config('capabilities-ai.llm.anthropic.api_key'),
    model: config('capabilities-ai.llm.anthropic.model'),
));
```

**Custom `LlmClient`:** implement `supportsToolRounds()`. Prefer `use LlmClientDefaults` (returns false) and override to `true` **only** if the client accepts tool-result messages on the next `complete()` (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Lying opens a bus-then-crash path. (PHP interfaces still cannot ship method bodies on supported PHP; the trait is the fail-closed default for hosts.) **Host upgrade callouts:** [user guide](docs/user-guide.md#upgrade-for-hosts-llmclient-tool-rounds) · [CHANGELOG Breaking](CHANGELOG.md).

**MVS product default:** multi-round tools are **off** until a client opts in. `AnthropicLlmClient` and `FakeLlmClient` opt in (`supportsToolRounds() === true`); hosts using `LlmClientDefaults` stay fail-closed until they override. Empty tool defs + refuse-before-bus is defense-in-depth for non-tool-round clients, not a second product surface.

**Proposals (single accept/reject model):** Gated by **`proposals.enabled`** (`CAPABILITIES_AI_PROPOSALS_ENABLED`). When **false**: accept/reject routes are not registered, TurnRunner **skips** fence → proposal extract, and history omits/empties proposals. When **true**: Accept returns typed `AcceptOutcome` for every known status (rejected/expired → `refuse`); HTTP maps outcomes + 404 when missing. Reject uses CAS + RuntimeException → 409 for non-pending. **Greenfield:** set `false` until you need proposals. **Host upgrade callouts:** [user guide](docs/user-guide.md#upgrade-for-hosts-acceptreject-wire) · [CHANGELOG Breaking](CHANGELOG.md).

- **Accept:** atomic CAS `pending → accepting`, then bus invoke with `idempotency_key=proposal:{ulid}` (D-005). Live **`StoreBoundIdempotencyReadiness`** probe of core `IdempotencyStore` (fail closed when unbound) — not a constructor stamp; **`AlwaysReadyIdempotency` is unit-tests only**. Branch `isApprovalRequired()` then `isHardRefuse()` then `isRetryable()`; approval/retry leave status `accepting` for host re-drive. Hard non-retryable → `failed` + `last_error`. Success → atomic `accepting → accepted`, clear `last_error`. Returns typed `AcceptOutcome` (`accepted` | `approval_required` | `retryable` | `failed` | `refuse`).
- **Reject:** atomic CAS `pending → rejected` only; already-rejected is idempotent; accepting/accepted/failed/expired refuse (HTTP 409).
- **Recovery:** stuck `accepting` is intentional (approval / retry / crash mid-accept). Package does **not** TTL-expire or reclaim; host re-drives accept under the same D-005 key (`proposal:{ulid}`). Hosts must wire core **`IdempotencyStore`** (not an AI-package store) so the bus actually dedupes; readiness not ready → 503 without invoke. Conversation/tool bus invokes stay without an `idempotency_key` — only accept sets the proposal key. Both tool and accept invokes still carry the job+user principal (above).
- **Stale turns:** schedule `php artisan capabilities-ai:reap-stale-turns` (host owns the schedule; package does not auto-schedule). Thresholds: `reaper.stale_queued_minutes`, `reaper.stale_running_grace_seconds` (running age uses max(`claim_ttl`, grace)).

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

Kinds: `status` | `token` | `tool` | `error` | `terminal`.

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
