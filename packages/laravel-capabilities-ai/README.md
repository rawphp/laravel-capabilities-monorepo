# rawphp/laravel-capabilities-ai

Conversation / turn / proposal runtime for the [Laravel Capabilities](https://github.com/rawphp/laravel-capabilities) bus.

**Monorepo path:** `packages/laravel-capabilities-ai/` in `laravel-capabilities-monrepo`.

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
