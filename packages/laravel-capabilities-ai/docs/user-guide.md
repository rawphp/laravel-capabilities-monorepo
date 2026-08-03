# laravel-capabilities-ai user guide

## What it is

Package runtime for chat turns on top of the core capability bus:

1. Cheap create message → queued turn (no LLM)
2. Claim + TurnRunner (LLM + tools via bus only)
3. Proposal accept/reject via bus

## Install

Path package in monorepo. Host: require `rawphp/laravel-capabilities-ai`, publish config + migrations.

## Host bindings

- `ConversationContextProvider`
- `ToolCatalog`
- `LlmClient` (Fake in tests, Anthropic in prod)

## Progress

`ProgressStore` array or Redis — never product MySQL.

## Optional routes

Set `CAPABILITIES_AI_ROUTES_ENABLED=true`. Prefix default `capabilities-ai/chat`.

## Proposal accept / reject (one model)

Fail-closed state machine + typed `AcceptOutcome` for HTTP. Atomic CAS claims; D-005 resume key on every accept invoke.

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
| `failed` / `refuse` | `failed` + `last_error` | Terminal — do not re-drive as success |

Stuck `accepting` is intentional limbo. Package does **not** TTL-expire or reclaim — **host re-drive only**.

### Reject

| Case | Result |
|------|--------|
| `pending` | Atomic CAS → `rejected` |
| already `rejected` | Idempotent success |
| `accepting` / `accepted` / `failed` / `expired` | Refuse (RuntimeException → HTTP 409) |

