# laravel-capabilities-ai user guide

## What it is

Package runtime for chat turns on top of the core capability bus:

1. Cheap create message → queued turn (no LLM)
2. Claim + TurnRunner (LLM + tools via bus only)
3. Proposal accept/reject via bus

## Install

Path package in monorepo. Host: require `rawphp/laravel-capabilities-ai`, publish config + migrations.

### Upgrade: proposals `last_error` column

If you already ran package migrations **before** `last_error` was added to the create migration, your `capabilities_ai_proposals` table may lack the column. Accept fail/success paths write or clear `last_error` and will SQL-error until you migrate.

1. Pull the package revision that includes `2026_08_04_000001_add_last_error_to_capabilities_ai_proposals_table`.
2. Run **`php artisan migrate`** (or your host’s package migrate path).

The ALTER is idempotent (no-op if the column already exists). Greenfield installs get `last_error` from the create migration alone.

## Host bindings

- `ConversationContextProvider`
- `ToolCatalog`
- `LlmClient` (Fake in tests, Anthropic in prod)

### Upgrade for hosts (manual DI / constructor / job handle)

Hosts that construct AI runtime services with `new` (or jobs without container method injection) must match current required constructor / handle signatures. Preferred path: **`CapabilitiesAiServiceProvider` + `ContainerBindings`** — resolve from the container; manual construction is advanced.

| Site | Required now | Notes |
|------|--------------|--------|
| `TurnRunner` | `ProgressStore $progress` | Required 3rd ctor arg (`TurnClaim`, `LlmClient`, **`ProgressStore`**, then optional context/tools/bus…). Was optional `?ProgressStore = null`. |
| `ConversationService` | `ProgressStore $progress` | Required 2nd ctor arg after `$dispatch`. **No** silent `ArrayProgressStore` default in ctor. |
| `RunTurnJob::handle` | `handle(TurnRunner $runner)` | Workers resolve `TurnRunner` via **container method injection**. Empty `handle()` is invalid. |
| `ProposalService` | `IdempotencyReadiness $idempotency` | Required 2nd ctor arg after `CapabilityBus`. SP default **`AlwaysReadyIdempotency`**; rebind a live probe for production fail-closed accept. |

**Host impact:** constructing outside SP without these deps, or dispatching `RunTurnJob` without container injection, breaks at construct / handle time.

