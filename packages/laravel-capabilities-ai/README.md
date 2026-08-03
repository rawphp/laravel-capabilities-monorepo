# rawphp/laravel-capabilities-ai

> **Status:** 0.x pre-stable — **not Packagist-published**.  
> **Install:** package VCS or monorepo path.

Conversation / turn / proposal runtime for the [Laravel Capabilities](https://github.com/rawphp/laravel-capabilities) bus.

**Monorepo path:** `packages/laravel-capabilities-ai/` in [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo).

## Scope (this package)

| | |
|---|---|
| **Is** | Optional **turn / proposal runtime**: queue a turn, claim it, loop LLM → tools, stream progress (array/Redis); tool side effects **only** via `CapabilityBus::invoke`; host seams for conversation context and tool catalog; thin `LlmClient` (fake + Anthropic) for turns and host completions that must not embed domain rules |
| **Is not** | The capability bus / registry; chat channel bots (use [messaging](https://github.com/rawphp/laravel-capabilities-messaging)); product CLI; a general app-wide LLM SDK replacing `laravel/ai`; domain `run()`; generative UI or agent-native OS |

Requires [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities). Consumers install **this package repo**, not the monorepo.

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
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
| `progress.driver` | `array` (or `redis`) |
| `llm.driver` | `fake` in tests / `anthropic` in prod |
| `claim_ttl` | `120` |
| `max_tool_rounds` | `8` |
| `routes.enabled` | `false` |

Progress events live in array/Redis — **not** MySQL product tables.

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

**Custom `LlmClient`:** implement `supportsToolRounds()`. Prefer `use LlmClientDefaults` (returns false) and override to `true` **only** if the client accepts tool-result messages on the next `complete()` (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Lying opens a bus-then-crash path. (PHP interfaces cannot ship method bodies; the trait is the fail-closed default for hosts.)

**MVS product default:** multi-round tools are **off** until a client opts in. `AnthropicLlmClient` stays false until real `tool_result` support ships; `FakeLlmClient` opts in for unit tests. Empty tool defs + refuse-before-bus is defense-in-depth for that default, not a second product surface.

**Proposal accept:** requires a container-bound core `IdempotencyStore` (D-005). Crash resume re-invokes with `idempotency_key=proposal:{ulid}` — no local `accept_outcome` cache. Reject is atomic `pending → rejected` only.

Env: `ANTHROPIC_API_KEY` (never required in CI — tests use `Http::fake` / `FakeLlmClient`).

## Flow

1. **Cheap create** — `ConversationService::createUserMessage` inserts message + queued turn, dispatches `RunTurnJob` (**no LLM**).
2. **Claim + run** — `TurnClaim` atomic update; `TurnRunner` loops LLM → tools via `CapabilityBus::invoke` only.
3. **Proposals** — `ProposalService::accept` / `reject` (accept is bus-only side effect).

## ProgressStore

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
