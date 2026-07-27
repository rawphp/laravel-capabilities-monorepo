# Concepts

Why the bus exists and how the pieces fit. Read this before deep package configuration. For steps, use [Getting started](getting-started.md) and the [first capability tutorial](tutorials/first-capability.md).

## Why it matters

In an agent-era product you still need **one lawful place** to mutate business state. If HTTP controllers, agent tools, MCP tools, jobs, and chat each open their own write path, rules drift and security holes appear.

Laravel Capabilities is a **product capability bus**: define each product operation once, then expose it on the surfaces you need under the same rules.

One sentence: *Define what the product can do once; let every channel invoke it under the same law.*

## How it works (minimal model)

```text
  Agent / MCP / HTTP / CLI / Job / (Messaging → agent tools)
              │
              ▼
     CapabilityRegistry::invoke
              │
    validate → authorize → (approval) → run() → audit / events
              │
              ▼
        CapabilityResult
```

- **Capability** — a real product operation (create invoice, void subscription, …), not a chat reply template.
- **Definition** — name, description, input/output schema (from DTOs), allowed surfaces, authorize callback, single `run()`, optional approval / idempotency / audit / groups.
- **Registry** — the choke point. Adapters are thin; they do not reimplement domain rules.
- **Surface** — a channel that may call the registry (agent, mcp, http, cli, job, artisan, messaging ingress). Global config flags enable channels; a capability’s `surfaces` list only **narrows** what is allowed for that operation.
- **Caller** — who is invoking (`http`, `cli`, `agent`, …). **Server-derived** from credentials/adapters — never trust a client-claimed caller header.
- **Result** — success or deny/fail shaped as `CapabilityResult` (same class of outcome across surfaces when parity holds).

## Key terms

### One `run()`

Domain mutation lives in a single `run()` (or an app action it calls). Surfaces must not open a second write path to the same business state.

### DTOs and schema

Wire shapes use package-native DTOs extending `CapabilityData`. JSON Schema for catalog, tools, and CLI is derived from types. Laravel rule strings may enrich **server** validation; they are not the portable schema source of truth for the CLI.

### Profiles (agent / MCP tool lists)

Agent and MCP must not dump the full catalog by default. **Profiles** (and groups/tags/only selectors) limit which capabilities appear as tools. Profiles reduce selection error; they **never** replace `authorize()`. Messaging configures an `agent_profile` (default config key points at a named profile such as `support`) so the bot only sees an intentional subset.

### Approval

Some capabilities require a human decision before `run()` completes. The core package owns the approval state machine and HTTP accept/reject routes. Notifiers (HTTP, CLI, Telegram adapter contracts) tell humans a decision is waiting. Pending approval is a **deny-class** outcome for multi-surface parity checks.

### Idempotency

Mutating invokes can carry an `Idempotency-Key` (default header name). The product CLI always sends one (new UUID unless you pass `--idempotency-key` or `--retry-last`). The server is authoritative; local CLI checks are UX only.

### Scope and tenants

Resource IDs from the client are untrusted until re-resolved under server scope/tenant rules. Do not authorize from forged ambient identity.

### Messaging sibling

Telegram (then other chat products) lives in `rawphp/laravel-capabilities-messaging`. It implements conversation **ingress** and **approval notifier** contracts from core. Chat identity is linked (code link or allowlist); the bot feeds the agent; **tools are registry capabilities**. No domain `run()` inside the messaging package.

### Product CLI vs Artisan

| | Product CLI | Artisan |
|---|---|---|
| Binary | `capabilities` (Go) | `php artisan` |
| Where it runs | User machine / local agent | App server |
| How it works | HTTP client to capability API | In-process console |
| Caller | Server derives `cli` from credentials | Optional in-server ops surface |

There is **one** HTTP invoke tree. The CLI does not get a second controller stack.

### Peers (`laravel/ai`, `laravel/mcp`)

Core **composes** these packages as adapters when agent/MCP surfaces are enabled. Compatibility is declared in a peer support matrix. Missing or incompatible peers fail closed or soft-disable — never half-register tools.

## What to do next

1. [Getting started](getting-started.md) — install  
2. [First capability tutorial](tutorials/first-capability.md) — define and invoke  
3. Package guides: [core](packages/laravel-capabilities.md) · [messaging](packages/laravel-capabilities-messaging.md) · [CLI](packages/capabilities-cli.md)  
4. Design depth: [spec.md](spec.md)

## Related

- [Troubleshooting](troubleshooting.md)
- [Documentation index](README.md)
