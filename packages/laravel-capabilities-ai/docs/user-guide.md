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