Authoritative matrix: [CHANGELOG Unreleased → Breaking → Manual DI / constructor / job handle](../CHANGELOG.md#manual-di--constructor--job-handle).

### Upgrade for hosts (LlmClient / tool rounds)

`LlmClient` requires **`supportsToolRounds(): bool`**. Hosts with a custom implementor must add the method or:

```php
use Rawphp\CapabilitiesAi\Support\LlmClientDefaults;

class MyLlmClient implements LlmClient
{
    use LlmClientDefaults; // returns false

    // override to true only if complete() accepts tool results next
}
```

| Client | Default | Host impact |
|--------|---------|-------------|
| Custom without method | n/a | **Compile/runtime break** until implemented |
| `LlmClientDefaults` | **false** | Safe default — multi-round tools off |
| `AnthropicLlmClient` | **false** | Tools not advertised to the model; if tool_calls still appear, TurnRunner **refuses bus invoke** before mutation (fail closed) |
| `FakeLlmClient` | **true** | Unit tests can exercise multi-round tools |

**Honesty rule:** return `true` only when the client can continue after tool results are appended (OpenAI-style `role=tool` or Anthropic `tool_result` blocks). Returning true without that support can mutate product state via the bus, then crash on the follow-up `complete()`.

**MVS product default:** multi-round tools stay **off** until a client opts in. Empty tool defs + refuse-before-bus is defense-in-depth for Anthropic and other non-tool-round clients — not a second product surface.

Authoritative behaviour: `TurnRunner` + `LlmClient` interface / `LlmClientDefaults` (see package unit tests).

### Upgrade for hosts (Anthropic default model ID)

Package default Anthropic model ID is now **`claude-sonnet-4-6`** (was `claude-sonnet-4-20250514`) in `config/capabilities-ai.php` (`CAPABILITIES_AI_ANTHROPIC_MODEL`) and the `AnthropicLlmClient` constructor. Hosts on package defaults hit a different model at runtime. Pin the previous ID via env or constructor `model` if you need the old default. See [CHANGELOG Unreleased Breaking](../CHANGELOG.md).

## Progress

`ProgressStore` array or Redis — never product MySQL.

### Upgrade for hosts (tool progress + tool messages)

Progress pollers / SSE clients and multi-round tool hosts must not assume always-ok tool outcomes.

#### Progress `kind=tool` events

Each tool invoke appends a progress event:

```json
{
  "kind": "tool",
  "data": {
    "name": "demo.tool",
    "payload": { "x": 1 },
    "ok": false,
    "error_code": "forbidden"
  }
}
```

| Field | Type | Notes |
|-------|------|--------|
| `name` | string | Capability name / alias invoked |
| `payload` | object | Invoke input (may contain host PII — treat progress as sensitive if streamed) |
| `ok` | bool | From `CapabilityResult::$ok` — **not** always true |
| `error_code` | string \| null | From `CapabilityResult::errorCode()`; null when `ok` is true |

**Host action:** branch on `data.ok` / `data.error_code`. Do not treat every `kind=tool` event as success.

#### Tool-role message content

After each bus invoke, TurnRunner appends a message for the next LLM round:

| | Old expectation | Current wire |
|--|-----------------|--------------|
| `role` | `tool` | unchanged |
| `content` | JSON always like `{"ok":true,"name":…}` | JSON of full `CapabilityResult::toArray()` **plus** `name` |

Example failure content (shape illustrative):

```json
{
  "ok": false,
  "error": { "code": "forbidden", "message": "nope" },
  "meta": {},
  "name": "demo.tool"
}
```

**Host action:** multi-round `LlmClient` implementors and transcript parsers must accept honest failure payloads (not invent success). Prefer `supportsToolRounds() === true` only when the client can continue after such messages.

Authoritative: `TurnRunner` (`progress->append` tool data + `encodeToolResult`). Unit locks in `TurnRunnerTest`.

## Optional routes

Set `CAPABILITIES_AI_ROUTES_ENABLED=true` (config `capabilities-ai.routes.enabled`; **default false**). Prefix default `capabilities-ai/chat`.

When enabled, `ChatController` exposes history, message create, turn show/cancel/events, proposal accept/reject, and conversation destroy. Domain logic stays in services; the controller only maps exceptions to HTTP.

### Upgrade for hosts (chat HTTP non-proposal routes)

**Non-proposal** routes moved from stub / always-**200** behaviour to real service payloads and fail-closed status codes (0.x pre-stable). Proposal accept/reject are documented separately: [Upgrade for hosts (accept/reject wire)](#upgrade-for-hosts-acceptreject-wire) · [CHANGELOG Unreleased Breaking](../CHANGELOG.md).

| Route action | Old expectation | Current wire |
|--------------|-----------------|--------------|
| **history** | Empty messages / always **200** | Real history from `ConversationService`; missing conversation → **HTTP 404** |
| **showTurn** | Stub body `{turn_ulid}` | Real turn from `TurnService`; missing → **HTTP 404** |
| **cancelTurn** | Always **200** cancelled stub | Real cancel; missing → **HTTP 404**; conflict (not cancellable) → **HTTP 409** + `message` |
| **turnEvents** | Empty events | Real progress events; query `cursor` (default **0**); JSON body `{turn_ulid, events}`; missing turn → **HTTP 404** |
| **destroyConversation** | Always **200** deleted stub | Real destroy; missing → **HTTP 404**; conflict (e.g. active turns) → **HTTP 409** + `message` |

**Status mapping (controller):**

| Exception / case | HTTP | Typical routes |
|------------------|------|----------------|
| `ModelNotFoundException` | **404** | history, showTurn, cancelTurn, turnEvents, destroyConversation |
| `RuntimeException` (domain conflict) | **409** + `message` | cancelTurn, destroyConversation |
| Success | **200** (message create **201**) | real service payload — not an empty stub |

**turnEvents shape (high level):**

- Query: `cursor` integer, default **0** when omitted
- Body: `{ "turn_ulid": "<ulid>", "events": [ … ] }` from `TurnService::events`

**Host action:** if you enable routes, stop assuming always-**200** empty bodies. Handle **404** for missing conversation/turn and **409** for cancel/destroy conflicts. Leave `routes.enabled` false until clients are ready.

Authoritative: `ChatController` + conversation/turn services (see package unit tests). CHANGELOG: [Unreleased Breaking — Chat HTTP non-proposal routes](../CHANGELOG.md).

## Proposal accept / reject (one model)

Fail-closed state machine + typed `AcceptOutcome` for HTTP. Atomic CAS claims; D-005 resume key on every accept invoke.

### Upgrade for hosts (accept/reject wire)

Hosts that still assume “reject always succeeds” or “accept failures are exceptions / 500s” must change clients.

**Reject (breaking vs force-reject):**

| Case | HTTP | Body / notes |
|------|------|----------------|
| `pending` | **200** | CAS → `rejected` |
| already `rejected` | **200** | Idempotent success — not an error |
| `accepting` / `accepted` / `failed` / `expired` | **409** | Refuse; do not force-reject mid-accept or after terminal states |
| missing | **404** | — |

**Accept (breaking vs throw-as-API):**

JSON body always includes `ulid`, `status`, `outcome` when the proposal exists. Accept does **not** throw for known statuses.

| Outcome (`outcome`) | HTTP | Host action |
|---------------------|------|-------------|
| `accepted` | **200** | Done |
| `approval_required` | **202** | Wait for approval; re-drive accept (D-005) |
| `retryable` | **429** or **409** | Re-drive when ready (`httpStatus` from result; rate_limited → 429) |
| `failed` | **422** or **503** | Terminal failure, or idempotency store not ready (503) — do not treat as success |
| `refuse` (bus hard) | **403** | Terminal — do not re-drive as success |
| `refuse` (already rejected) | **409** | Do not re-drive |
| `refuse` (expired) | **410** | Do not re-drive |
| (missing proposal) | **404** | — |

Authoritative mapping: `ProposalService` + `ChatController::jsonFromAcceptOutcome` / `rejectProposal` (see package unit tests).

**Accept D-005 key + host IdempotencyStore (breaking vs bare invoke):**

| | Old / wrong assumption | Current |
|--|------------------------|---------|
| Accept bus options | Bare invoke or optional key | **Always** `idempotency_key=proposal:{ulid}` |
| Double-accept / re-drive from `accepting` | May re-run domain twice | Same stable key; host core **`IdempotencyStore`** must be wired so the bus can dedupe |
| Store not ready | Silent / still invoke | Live `IdempotencyReadiness` → `failed` (**HTTP 503**), **no** bus invoke |
| Conversation tool bus invokes | Confused with accept | Remain **bare** (`idempotency_key` null) — only proposal **accept** uses `proposal:{ulid}` |

Hosts own core `IdempotencyStore` configuration. This package does **not** ship a second store; readiness alone is not enough for real dedupe.

### Accept

1. Live `IdempotencyReadiness` — not ready → `failed` (503), no bus invoke.
2. Atomic CAS `pending → accepting` (lost race re-enters accept).
3. Bus `invoke(..., ['idempotency_key' => 'proposal:{ulid}'])`.
4. Map result:

| AcceptOutcome | Proposal status | Host action |
|---------------|-----------------|-------------|
| `accepted` | `accepted` (`last_error` cleared) | Done |
| `approval_required` | stays `accepting` | Wait for approval; re-drive accept (D-005) |
| `retryable` | stays `accepting` | Re-drive when ready |
| `failed` / `refuse` (bus hard fail) | `failed` + `last_error` | Terminal — do not re-drive as success |
| `refuse` (already rejected) | stays `rejected` | HTTP 409 — do not re-drive |
| `refuse` (expired) | stays `expired` | HTTP 410 — do not re-drive |

Missing proposal on accept → HTTP 404. Accept never throws for known statuses.

Stuck `accepting` is intentional limbo. Package does **not** TTL-expire or reclaim — **host re-drive only**.

### Reject

| Case | Result |
|------|--------|
| `pending` | Atomic CAS → `rejected` |
| already `rejected` | Idempotent success |
| `accepting` / `accepted` / `failed` / `expired` | Refuse (RuntimeException → HTTP 409) |
| missing | HTTP 404 |

