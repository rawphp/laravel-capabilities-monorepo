# Laravel Capabilities

> **Status:** monorepo **unit-complete design** (v0.1–v0.5 largely unit-tested in-package) — **not Packagist-published**, **not a stable public API**.  
> **Working title:** `rawphp/laravel-capabilities`  
> **Job:** one domain capability → many agent-era surfaces, without dual mutation paths.  
> **Install today:** monorepo path repository or VCS (`docs/versioning.md`). Unit-green monorepo ≠ shipped product — see root README readiness residuals.

---

## Philosophy

### What this is

**Laravel Capabilities** is a **product capability bus** for Laravel apps living in an agent-era world.

A *capability* is a real product operation — create an invoice, replace a meal food, void a subscription — defined once with:

- a name and description agents can understand  
- a schema  
- authorization  
- a single `run()`  
- optional approval and audit  

That definition is then **exposed** through the channels your product actually needs:

| Channel | Operator |
|---|---|
| In-app agent | `laravel/ai` tools |
| MCP | External agents (Claude Code, Cursor, ChatGPT, …) |
| HTTP | Your API, mobile apps, integrations |
| Product CLI | Downloadable client on the user’s machine (for humans *and* local agents) |
| Jobs | Queues and schedules |

Humans and agents are **two kinds of operators of the same product**, not two products glued together with drift-prone glue code.

This is **not** “add a chatbot.”  
This is **not** another LLM SDK.  
This is **not** a TypeScript framework ported to PHP.

It is the missing layer *above* Laravel’s official AI and MCP packages: the place where **domain truth** and **surface fan-out** meet.

### Why it exists

Software is no longer only clicked. It is also **driven** — by in-app agents, by coding agents on a laptop, by MCP hosts, by scripts, by cron.

Laravel already answers parts of that well:

- **`laravel/ai`** — talk to models and attach tools inside your app  
- **`laravel/mcp`** — expose tools to external AI clients  
- **HTTP, queues, policies, Artisan** — the classical product and ops surfaces  

What Laravel does **not** give you out of the box is a single place that says:

> This is what my product can *do*.  
> Every caller — agent, MCP, CLI, HTTP, job — uses that same definition, with the same rules.

Without that place, teams re-implement the same mutation three or four times:

1. A controller for the UI or mobile app  
2. An AI tool class for the in-app agent  
3. An MCP tool for Claude/Cursor  
4. Maybe a console command or job  

Schemas diverge. One path checks a policy; another forgets. Approvals apply only in chat. Audit exists only on the “agent” path. Reliability dies by a thousand dual paths.

**Why this package:** so Laravel apps can be agent-operable **without** sacrificing the discipline that made Laravel apps trustworthy for humans.

**Why not only MCP?** MCP is one excellent wire. Local agents also live in a shell; mobile apps speak HTTP; your own Coach loop needs tools; cron needs jobs. One bus, many transports.

**Why not only `laravel/ai`?** In-app agents are one operator. Customers and their coding agents are others. The product’s capabilities must outlive any single agent runtime.

**Why a downloadable CLI?** End users of a multi-tenant SaaS do not clone your monorepo. They install a small client, authenticate to *their* deployment, and let local agents drive real capabilities — the `gh` / Stripe CLI pattern, not Artisan-in-the-server-box.

**Why not clone agent-native?** [BuilderIO agent-native](https://github.com/BuilderIO/agent-native) proves the *architecture* (define once, many surfaces). It is a TypeScript/Nitro/React stack for cloneable web apps. Laravel shops need the same **invariant** in Laravel idioms — Eloquent, policies, Sanctum, queues, Pest — composing `laravel/ai` and `laravel/mcp`, not reimplementing them.

### Beliefs we build against

1. **One `run()`.** If two code paths can change the same business state, the package has failed its job. Surfaces are adapters; the registry is the choke point.

2. **Capability is product language; transport is plumbing.** We name operations the way the product thinks (`create-invoice`), not the way a protocol thinks (`tools/call`).

3. **Governance is part of the capability, not a chat feature.** Authorization, approval, audit, and caller identity apply whether the invoker was a button, an agent, MCP, or the CLI.

4. **Compose official packages; do not replace them.** We wrap `laravel/ai` and `laravel/mcp` as adapters. They own protocols and model loops; we own the capability contract.

5. **Surfaces are optional; defaults are generous.** Global config turns outchannels on or off (MCP yes/no, agent yes/no, CLI yes/no, …). Default: **all enabled** — opt out of what you do not need; do not force every app to rediscover the full map.

6. **The CLI is a client, not a second backend.** No domain logic on the laptop. Auth + catalog + invoke against the same HTTP bus. Optional MCP stdio is a bridge, not a fork.

7. **Thin framework, fat domain.** Your actions and services stay yours. We refuse to become a second application framework, a chat UI kit, or a template gallery. **Messaging bots are a sibling product**, not core registry weight — see [D-007](#d-007--package-boundary-messaging-vs-thin-core).

8. **Fail closed and fail obvious.** Disabled surfaces register nothing. Missing peer packages while a surface is enabled fail boot with a clear error. Invalid or unauthorized input never reaches `run`.

9. **No silent actors.** Every invoke has an explicit principal — a real `User`, a linked messaging identity, or a declared **system actor** for jobs/MCP integrations. Queue workers never pass authorization as “null user = allow.” MCP hosts get an **auth profile** (user PAT, integration credentials, or user-delegated OAuth) — not a vague “token user.” See [D-023](#d-023--mcp-principal-model-and-auth-profiles).

10. **No ambient tenancy.** Resource IDs from agents, MCP, CLI, or HTTP are untrusted until re-resolved inside the caller’s tenant/team scope. The package makes scope a first-class choke point, not a policy footnote authors might forget. **SystemActor tenant** comes from trusted job/context fields only — never wire input magic keys ([D-003](#d-003--multi-tenancy-and-resource-scoping) / P2-005).

11. **Retries must not double-apply.** Agent, MCP, CLI, and HTTP clients retry freely. Mutating invokes and approval accepts are idempotent when a key is supplied; the bus stores outcomes, not hope. See [D-005](#d-005--idempotency-for-mutating-invokes).

12. **Approvals are decisions, not delayed fire-and-forget.** Pending work expires, re-validates on accept, runs at most once, records who decided, and **recovers** if the process dies between decision and execution. See [D-006](#d-006--approval-store-replay-staleness-double-accept-and-crash-recovery).

13. **Least privilege for model tool lists.** Agents and MCP servers get **profiles / groups**, not every capability by default — and MCP is never “all UI powers for this user.” Meta-tools (`list` + `run`) inherit the **same** profile; progressive disclosure is not an escape hatch. See [D-008](#d-008--agent-tool-surface-profiles-not-full-catalog-dump) and [D-023](#d-023--mcp-principal-model-and-auth-profiles).

14. **The framework does not reintroduce dual paths.** One HTTP invoke API; the product CLI is a client (`caller: cli`), not a second controller tree. See [D-009](#d-009--one-http-capability-api-not-cli-vs-http-controllers).

15. **Domain success is not held hostage by audit failure** (unless `audit.strict`). Transactions and side effects are defined — see [D-010](#d-010--transaction--side-effect-consistency).

16. **Peer packages are pinned by matrix and contract tests**, not hope. Incompatible `laravel/ai` / `laravel/mcp` fail boot or soft-disable loudly — see [D-011](#d-011--peer-package-churn-laravelai--laravelmcp).

17. **Caller is a server-derived fact, never a free-form client claim.** Credential class / adapter code sets `caller`; `X-Capabilities-Caller` cannot self-upgrade policy. See [D-022](#d-022--server-derived-caller-not-client-spoofable-header).

### What success looks like

- A new product feature is **one capability class** (or fluent define), not four tool definitions.  
- Turning off MCP is a **config flag**, not a week of deleting code.  
- An in-app agent and a local Claude Code session cannot bypass rules the UI must obey.  
- An agent cannot `create-invoice` for another tenant’s `customer_id` even if `exists:customers,id` passes globally.  
- A support agent’s tool list does not include finance void/charge tools.  
- CLI and mobile hit the same invoke endpoint and the same `run()`.  
- Drift between “what the agent can do” and “what the API can do” becomes a **registry bug**, not a lifestyle.  
- Laravel remains Laravel — this package feels inevitable next to policies and queues, not alien.

### One sentence

> **Define what the product can do once; let every agent-era channel invoke it under the same law.**

---

## The gap (why Laravel needs this package)

Laravel already has strong primitives for AI and MCP:

| Package | Role |
|---|---|
| [`laravel/ai`](https://github.com/laravel/ai) | In-app agents, tools, streaming, providers |
| [`laravel/mcp`](https://github.com/laravel/mcp) | MCP servers for external AI clients |
| Framework | HTTP, Artisan, queues, policies, auth, events |

What is missing is the **product capability bus** between domain code and those primitives:

> Define a capability **once** (schema, authorization, `run`, approval, audit).  
> Expose it consistently as an **AI tool**, **MCP tool**, **HTTP action**, **downloadable CLI**, and **job** — same validation, same permissions, same side effects.

Without that layer, apps grow dual paths: UI controllers mutate one way, agent tools another, MCP tools a third. Schemas drift. Approvals apply unevenly. Audit trails fragment.

**Laravel Capabilities** is that missing middle: a **Laravel-native** server package plus a **distributable client CLI** for local machines and local agents.

---

## What this package is / is not

### Is

- A **capability registry** with typed input schemas (server)
- Automatic **invoke adapters** for AI, MCP, HTTP, jobs, and the remote CLI protocol
- **Approval + audit + scope + idempotency** as bus governance
- **Contracts** for conversation ingress (so messaging packages can plug in cleanly)
- A **downloadable CLI** end users (and their local agents) install on their computers
- A **catalog** agents, CLIs, and UIs can discover

### Is not

- An LLM client (use `laravel/ai` on the server; local agents stay whatever the user already runs)
- An MCP protocol implementation (use `laravel/mcp` for HTTP/SSE MCP; the CLI may *bridge* to it)
- **Artisan** as the product CLI (Artisan remains optional *in-server* ops; see below)
- A chat UI, Livewire kit, or cloneable SaaS template gallery
- **Telegram/Slack/WhatsApp bot runtime in core** — that is `rawphp/laravel-capabilities-messaging` (D-007)
- A2A mesh / multi-app workspace runtime
- A replacement for controllers, Form Requests, or your domain services
- Agent-native-with-PHP (full messaging OS); we are a **capability bus**

### CLI vs Artisan (important)

| | **Product CLI** (this package) | **Artisan** (Laravel core) |
|---|---|---|
| Who installs it | End user / developer on **their laptop** | App operator with the **server codebase** |
| Where it runs | Local machine | Server / deploy environment |
| Auth | Login / device code / API token against **production (or staging) app** | App env, typically already trusted |
| Who calls it | Humans **and local agents** (Claude Code, Cursor, Codex, scripts) | Deploy scripts, ops, rarely end users |
| Talks to | Remote capability HTTP (and optional local MCP stdio bridge) | In-process registry |

When this README says **CLI**, it means the **downloadable remote client**, not `php artisan …`.

---

## Architecture

Two kinds of surface feed the same product:

| Kind | Examples | Job |
|---|---|---|
| **Invoke surfaces** | HTTP, product CLI, MCP tools, `laravel/ai` tools, jobs | Call a **named capability** with a **structured payload** |
| **Conversation surfaces** | Telegram (v1), later Slack / WhatsApp / email | Carry **natural language** into an **agent turn**; agent then calls capabilities as tools |

Messaging is **not** a second mutation API. Telegram does not call Eloquent. It talks to the agent; the agent’s tools are the capability registry.

```text
  PHONE / CHAT APP              LOCAL MACHINE                 YOUR LARAVEL APP
 ┌──────────────────┐   ┌──────────────────────────┐   ┌─────────────────────────────┐
 │ Telegram (user)  │   │ Local agents             │   │ app/Capabilities/*.php      │
 └────────┬─────────┘   │ capabilities CLI         │   │      (define once)          │
          │ webhook     │  auth · catalog · run    │   └──────────────┬──────────────┘
          │             └────────────┬─────────────┘                  │
          ▼                          │ HTTPS                          ▼
 ┌──────────────────┐                │              ┌─────────────────────────────┐
 │ Messaging gateway│                │              │ CapabilityRegistry          │
 │  TelegramAdapter │                └─────────────►│  validate → authz → approve │
 │  identity · threads               product CLI    │  → run → audit              │
 └────────┬─────────┘                MCP / HTTP     └──────────────▲──────────────┘
          │                                                        │ tools
          │              ┌─────────────────────┐                   │
          └─────────────►│ Agent (laravel/ai)  ├───────────────────┘
                         │ tools = profile     │
                         │ (not full catalog)  │
                         └─────────────────────┘

 Invoke adapters (structured):     AiTool · McpTool · HttpApi · QueueJob
 Conversation adapters (NL):       Telegram · (later Slack, …)  →  agent  →  registry
                                   (product CLI = HTTP client, caller:cli — D-009)
```

**Core server package** (`rawphp/laravel-capabilities`) owns definitions, governance, invoke adapters, and **conversation ingress contracts**.  
**Product CLI** is a thin authenticated client over the **same** HTTP capability API as every other HTTP caller ([D-009](#d-009--one-http-capability-api-not-cli-vs-http-controllers)) — not a second server pipeline.  
**Messaging** (`rawphp/laravel-capabilities-messaging`) is an **optional sibling**: front door into the **agent**, not a parallel `run()` path, and **not** shipped inside core (D-007).

### Core concepts

| Concept | Meaning |
|---|---|
| **Capability** | A single product operation (`replace_meal_food`, `create_invoice`, …) |
| **Invoke surface** | Structured call path: `agent` tools, `mcp`, `http`, `cli` (product CLI), `job` |
| **Conversation surface** | Chat channel into the agent: `messaging.telegram` (v1), later Slack / etc. |
| **Caller context** | Who/what invoked (`agent` \| `mcp` \| `http` \| `cli` \| `job` \| messaging-derived agent turns) |
| **Actor / principal** | Who is responsible: `User`, or **`SystemActor`** for jobs/schedulers — never implicit null |
| **Scope / tenant** | Active organization, team, or tenant for this invoke — resolved before authorize/run |
| **Approval** | Optional human gate before `run` — HTTP, CLI, **or chat buttons** |
| **Audit** | Structured record of mutating invocations (always includes actor **and scope**) |
| **Catalog** | Machine-readable capabilities + JSON Schemas |
| **Identity link** | Maps Telegram (etc.) user → Laravel `User` before agent tools may mutate |

### Design rules

1. **One `run()`.** Every structured invoke hits the registry. Messaging never bypasses it.
2. **Adapters are dumb.** Protocol in, registry (or agent) out.
3. **Domain stays yours.** Capabilities call your actions/services.
4. **Global surface switches, then per-capability narrowing.** Default: all on; opt out.
5. **Fail closed.** Disabled surfaces register nothing; unlinked chat identities cannot run tools.
6. **Conversation ≠ invoke.** Telegram/Slack feed the agent; the agent’s tools are capabilities.
7. **Jobs declare an actor.** See [D-002 — Job / scheduler caller identity](#d-002--job--scheduler-caller-identity).
8. **Resources are re-resolved under scope.** System actors get tenant from first-class job/context fields only (P2-005). See [D-003 — Multi-tenancy and resource scoping](#d-003--multi-tenancy-and-resource-scoping).
9. **Mutating invokes support idempotency keys.** See [D-005 — Idempotency for mutating invokes](#d-005--idempotency-for-mutating-invokes).
10. **Approvals are a state machine with single execution and crash recovery.** See [D-006 — Approval store](#d-006--approval-store-replay-staleness-double-accept-and-crash-recovery).
11. **Messaging is a sibling package.** Core stays a thin capability bus. See [D-007](#d-007--package-boundary-messaging-vs-thin-core).
12. **Agents get tool groups, not the full catalog.** See [D-008](#d-008--agent-tool-surface-profiles-not-full-catalog-dump).
13. **One HTTP capability API.** CLI is a client, not a second controller tree. See [D-009](#d-009--one-http-capability-api-not-cli-vs-http-controllers).
14. **Transactions and audit semantics are explicit.** See [D-010](#d-010--transaction--side-effect-consistency).
15. **Peer adapters are versioned and tested.** `laravel/ai` / `laravel/mcp` churn is expected — see [D-011](#d-011--peer-package-churn-laravelai--laravelmcp).
16. **Names, errors, DTOs, CLI language, and ops hooks are decided before external cache.** See [D-012–D-021](#next-design-pass-d-012d-021).
17. **Caller is derived from credentials / adapters, not spoofable headers.** See [D-022](#d-022--server-derived-caller-not-client-spoofable-header).
18. **MCP principals are explicit auth profiles**, not “whatever OAuth user.” See [D-023](#d-023--mcp-principal-model-and-auth-profiles).

---

## Dependencies

### Required

| Package | Why |
|---|---|
| `php` ^8.2+ | Modern attributes / typed properties |
| `illuminate/*` (Laravel 11+) | Container, routing, console, bus, events |
| `rawphp/laravel-capabilities` | This package |

### Optional peers (recommended in production agent apps)

| Package | Why | If missing / incompatible |
|---|---|---|
| [`laravel/ai`](https://packagist.org/packages/laravel/ai) | In-app agent tools surface | `agent` surface disabled or boot fails ([D-011](#d-011--peer-package-churn-laravelai--laravelmcp)) |
| [`laravel/mcp`](https://packagist.org/packages/laravel/mcp) | External / remote MCP tools surface | `mcp` surface disabled or boot fails |

### Peer support matrix (maintained in README + CI)

Versions below are **illustrative placeholders** until first release; the machine-readable source of truth is package `PeerSupportMatrix` (mirrored in `config/capabilities.php` → `peers.support`). Package README documents the D-011 release gate.

| `rawphp/laravel-capabilities` | Laravel | `laravel/ai` | `laravel/mcp` | Adapter API |
|---|---|---|---|---|
| `^0.1` | `^11.0` \| `^12.0` | matrix constraints (see `PeerSupportMatrix`) | matrix constraints (see `PeerSupportMatrix`) | `v1` |
| `^0.2` (example) | … | … | … | `v1` or `v2` |

- **Composer `suggest`** lists peers; **composer.json `conflict`** / test matrix pins known-bad combos when discovered.
- **Default package CI does not install live `laravel/ai` / `laravel/mcp`.** Package honesty = matrix + unit contract fixtures (mocks/fakes). Live peer minors are an **optional consumer-app** path.
- Each release notes declared peer constraints; consumer apps that run peer-live may record which minors they exercised.
- Bumping the matrix without a green **unit** adapter contract suite is a release blocker (D-011).

### Distributed separately (same monorepo, different Composer packages)

| Artifact | Package | Why |
|---|---|---|
| **Capability bus (core)** | `rawphp/laravel-capabilities` | Registry, schema, HTTP, AI/MCP/job adapters, approval, audit, scope, idempotency, **ingress contracts** |
| **Product CLI** | `rawphp/capabilities-cli` | Downloadable client; auth + catalog + run + MCP stdio |
| **Messaging** | `rawphp/laravel-capabilities-messaging` | Telegram (then Slack/…); webhooks, identity, threads — **not in core** (D-007) |

Install on the **server**:

```bash
composer require rawphp/laravel-capabilities

# Optional peers for invoke surfaces
composer require laravel/ai laravel/mcp

# Optional: conversation surfaces (Telegram, …)
composer require rawphp/laravel-capabilities-messaging
```

Install the **CLI** on the **user’s machine** (not on the server):

```bash
# examples — exact channel TBD
brew install rawphp/tap/capabilities
# or
curl -fsSL https://capabilities.example/install | sh
# or
npx @rawphp/capabilities-cli
```

Core **suggests** `laravel/ai`, `laravel/mcp`, and `rawphp/laravel-capabilities-messaging`. Boot requirements follow **`surfaces.*.enabled`**. Core does **not** reimplement provider SDKs, MCP wire protocol, or Bot API stacks.

### Explicit non-dependencies

- No React/Vite runtime
- No Drizzle / Nitro
- No forced Livewire / Inertia
- No separate HTTP microframework — uses Laravel routes

---

## Installation (planned)

```bash
composer require rawphp/laravel-capabilities

# Only required for surfaces you enable (see config)
composer require laravel/ai    # if surfaces.agent = true
composer require laravel/mcp   # if surfaces.mcp = true

php artisan vendor:publish --tag=capabilities-config
```

---

## Configuration: which outchannels to support

Apps choose **which surfaces (outchannels)** are live. That choice is global config — not per-capability naming only.

**Default: everything enabled.** Ship the full fan-out; operators turn off channels they do not want. Per-capability `surfaces: [...]` can only **narrow** further (a capability cannot enable a globally disabled channel).

### `config/capabilities.php`

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Capability discovery
    |--------------------------------------------------------------------------
    */
    'path' => app_path('Capabilities'),

    /*
    |--------------------------------------------------------------------------
    | Surfaces (outchannels)
    |--------------------------------------------------------------------------
    |
    | Master switches for how capabilities may be invoked. Defaults are all
    | true: enable the full agent-era surface, then disable what you do not
    | need. When a surface is false, the package does not register routes,
    | tools, CLI API, or adapters for it — nothing is "half generated."
    |
    | Peer packages (laravel/ai, laravel/mcp) are still required only if the
    | matching surface is enabled; if enabled but the package is missing,
    | boot fails with a clear error (or soft-skips in local if you set
    | surfaces.*.require_package = false).
    |
    */
    'surfaces' => [

        /*
         * In-app agent tools via laravel/ai.
         * When false: no AI tool registration, Capability::aiTools() is empty.
         */
        'agent' => [
            'enabled' => env('CAPABILITIES_SURFACE_AGENT', true),
            'require_package' => true, // laravel/ai must be installed when enabled
            // D-011: missing or incompatible peer
            'on_incompatible' => env('CAPABILITIES_AGENT_ON_INCOMPATIBLE', 'fail'), // fail | disable
            // D-008: never default to "all tools"; define named profiles
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
            'require_profile' => true, // aiTools / aiMetaTools need profile|groups|only (P2-007)
            'max_tools_warn' => 32,  // log warning if a profile expands larger
            'max_tools_hard' => 64,  // throw / refuse if exceeded (config)
            'max_tool_calls_per_turn' => 16, // D-013 agent loop budget
        ],

        /*
         * MCP server tools via laravel/mcp (remote hosts + optional CLI bridge).
         * When false: no MCP tool registration, no /mcp capability catalog wiring.
         * Always register tools via named profiles (D-008). Principal model: D-023.
         */
        'mcp' => [
            'enabled' => env('CAPABILITIES_SURFACE_MCP', true),
            'require_package' => true, // laravel/mcp must be installed when enabled
            'on_incompatible' => env('CAPABILITIES_MCP_ON_INCOMPATIBLE', 'fail'), // fail | disable
            // D-008: never default to mounting every mcp-surface capability
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
            'require_profile' => true, // mcpTools() without profile is error / loud deprecation
            // D-023: how MCP credentials map to actor
            'auth' => [
                'default_profile' => 'user_pat', // user_pat | integration | user_delegated
                'allow_integration_credentials' => false, // opt-in: app-level tokens → SystemActor/bot
                'integration_actors' => [
                    // 'mcp-billing-bot' => SystemActor name or bot user id mapping
                ],
                'audit_client_id' => true, // always record mcp.client_id when present
            ],
        ],

        /*
         * HTTP capability API (also the transport the product CLI uses).
         * When false: no capability routes; product CLI cannot talk to this app
         * unless you re-enable HTTP (CLI is not a separate protocol).
         */
        'http' => [
            'enabled' => env('CAPABILITIES_SURFACE_HTTP', true),
            'prefix' => 'capabilities',
            'middleware' => ['api', 'auth:sanctum'],
        ],

        /*
         * Product CLI = same HTTP API as any client (D-009), with caller: cli.
         * Requires surfaces.http.enabled. "cli enabled" means: document CLI,
         * allow device-code auth routes, register the CLI OAuth client /
         * token ability that maps to caller: cli (D-022).
         * There is no second invoke controller for the CLI.
         */
        'cli' => [
            'enabled' => env('CAPABILITIES_SURFACE_CLI', true),
        ],

        /*
         * Queue / scheduler invocations (RunCapability job).
         */
        'job' => [
            'enabled' => env('CAPABILITIES_SURFACE_JOB', true),
        ],

        /*
         * Optional in-server Artisan helpers for operators with the codebase.
         * Default true to match "enable everything."
         */
        'artisan' => [
            'enabled' => env('CAPABILITIES_SURFACE_ARTISAN', true),
        ],

        /*
         * Conversation surfaces are implemented by
         * rawphp/laravel-capabilities-messaging (D-007), not core.
         * Core only exposes whether the app expects messaging contracts.
         * Detailed channel config lives in config/capabilities-messaging.php
         * after that package is installed.
         */
        'messaging' => [
            'enabled' => env('CAPABILITIES_SURFACE_MESSAGING', false),
            // default false in core installs — turn on when messaging package present
        ],
    ],

    'audit' => [
        'enabled' => true,
        // best_effort (default): domain commit wins if audit write fails; queue retry
        // strict: audit failure fails the invoke (rolls back only if outer/domain txn includes it)
        'mode' => env('CAPABILITIES_AUDIT_MODE', 'best_effort'), // best_effort | strict
        'driver' => 'database', // database | log | queue
        'queue' => 'capabilities-audit', // used when driver=queue or best_effort outbox
        'required' => false, // if true with best_effort: at-least-once via outbox; never silent drop
    ],

    'transactions' => [
        // null/false: registry does NOT wrap run() in DB::transaction (default)
        // true: optional outer transaction around run + sync audit (see D-010 — rarely what you want)
        'wrap_run' => false,
    ],

    'events' => [
        'enabled' => true, // CapabilityInvoked, CapabilityFailed, CapabilityApprovalRequested, …
    ],

    'approval' => [
        'store' => 'database',
        'ttl_hours' => 24,              // pending → expired (D-006)
        'default_policy' => 'requester_or_role', // see D-006
        // P2-004: deferred = pending→approved→executed + resume job; atomic = pending→executed under lock
        'execution' => env('CAPABILITIES_APPROVAL_EXECUTION', 'deferred'), // deferred | atomic
        'resume' => [
            'enabled' => true,
            'every_seconds' => 60,
            'grace_seconds' => 30,
            'stuck_after_seconds' => 300,
            'lease_seconds' => 120,
        ],
        // Channel notifiers (telegram, …) registered by messaging package
    ],

    'idempotency' => [
        'enabled' => true,
        'ttl_hours' => 24,
        'header' => 'Idempotency-Key',
        'warn_missing_key' => env('CAPABILITIES_IDEMPOTENCY_WARN', true),
    ],

    'validation' => [
        'validate_output' => true, // D-014 — default ON
    ],

    'rate_limits' => [
        'enabled' => true, // D-013
        'defaults' => [
            'per_minute' => 60,
            'per_capability_per_minute' => 30,
        ],
        'agent_turn' => [
            'max_tool_calls' => 16,
        ],
    ],

    'observability' => [
        'metrics' => true, // D-019
        'tracing' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caller derivation (D-022)
    |--------------------------------------------------------------------------
    |
    | HTTP `caller` is derived from credential class — never trusted as a
    | free-form client claim. Map Sanctum token abilities / Passport OAuth
    | client_ids to policy buckets. In-process adapters set caller in code.
    |
    */
    'clients' => [
        // OAuth client_id (Passport/Sanctum device) → caller value
        'oauth' => [
            // 'capabilities-cli' => 'cli',
            // 'ios-app' => 'http',
            // 'billing-integration' => 'http',
        ],
        // Sanctum personal access token ability (or token name pattern) → caller
        'token_abilities' => [
            'capabilities:cli' => 'cli',
            // bare API tokens without a mapped ability default to 'http'
        ],
        // Privilege order for optional X-Capabilities-Caller downgrade only
        // (lower index = more privileged for policy; header may only move toward
        // a *stricter* bucket, never toward a more lenient one)
        'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
        // If true, unknown/mismatched header is ignored (telemetry only).
        // If false, mismatched upgrade attempts return 400 caller_claim_rejected.
        'reject_upgrade_attempts' => false,
    ],
];
```

When `rawphp/laravel-capabilities-messaging` is installed it publishes e.g. `config/capabilities-messaging.php` (tokens, webhook secrets, identity link mode, Telegram callback TTL). Core does not ship Bot API env requirements.

### Behaviour when a surface is disabled

| Surface `enabled => false` | What does **not** happen |
|---|---|
| `agent` | No `laravel/ai` tools registered; in-app agents do not see capabilities as tools |
| `mcp` | No MCP tools / catalog wiring from this package |
| `http` | No capability HTTP routes (and product CLI cannot call the app) |
| `cli` | Device-code / CLI client auth helpers off; do not advertise product CLI (HTTP invoke may remain) |
| `job` | `RunCapability` job / scheduler helpers not registered |
| `artisan` | No `php artisan capability:*` commands |
| `messaging` | Core does not register chat routes; if messaging package absent, surface stays off |

Disabled surfaces are **not** partially registered. No dead routes, no empty tool stubs that confuse models.

**Messaging package** adds its own channel switches (`telegram`, later `slack`, …) when installed.

### Interaction with per-capability flags

```php
#[Capability(
    name: 'create-invoice',
    surfaces: ['agent', 'mcp', 'http', 'cli'], // request these
)]
```

Effective exposure:

```text
effective = capability.surfaces ∩ globally enabled surfaces
```

If `surfaces.mcp.enabled` is `false`, this capability is never an MCP tool even if `'mcp'` is listed on the class.

### Env-first toggles (deploy-time)

```env
CAPABILITIES_SURFACE_AGENT=true
CAPABILITIES_SURFACE_MCP=true
CAPABILITIES_SURFACE_HTTP=true
CAPABILITIES_SURFACE_CLI=true
CAPABILITIES_SURFACE_JOB=true
CAPABILITIES_SURFACE_ARTISAN=true
```

Example: API-only backend that is not agent-facing yet:

```env
CAPABILITIES_SURFACE_AGENT=false
CAPABILITIES_SURFACE_MCP=false
CAPABILITIES_SURFACE_CLI=false
CAPABILITIES_SURFACE_HTTP=true
CAPABILITIES_SURFACE_JOB=true
CAPABILITIES_SURFACE_ARTISAN=false
```

Example: full invoke bus (core defaults):

```env
# agent/mcp/http/cli/job on — omit keys or true
```

Example: add Telegram (requires messaging package):

```env
# composer require rawphp/laravel-capabilities-messaging
CAPABILITIES_SURFACE_MESSAGING=true
CAPABILITIES_TELEGRAM=true
TELEGRAM_BOT_TOKEN=...
TELEGRAM_WEBHOOK_SECRET=...
```

### Boot rules

1. **Invoke surfaces default on** (agent, mcp, http, cli, job, artisan) — opt out.  
2. **`messaging` defaults off** in core until `rawphp/laravel-capabilities-messaging` is installed; that package may default its channels on.
3. **Missing peer package** while surface enabled + `require_package` → fail boot **or** disable surface per `on_incompatible` (D-011).
4. **Incompatible peer version** (adapter probe fails) → same as (3): **fail** (default) or **disable + CRITICAL log** — never half-register tools.
5. **`cli` requires `http`** — fail boot if misconfigured.
6. **`messaging` requires `agent` + messaging package** — package presence checked at boot if surface enabled; **Telegram secrets validated on first HTTP traffic / setup command**, not on every `artisan migrate` (D-021).
7. **Telegram token/secret** required only by the **messaging** package when its telegram channel is on **and** a messaging request or `messaging:telegram-setup` runs.
8. **Catalog** only lists capabilities with at least one *effective* invoke surface for that caller.
9. **CI** must run **unit** adapter contract tests (matrix + fixtures; no live peers in default package CI) before release (D-011).
10. **`CAPABILITIES_SKIP_BOOT_CHECKS=true`** only for tightly controlled CI; never production (D-021).

---

## Defining a capability

**Canonical form (D-017):** one PHP class, `#[Capability(...)]`, implements `DefinesCapability`, with package-native input/output types (D-015).  
**Alternate:** fluent `Capability::define(...)->…` that registers the same shape.  
Do not invent a third discovery path.

Prefer **typed input/output DTOs**. The package derives **JSON Schema** from those types — see [Type safety & schemas](#type-safety--schemas).

```php
<?php

namespace App\Capabilities;

use App\Models\Invoice;
use Illuminate\Contracts\Auth\Authenticatable;
use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData; // package-native base (D-015)
use Rawphp\Capabilities\Support\CapabilityResult;

/** Wire shape for create-invoice — package-native, not Spatie-required */
class CreateInvoiceInput extends CapabilityData
{
    public function __construct(
        #[Field(description: 'Customer id within the active tenant')]
        public int $customer_id,
        public int $amount_cents,
        public string $currency,
        public ?string $memo = null,
    ) {}

    public static function rules(): array
    {
        // Server-only rules (DB existence, etc.) — not all of these ship to the CLI
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}

class CreateInvoiceResult extends CapabilityData
{
    public function __construct(
        public int $invoice_id,
    ) {}
}

#[Capability(
    name: 'create-invoice',
    description: 'Create an invoice for a customer.',
    surfaces: ['agent', 'mcp', 'http', 'cli'],
    input: CreateInvoiceInput::class,
    output: CreateInvoiceResult::class,
    // optional deprecation (D-012):
    // aliases: ['invoice.create'],
    // deprecated: false,
    // successor: null,
)]
class CreateInvoice implements DefinesCapability
{
    public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        $user = $ctx->user();
        if ($user === null) {
            return false;
        }

        // Re-resolve under tenant — never trust client customer_id alone (D-003)
        $customer = $ctx->scope()
            ->query(Customer::class)
            ->whereKey($input->customer_id)
            ->first();

        return $customer !== null
            && $user->can('create', Invoice::class);
    }

    public function needsApproval(CreateInvoiceInput $input, CapabilityContext $ctx): bool
    {
        return $input->amount_cents >= 100_000
            && in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true);
    }

    public function run(CreateInvoiceInput $input, CapabilityContext $ctx): CreateInvoiceResult
    {
        $customer = $ctx->scope()
            ->query(Customer::class)
            ->whereKey($input->customer_id)
            ->firstOrFail(); // 404/403-class failure if cross-tenant id

        $invoice = Invoice::query()->create([
            'tenant_id' => $ctx->tenantId(),
            'customer_id' => $customer->id,
            'amount_cents' => $input->amount_cents,
            'currency' => $input->currency,
            'memo' => $input->memo,
            'created_via' => $ctx->caller(),
        ]);

        return new CreateInvoiceResult(invoice_id: $invoice->id);
    }
}
```

`run` receives a **validated, typed** DTO — never a raw untrusted array (arrays are only the wire format at the edge).

Optional Spatie bridge (D-015): apps may use Spatie Laravel Data classes if they implement `SchemaProvider` / are registered via `CapabilityData::usingSpatie()` — **not** required for v1.

Fluent alternate (same registry entry):

```php
Capability::define('create-invoice')
    ->description('…')
    ->input(CreateInvoiceInput::class)
    ->output(CreateInvoiceResult::class)
    ->surfaces(['agent', 'mcp', 'http', 'cli'])
    ->authorize(fn (CreateInvoiceInput $in, CapabilityContext $ctx) => …)
    ->run(fn (CreateInvoiceInput $in, CapabilityContext $ctx) => …);
```

---

## Type safety & schemas

Goal: **one contract**, enforced at every boundary — PHP static analysis, server runtime, AI/MCP tool schemas, **and the product CLI before it sends HTTP**.

### The problem with Laravel rules alone

```php
'customer_id' => 'required|integer|exists:customers,id'
```

- Great on the server  
- **Not portable** to a Go/TS/Rust CLI  
- **`exists:customers,id` cannot be checked on the laptop** without the DB  
- AI/MCP hosts expect **JSON Schema**, not Laravel rule strings  

So Laravel rule strings are a **server enrichment**, not the cross-surface source of truth.

### Source of truth: typed DTO → JSON Schema

```text
CreateInvoiceInput (PHP class / Data object)
        │
        ├─► PHPStan / Psalm  (static types in app + package)
        ├─► Server runtime validate + hydrate DTO
        │     1) structural (JSON Schema)
        │     2) server-only rules (exists, unique, …)
        ├─► JSON Schema document (draft 2020-12)
        │     published in catalog + tool definitions
        └─► CLI / agents validate structure with same JSON Schema
              before POST (no server round-trip for shape errors)
```

| Layer | What “type safety” means |
|---|---|
| **PHP app + package** | Typed DTOs on `authorize` / `run` / `output`; PHPStan level max on package |
| **Server runtime** | Reject invalid wire JSON; never call `run` with untyped bags |
| **Catalog / AI / MCP** | JSON Schema (and/or provider tool schema derived from it) |
| **Product CLI** | Fetch schema → validate payload locally → only then send |
| **Compile-time CLI** | Optional codegen from catalog for strongly typed SDKs (later) |

The generic CLI cannot hard-code every app’s types. It gets **runtime type safety** from **downloaded JSON Schema**. The Laravel app gets **static type safety** from DTOs.

### Contract split: portable vs server-only

| Rule kind | Example | In JSON Schema for CLI? | On server? |
|---|---|---|---|
| **Structural / portable** | `integer`, `string`, `min`, `max`, `enum`, `required`, formats | **Yes** | Yes |
| **Server-only** | `exists:customers,id`, `unique`, policy, multi-tenant scope | **No** (or documented as “server will recheck”) | Yes |

CLI validates **portable** constraints early (fast feedback, fewer bad requests).  
Server **always** re-validates portable + server-only (CLI is untrusted).

Never trust the client alone — local validation is UX and agent efficiency; **server is law**.

### Catalog carries the schema

```http
GET /capabilities/create-invoice
```

```json
{
  "name": "create-invoice",
  "description": "Create an invoice for a customer.",
  "surfaces": ["agent", "mcp", "http", "cli"],
  "input_schema": {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "additionalProperties": false,
    "required": ["customer_id", "amount_cents", "currency"],
    "properties": {
      "customer_id": { "type": "integer", "minimum": 1 },
      "amount_cents": { "type": "integer", "minimum": 1 },
      "currency": { "type": "string", "minLength": 3, "maxLength": 3 },
      "memo": { "type": ["string", "null"], "maxLength": 500 }
    }
  },
  "output_schema": {
    "type": "object",
    "required": ["invoice_id"],
    "properties": {
      "invoice_id": { "type": "integer" }
    }
  },
  "schema_version": "1"
}
```

`GET /capabilities` returns the same schemas for the full catalog (or a compact list + `describe` for detail).

### CLI: validate before send

```text
capabilities run create-invoice --input-file=payload.json
        │
        ▼
1. Resolve profile / base URL / token
2. Load schema (cache by name + schema_version; refresh if stale)
3. JSON Schema validate payload  ← fail here with field errors, exit 2
4. Ensure Idempotency-Key (generate UUID if not --idempotency-key)
5. POST /capabilities/create-invoice + Idempotency-Key  only if valid
6. Server re-validates + authorize + run (or return stored result for same key)
```

```bash
# Structural error never hits the network
capabilities run create-invoice --input='{"customer_id":"x"}'
# → error: customer_id must be integer  (local)
# exit code 2

# Structural OK, server-only fails
capabilities run create-invoice --input='{"customer_id":999999,"amount_cents":100,"currency":"USD"}'
# → HTTP 422 exists:customers  (server)
# exit code 5
```

Schema cache:

- On `auth login` / `catalog refresh`, store schemas under `~/.config/capabilities/<profile>/schemas/`  
- Header or catalog field `schema_version` / `etag` → invalidate cache  
- `capabilities run --no-cache` forces re-fetch  

### Server pipeline (order matters)

```text
raw JSON + optional Idempotency-Key
  → JSON Schema validate (portable)
  → hydrate CreateInvoiceInput DTO
  → server-only validation (exists, …)
  → resolve actor / scope (D-002, D-003)
  → idempotency lookup (D-005)
  → authorize(DTO)
  → needsApproval(DTO)?  → maybe pending + CapabilityApprovalRequested event
  → rate limit check (D-013)
  → run(DTO)             → domain owns its transaction (D-010)
  → validate output schema (D-014 — default ON for mutations)
  → store idempotency result (if key)
  → record audit (best_effort or strict — D-010)
  → emit CapabilityInvoked | CapabilityFailed
  → wire JSON response (stable error envelope — D-018)
```

If any step **before** `run` fails, `run` is not called (except idempotent **replay** of a completed result).  
After a **successful** `run`, audit/event failure does **not** roll back domain state unless `audit.mode = strict` (D-010).  
Invalid **output** after `run` is a server bug: log + `CapabilityFailed` / 500-class envelope; do not return malformed tool results to agents (D-014).

### AI & MCP

- Tool **input_schema** for Anthropic/OpenAI/MCP = catalog `input_schema` (JSON Schema).  
- No second hand-written tool schema.  
- Drift between agent tools and HTTP is impossible if both mount from the registry.

### PHP package standards

| Practice | Purpose |
|---|---|
| DTOs on public capability API | Static types for app authors |
| `array` only at HTTP/CLI edge adapters | Wire format quarantine |
| PHPStan (max) + Psalm optional in CI | Package-internal safety |
| `output` schema | Catch broken `run()` returns before they hit agents |
| Pest tests: schema snapshot | Catalog JSON Schema locked in tests so refactors don’t silently change contracts |

### Optional: codegen (later)

For apps that want **compile-time** types outside PHP:

```bash
capabilities codegen typescript --out ./src/generated/capabilities.ts
capabilities codegen go --out ./pkg/capabilities/
```

Generated clients embed schemas or typed builders; they still call the same HTTP API. **v1 does not require codegen** — JSON Schema validation in the generic CLI is enough.

### What we refuse

- Laravel rule strings as the only schema (not CLI-safe)  
- Separate Zod/JSON Schema hand-copied beside the PHP DTO  
- CLI sending first and “letting the server validate” as the only check (wasteful; bad agent loops)  
- Skipping server re-validation because “CLI already checked”  

### One sentence

> **DTOs type the server; JSON Schema types the wire; the CLI validates the wire before send; the server always re-validates.**

---

## Surfaces

### 1. In-app agent (`laravel/ai`)

Capabilities with the `agent` surface **may** be exposed as AI tools — but **not** by dumping the entire catalog into one model context. See **[D-008](#d-008--agent-tool-surface-profiles-not-full-catalog-dump)**.

**Happy path: named profiles / tool groups**, not `aiTools()` with no filter.

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Rawphp\Capabilities\Facades\Capability;

class BillingAgent implements Agent, HasTools
{
    public function instructions(): string
    {
        return 'You help staff manage invoices. Use only billing tools.';
    }

    public function tools(): iterable
    {
        // Profile from config, or explicit allowlist — NOT the full catalog
        return [
            ...Capability::aiTools(profile: 'billing'),
            // Large products: meta-tools still bound to the same profile (P2-007)
            // ...Capability::aiMetaTools(profile: 'billing'),
            // equivalent direct list:
            // ...Capability::aiTools(['create-invoice', 'void-invoice', 'list-invoices']),
        ];
    }
}
```

```php
// Support agent — smaller, safer surface
return [...Capability::aiTools(profile: 'support')];
```

**Visibility filter:** `aiTools()` only includes capabilities the **current actor** may discover (`canDiscover` / authorize read) under the active scope. Unauthorized tools never appear in the model’s list.

**Still enforced on call:** even if a tool is listed, `authorize()` + scope (D-003) run on every invoke. Profiles reduce **selection error and prompt-injection surface**; they do not replace authorization.

The adapter implements the AI SDK tool contract; `handle` validates and invokes the registry.

### 2. MCP (`laravel/mcp`)

Same rule as agents: **do not mount the universe** on one MCP server by default. **Always** pass a named profile — MCP servers are **not** “all capabilities the authenticated user could do in the UI.”

```php
// routes/ai.php (Laravel MCP style)
use Laravel\Mcp\Facades\Mcp;
use Rawphp\Capabilities\Facades\Capability;

Mcp::web('billing', function ($server) {
    // profile required (D-008 + D-023) — no unfiltered mcpTools()
    $server->tools(Capability::mcpTools(profile: 'billing'));
});
```

External clients (Claude Code, Cursor, ChatGPT MCP, etc.) call the same `run()` as the in-app agent. Use separate MCP servers or profiles for separate product areas; optional **compact catalog + `invoke` meta-tools** for large products still **bound to the same profile** ([D-008 progressive disclosure / P2-007](#progressive-disclosure-advanced-large-products--p2-007)) — not a full-catalog escape hatch.

**Principal is not “token user” only.** Hosts use different credential shapes (user PAT, integration client credentials, user-delegated OAuth). The bus maps each to an explicit actor + audit metadata — see **[D-023 — MCP principal model](#d-023--mcp-principal-model-and-auth-profiles)**.

| Token type | Actor | Notes |
|---|---|---|
| User PAT | That `User` | Default |
| Integration client credentials | `SystemActor` or bot `User` | Must use `allowSystemCallers` / narrow profiles |
| User-delegated OAuth | `User` + `mcp.client_id` in context | Audit **both** |

### 3. HTTP

```http
POST /capabilities/create-invoice
Authorization: Bearer …
Content-Type: application/json
Idempotency-Key: 01J8Z…   # optional but recommended for mutating caps (D-005)
# Caller is derived from the credential (D-022) — not from a free-form header.
# Optional telemetry only:
# X-Capabilities-Caller: mobile   # may downgrade presentation; never upgrades policy

{
  "customer_id": 42,
  "amount_cents": 2500,
  "currency": "USD"
}
```

Body field alternative: `"idempotency_key": "01J8Z…"` (same semantics as the header; header wins if both set).

Or invoke programmatically (in-process, no HTTP) — adapters and jobs set `caller` in code:

```php
use Rawphp\Capabilities\Facades\Capability;

$result = Capability::invoke('create-invoice', [
    'customer_id' => 42,
    'amount_cents' => 2500,
    'currency' => 'USD',
], caller: 'http', idempotencyKey: '01J8Z…');
```

**One route tree** serves browsers, mobile, integrations, and the product CLI ([D-009](#d-009--one-http-capability-api-not-cli-vs-http-controllers)):

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/capabilities` | Catalog list |
| `GET` | `/capabilities/{name}` | Describe (JSON Schema) |
| `POST` | `/capabilities/{name}` | Invoke |
| `GET/POST` | `/capabilities/auth/…` | Token / device-code auth (CLI + API clients) |
| `POST` | `/capabilities/approvals/{id}/accept\|reject` | Approval decisions |

### 4. Product CLI (downloadable — not Artisan)

End users install a small client on their computer. It authenticates to **their** deployment, then calls the **same** HTTP capability API as any other client. **Local coding agents** shell out to the CLI (or its MCP stdio mode). There is **no** `CliApiController` invoke pipeline on the server.

#### Auth

```bash
# Point at a deployment
capabilities auth login --base-url=https://app.example.com

# Device-code or browser OAuth / personal access token (Sanctum/Passport)
# Tokens stored in OS keychain / config dir — never in the agent prompt
capabilities auth status
capabilities auth logout
```

#### Catalog & invoke

```bash
capabilities catalog
capabilities describe create-invoice

# CLI generates an Idempotency-Key per run unless you pass one (D-005)
capabilities run create-invoice \
  --input='{"customer_id":42,"amount_cents":2500,"currency":"USD"}'

capabilities run create-invoice \
  --input-file=./payload.json \
  --idempotency-key=manual-retry-001 \
  --json

echo $?   # stable exit codes: 0 ok, 2 validation, 3 auth, 4 approval_required, 5 domain error
```

#### Local agents

Two integration modes (both hit the **same** server `run()`):

| Mode | How local agents use it |
|---|---|
| **Shell tool** | Agent runs `capabilities run …` / `capabilities catalog` as a subprocess |
| **MCP stdio bridge** | `capabilities mcp` speaks MCP on stdio; Cursor/Claude Desktop connect to the local process, which proxies to the remote app with the stored token |

```bash
# Example: register with a local MCP host
capabilities mcp --base-url=https://app.example.com
```

Server-side, CLI traffic is **the same** `POST /capabilities/{name}` with:

| Signal | Example |
|---|---|
| Auth | Bearer token from `capabilities auth login` (issued for the **registered CLI OAuth client** or a token ability that maps to `cli`) |
| Caller | **Server-derived** as `cli` from credential class ([D-022](#d-022--server-derived-caller-not-client-spoofable-header)) — not from a client-chosen header |
| Optional envelope | `Accept: application/vnd.capabilities.cli+json` for CLI-oriented error/result shaping |
| Idempotency | `Idempotency-Key` (CLI always sends — D-005) |

Capabilities opt in with `surfaces: […, 'cli']` for **policy/catalog visibility**, not a separate code path.

#### What the CLI is *not*

- Not `php artisan` inside the app repo  
- Not a second copy of your domain logic on the laptop  
- Not unauthenticated public access — **auth first**, then invoke  
- **Not a second HTTP controller tree** for invoke/catalog (D-009)

Optional **in-server** Artisan helpers (`php artisan capability:run`) may still exist for operators who have the codebase; they call `Capability::invoke` in-process and must not be confused with the product CLI.

### 5. Jobs / scheduler

Queue workers have **no HTTP session**. That must not become silent privilege or silent deny. See **[D-002](#d-002--job--scheduler-caller-identity)** for the full actor model.

**Required:** every job payload names an **actor** — a user id acting on behalf of someone, or a registered **system actor** for true machine work.

```php
use Rawphp\Capabilities\Jobs\RunCapability;
use Rawphp\Capabilities\Support\SystemActor;

// Act as a concrete user (e.g. “run this for user 42 after they requested it”)
RunCapability::dispatch(
    name: 'create-invoice',
    input: [
        'customer_id' => 42,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ],
    actingAs: 42, // user_id — required
);

// True system work (nightly reconciliation) — must be allowlisted on the capability.
// Tenant is a first-class job field (D-003 / P2-005) — never buried in input as _tenant_id.
RunCapability::dispatch(
    name: 'daily-reconciliation',
    input: [],
    actingAs: SystemActor::named('scheduler'),
    tenantId: 'tenant_7',
);
```

Scheduled example:

```php
$schedule->job(new RunCapability(
    name: 'daily-reconciliation',
    input: [],
    actingAs: SystemActor::named('scheduler'),
    tenantId: 'tenant_7', // required for multi-tenant system jobs unless globalSystem
))->dailyAt('02:00');
```

**Dispatch without `actingAs` fails** (exception at queue time or before push) — never enqueued as null-user. Multi-tenant system jobs also require first-class `tenantId` (or `globalSystem: true` on the capability) — see [D-003](#d-003--multi-tenancy-and-resource-scoping).

### 6. Messaging / Telegram (sibling package — not core)

Chat platforms are **front doors into the agent**, not alternate `run()` APIs. Implementation lives in **`rawphp/laravel-capabilities-messaging`** ([D-007](#d-007--package-boundary-messaging-vs-thin-core)), not in `laravel-capabilities` core.

Inspired by [agent-native messaging](https://www.agent-native.com/docs/messaging): same agent, same tools (capabilities), shared thread history — reachable from the phone.

#### Core vs messaging responsibilities

| Package | Owns |
|---|---|
| **`laravel-capabilities` (core)** | `ConversationIngress` / `ConversationReply` contracts; agent tool wiring; approval APIs; capability registry |
| **`laravel-capabilities-messaging`** | Telegram webhooks, Bot API, identity link, thread store, chat approval notifier, signed callbacks |

#### Pipeline (messaging package)

```text
Telegram update
  → verify webhook secret
  → queue ProcessTelegramUpdate
  → resolve identity (linked Laravel user or reject)
  → map chat/topic → conversation thread
  → ConversationIngress → laravel/ai agent (tools = profile, e.g. aiTools(profile: 'support'))
  → tool calls → CapabilityRegistry
  → ConversationReply → Telegram Bot API
```

Messaging config should name which **agent profile** (D-008) the bot uses — never “all agent-surface capabilities.”

#### Identity (required)

Unlinked Telegram users never get tool access (`code` link flow or `allowlist`). Details and env vars ship with the messaging package README.

#### What messaging must never do

- Call Eloquent / domain services directly from the adapter  
- Own a second `run()` path  
- Become a hard dependency of core  

#### Later channels

Slack, WhatsApp, email add adapters **inside the messaging package** (or further splits like `-telegram` only if needed). Core contracts stay stable.

```bash
composer require rawphp/laravel-capabilities-messaging
php artisan vendor:publish --tag=capabilities-messaging-config
```

---

## Caller context

Every `run` receives a `CapabilityContext`:

| Field | Description |
|---|---|
| `caller` | `agent` \| `mcp` \| `http` \| `cli` \| `job` — **server-derived** ([D-022](#d-022--server-derived-caller-not-client-spoofable-header)) |
| `actor` | **Always set:** `User` or `SystemActor` — never null after context is built |
| `user` | `User` when the actor is a user; `null` only when actor is `SystemActor` |
| `scope` | **Active tenancy boundary** — `CapabilityScope` from `ScopeResolver` (D-003) |
| `tenantId` / `teamId` / `organizationId` | Convenience accessors when the app uses those dimensions |
| `requestId` / `traceId` | Correlation ids |
| `agent` | Optional agent name / thread id when `caller=agent` |
| `mcp` | When `caller=mcp`: `{ client_id?, auth_profile: user_pat\|integration\|user_delegated, host?, session? }` (D-023) |
| `messaging` | Optional `{ channel: telegram, chat_id, … }` when the agent turn originated from chat |
| `job` | Optional `{ queue, job_id, acting_as_type, acting_as_id }` when `caller=job` |
| `credential` | Optional audit metadata: `{ type: oauth\|pat\|in_process, client_id?, ability? }` used to derive caller |

Use this for policy differences (e.g. agents cannot void invoices without approval; staff UI can). Messaging-originated tool calls still use `caller: agent` at the registry; `messaging` metadata explains *which* front door started the turn.

**Caller is not a client-chosen header.** Approvals and rate limits that branch on `$ctx->caller()` are only meaningful if the bus sets caller from **credential class** or **in-process adapter code**. See [D-022](#d-022--server-derived-caller-not-client-spoofable-header).

`authorize()` should branch on **actor type**, not on “is user null?”, and **always** re-resolve resources under `$ctx->scope()`:

```php
public function authorize(CreateInvoiceInput $input, CapabilityContext $ctx): bool
{
    return match (true) {
        $ctx->actor() instanceof User => $ctx->scope()
            ->query(Customer::class)
            ->whereKey($input->customer_id)
            ->exists()
            && $ctx->user()->can('create', Invoice::class),
        $ctx->actor() instanceof SystemActor => false, // invoices are never system-created here
        default => false,
    };
}
```

---

## D-002 — Job / scheduler caller identity

| | |
|---|---|
| **ID** | D-002 |
| **Category** | Authorization / architecture |
| **Effort** | Medium |
| **Impact** | Security, reliability |

### Problem

`RunCapability::dispatch('create-invoice', [...])` with `caller: job` and `authorize()` written as `$ctx->user()` collides with reality: **queue workers have no authenticated user**.

Without a specified model, authors fall into one of three dual-path failure modes:

1. **Jobs always fail authorization** (user is null → deny)  
2. **Authors skip auth when `caller === 'job'`** (second, weaker path)  
3. **Jobs run as implicit superuser** (null user + allow)

None of those are acceptable for a package whose job is one law for every surface.

### Decision

**Every job invoke must declare an explicit actor.** The registry never builds a job context with a null principal.

#### Actor kinds

| Actor | When to use | How policies see it |
|---|---|---|
| **`User` (by id)** | Work on behalf of a person (deferred user action, per-tenant night job for that user) | Same as HTTP: `$user->can(...)` |
| **`SystemActor`** | True machine work (reconciliation, cleanup, aggregate reports) | Dedicated checks: capability flag + named actor, **not** staff UI policies |

```php
namespace Rawphp\Capabilities\Support;

final class SystemActor
{
    public function __construct(
        public readonly string $name, // e.g. 'scheduler', 'reconciliation', 'horizon'
    ) {}

    public static function named(string $name): self
    {
        return new self($name);
    }
}
```

#### Capability declaration for system callers

Capabilities that may run as system must **opt in**. Default: system actors are **denied**.

```php
#[Capability(
    name: 'daily-reconciliation',
    description: 'Reconcile yesterday’s ledger.',
    surfaces: ['job', 'artisan'],
    allowSystemCallers: ['scheduler'], // only these SystemActor names
    // input/output DTOs…
)]
class DailyReconciliation implements DefinesCapability
{
    public function authorize(DailyReconciliationInput $input, CapabilityContext $ctx): bool
    {
        // Registry already enforces allowSystemCallers for SystemActor.
        // Optional extra checks:
        if ($ctx->actor() instanceof SystemActor) {
            return $ctx->actor()->name === 'scheduler';
        }

        // Human-triggered reconcilation via artisan/http if you allow it:
        return $ctx->user()?->can('reconcile', Ledger::class) ?? false;
    }

    public function run(DailyReconciliationInput $input, CapabilityContext $ctx): DailyReconciliationResult
    {
        // …
    }
}
```

`allowSystemCallers: true` (allow any registered system name) is supported but discouraged; prefer an explicit name list.

Capabilities **without** `allowSystemCallers` (or with `[]`) **reject** `SystemActor` before `authorize()` — fail closed.

#### Job payload contract

```php
new RunCapability(
    name: string,           // capability name
    input: array,           // wire JSON → DTO (capability fields only — never tenant magic keys)
    actingAs: int|SystemActor,  // REQUIRED — user_id or SystemActor
    tenantId: ?string = null,   // first-class scope for SystemActor (D-003 / P2-005)
    // optional: teamId, organizationId — same rule: constructor attrs, not input[]
)
```

| Rule | Behaviour |
|---|---|
| Missing `actingAs` | **Do not dispatch** — throw `MissingJobActorException` |
| `actingAs: int` | Load `User`; missing user → fail job; set `actor = user`, `caller = job`; scope from user (resolver) |
| `actingAs: SystemActor` | Capability must list that name in `allowSystemCallers`; else fail |
| `SystemActor` + multi-tenant | Require first-class `tenantId` (or equivalent context attr) **or** capability `globalSystem: true` — **not** `input['_tenant_id']` ([P2-005](#tenant-source-for-system-actors-p2-005)) |
| `authorize()` returns false | Fail job; **do not** run; audit denial |
| Audit | Always record `actor_type`, `actor_id`/`actor_name`, `caller=job`, and resolved `tenant_id` |

#### What we refuse

| Anti-pattern | Why |
|---|---|
| `if ($ctx->caller() === 'job') return true;` | Dual path; silent superuser |
| Null user + “allow when no user” | Same |
| Reusing only `$user->can('update', $model)` for scheduler jobs without a user | Scheduled capabilities must declare **system** authorization, not pretend to be staff UI |
| Global “jobs bypass policy” config | Package-level footgun |
| Putting tenant in `input['_tenant_id']` (or similar) for system jobs | Agent/MCP/DTO hydrate path can inject tenant — **P2-005** |

#### Author guidance

1. **User-scoped scheduled work** — pass `actingAs: $userId` (or resolve tenant owner explicitly when enqueueing).  
2. **Machine-scoped scheduled work** — `SystemActor::named('…')` + `allowSystemCallers` + first-class `tenantId` on the job (D-003) + policies that understand system.  
3. **Never** copy-paste staff UI `authorize()` and hope jobs work.  
4. **Never** smuggle scope through capability input for system actors.  
5. Pest: assert dispatch without actor throws; assert system actor on non-allowlisted capability fails; assert system job without `tenantId` fails when tenancy required; assert user actor hits the same policy as HTTP.

#### Relationship to other surfaces

| Surface | How actor is set |
|---|---|
| `http` / `cli` | Authenticated token → `User` (caller from credential class — D-022) |
| `agent` / messaging | Linked/session user → `User` |
| `mcp` | **Auth profile** (D-023): user PAT → `User`; integration credentials → `SystemActor`/bot; user-delegated OAuth → `User` + audited `mcp.client_id` — not a single “token user” abstraction |
| `job` | **Payload `actingAs` only** — no ambient auth |
| `artisan` | Prefer explicit `--acting-as=` / `--system=scheduler`; refuse bare invoke for mutating caps |

---

## D-003 — Multi-tenancy and resource scoping

| | |
|---|---|
| **ID** | D-003 |
| **Category** | Authorization / architecture |
| **Effort** | Large (design), medium for a first-class hook |
| **Impact** | Security, scalability |
| **Also closes** | P2-005 (SystemActor tenant via input magic keys) |

### Problem

The invoice example’s `exists:customers,id` and `$user->can('create', Invoice::class)` say nothing about **which tenant** that customer belongs to.

The package’s job — **one domain capability → many agent-era surfaces** — is exactly where tenant leaks hurt most:

- An agent or MCP client that can `create-invoice` for a `customer_id` in **another** tenant is catastrophic.  
- Server-only `exists` is **global** unless the rule is itself scoped.  
- Policies *might* enforce tenancy **if authors remember** on every capability.  
- Dual-path consistency (agent = HTTP = CLI) is worthless if the shared path is cross-tenant.

Leaving tenancy to chance is a dual-path failure mode: **tenant A vs tenant B**.

A second failure mode (**P2-005**): resolving `SystemActor` tenant from **wire input** magic keys (e.g. `input['_tenant_id']`). Underscore fields invite agents/MCP (or any path that hydrates full input into the resolver) to **inject tenant**. Job-only constructor fields set by trusted dispatchers are safe; client/agent DTO keys are not.

### Decision

1. **Document a required pattern:** never trust client resource IDs alone; re-resolve every resource under the active scope inside `authorize()` and `run()`.  
2. **First-class context hooks:** `CapabilityContext::scope()`, `tenantId()`, optional `team()` / `organizationId()`, backed by an app-supplied **`ScopeResolver`**.  
3. **Pipeline middleware:** resolve scope **after** actor, **before** `authorize` / `run` (`ResolveTenantFromCaller`).  
4. **Testing helpers:** `assertCannotInvokeAcrossTenant` (and related) so cross-tenant invoke is a package-level regression target.  
5. **SystemActor scope from trusted context only (P2-005):** `RunCapability::$tenantId` / context attributes set by trusted dispatchers — **never** from capability input / magic keys. User-facing capabilities may declare an explicit `tenant_id` DTO field only if it is **membership-checked** against the authenticated user; that path is still not for `SystemActor`.

The package does **not** mandate a single tenancy library (Stancl, Spark, home-grown). It mandates a **scope choke point** every surface hits.

### Required author pattern

```text
Client sends customer_id=99
        │
        ▼
JSON Schema / exists:customers,id     ← structural only; may pass cross-tenant
        │
        ▼
ScopeResolver → tenant_id=7 for this actor
        │
        ▼
authorize/run:
  scope->query(Customer::class)->whereKey(99)->first()
        │
        ├─ null  → deny / not found  (cross-tenant or missing)
        └─ row   → continue with THIS model only
```

**Never:**

```php
// WRONG — trusts client id after a global exists rule
$customer = Customer::findOrFail($input->customer_id);
Invoice::create(['customer_id' => $customer->id, ...]);
```

**Always:**

```php
// RIGHT — re-resolve under scope
$customer = $ctx->scope()
    ->query(Customer::class)
    ->whereKey($input->customer_id)
    ->firstOrFail();

Invoice::create([
    'tenant_id' => $ctx->tenantId(),
    'customer_id' => $customer->id,
    ...
]);
```

Same for updates: load the aggregate under scope, then mutate. Do not `find($id)` in the global connection and “check tenant_id later” unless that check is unavoidable and tested — prefer scoped query so the leak is impossible.

### ScopeResolver (app-supplied)

```php
namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;

interface ScopeResolver
{
    /**
     * Resolve the active tenancy boundary for this invoke.
     * Called after actor is known, before authorize/run.
     */
    public function resolve(CapabilityContext $partial): CapabilityScope;
}
```

```php
namespace Rawphp\Capabilities\Support;

final class CapabilityScope
{
    public function __construct(
        public readonly ?string $tenantId = null,
        public readonly ?string $teamId = null,
        public readonly ?string $organizationId = null,
        /** @var array<string, mixed> */
        public readonly array $attributes = [],
    ) {}

    /**
     * Start an Eloquent query constrained to this scope.
     * Apps register how each model is scoped (trait, global scope, or resolver map).
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $model
     * @return \Illuminate\Database\Eloquent\Builder<T>
     */
    public function query(string $model): \Illuminate\Database\Eloquent\Builder
    {
        return app(ScopedQueryFactory::class)->for($this, $model);
    }
}
```

Example app resolver:

```php
final class AppScopeResolver implements ScopeResolver
{
    public function resolve(CapabilityContext $partial): CapabilityScope
    {
        $user = $partial->user();

        if ($user === null) {
            // SystemActor: tenant ONLY from first-class job/context fields (P2-005).
            // Never: $partial->input['_tenant_id'] or any wire/DTO magic key.
            $tenantId = $partial->jobTenantId()   // RunCapability::$tenantId
                ?? $partial->contextAttr('tenant_id') // trusted dispatcher only
                ?? null;

            if ($tenantId === null && ! $partial->capabilityAllowsGlobalSystem()) {
                throw new UnresolvedScopeException(
                    'System jobs must declare tenantId on the job/context, not in input.'
                );
            }

            return new CapabilityScope(tenantId: $tenantId !== null ? (string) $tenantId : null);
        }

        // User actors: scope from membership (session/token), not from untrusted input alone.
        // If the capability declares an explicit tenant_id DTO field, membership-check it:
        //   if ($input->tenant_id) assert user belongs to that tenant; else use current.
        return new CapabilityScope(
            tenantId: (string) $user->current_tenant_id,
            teamId: $user->current_team_id ? (string) $user->current_team_id : null,
        );
    }
}
```

Bind in the app:

```php
// AppServiceProvider
$this->app->singleton(ScopeResolver::class, AppScopeResolver::class);
```

### Single-tenant apps

Not every Laravel app is multi-tenant. For solo products:

```php
final class SingleTenantScopeResolver implements ScopeResolver
{
    public function resolve(CapabilityContext $partial): CapabilityScope
    {
        return new CapabilityScope(tenantId: 'default');
    }
}

// ScopedQueryFactory may be a no-op (unconstrained query) when tenantId === 'default'
```

The **hook still runs** — so enabling tenancy later is config/resolver work, not a rewrite of every capability. Document clearly when `query()` is unconstrained so authors do not assume isolation they do not have.

### Pipeline middleware

Ordered invoke pipeline (package-owned, app-extendable):

```text
ValidateSchema
  → HydrateDto
  → ResolveActor          // D-002 jobs; D-023 MCP auth profiles; token/session for http/cli/agent
  → ResolveTenantFromCaller  // ScopeResolver — D-003
  → Authorize
  → ApprovalGate
  → ExecuteRun
  → Audit
```

`ResolveTenantFromCaller`:

- Invokes `ScopeResolver::resolve`  
- Attaches `CapabilityScope` to context  
- Fails closed if resolver throws or returns unusable scope when the app marked tenancy as required  
- Passes **actor + job/context attributes** into the resolver; does **not** promote raw input keys into scope for system actors  

Optional: header `X-Tenant-Id` / CLI `--tenant=` **only** as a hint that the resolver may accept after verifying membership — **never** as the sole authority without membership check. Same rule for any client-supplied tenant claim.

### Tenant source for system actors (P2-005)

| Source | Allowed for `SystemActor` scope? |
|---|---|
| `RunCapability::$tenantId` (constructor / dispatch arg) | **Yes** — trusted dispatcher |
| Context attributes set only by in-process trusted code (schedule, admin console, package job) | **Yes** |
| Capability `globalSystem: true` (no tenant; rare cross-tenant maintenance) | **Yes** — explicit opt-in on the capability |
| `input['_tenant_id']`, `input['tenant_id']` smuggled for system jobs | **No** — backdoor if any path hydrates input into the resolver |
| Agent / MCP / HTTP body magic underscore fields | **No** |
| Explicit DTO field on a **user-facing** capability (e.g. staff “switch tenant”) | **Only** if declared on the schema **and** membership-checked against the authenticated user — still not for `SystemActor` |

**Package stance:** official examples, tests, and `RunCapability` API use first-class `tenantId`. Docs and static analysis guidance forbid reading `$partial->input[...]` for system scope. If an app resolvers still does, that is an app bug — the package example must not teach it.

### SystemActor + tenancy

System jobs are high risk: no human session, easy to forget the tenant.

| Rule | Behaviour |
|---|---|
| System job without tenant | Fail unless capability is explicitly `globalSystem: true` (rare: true cross-tenant maintenance) |
| System job with tenant | `actingAs: SystemActor` **and** first-class `tenantId` on the job/context; resolver builds scope from that only |
| Wire input | Capability DTO fields for the operation only — **no** tenant magic keys for system actors |
| Audit | Record both `actor` and `tenant_id` (from resolved scope, not from raw input) |

```php
RunCapability::dispatch(
    name: 'daily-reconciliation',
    input: [], // domain payload only — never ['_tenant_id' => …]
    actingAs: SystemActor::named('scheduler'),
    tenantId: 'tenant_7', // first-class job field (P2-005)
);
```

### What `exists` and policies are for

| Mechanism | Role |
|---|---|
| JSON Schema / portable rules | Shape |
| Laravel `exists`, `unique` | Coarse DB presence — **not** tenancy |
| Policies / `can` | Permission within a scope |
| **`scope()->query()`** | **Tenancy / team isolation choke point** |

Authors may still use policies that check `tenant_id`; the package still requires scoped re-resolution so a missed policy does not become a cross-tenant write.

### Testing helpers

```php
Capability::fake(); // or real registry + tenant DB

// User in tenant A must not affect tenant B resources
$this->actingAs($userTenantA);

Capability::assertCannotInvokeAcrossTenant(
    name: 'create-invoice',
    input: ['customer_id' => $customerInTenantB->id, 'amount_cents' => 100, 'currency' => 'USD'],
    foreignTenant: $tenantB,
);

// Explicit positive control
Capability::invoke('create-invoice', [...], caller: 'http')
    ->assertOk(); // customer in tenant A
```

Also:

- `assertScopeResolvedTo($tenantId)`  
- Pest dataset: same capability via `http`, `agent`, `cli`, `mcp` — all deny cross-tenant id  

Cross-tenant tests are **as mandatory** as schema tests for any multi-tenant app using this package.

### What we refuse

| Anti-pattern | Why |
|---|---|
| Trusting client ids after global `exists` | Classic IDOR via agent/MCP |
| “Policies will handle tenancy” with no scoped query | One forgotten policy = breach |
| Different tenancy rules per surface | Dual path; agent weaker than UI |
| Optional scope only on HTTP | MCP/CLI/agent become the leak |
| Documenting tenancy only in a blog post | Must live in pipeline + context + tests |
| **`ScopeResolver` reading `input['_tenant_id']` (or any input) for `SystemActor` (P2-005)** | Agents/MCP can inject tenant if input is hydrated into the resolver |
| Treating underscore-prefixed input as “internal only” | Wire clients can still send those keys |

### Author checklist

1. Register a `ScopeResolver` (single-tenant stub or real).  
2. In every `authorize` / `run`, load resources via `$ctx->scope()->query(...)`.  
3. Persist `tenant_id` (or equivalent) on writes from `$ctx->tenantId()`.  
4. Add `assertCannotInvokeAcrossTenant` for each capability that accepts a foreign key.  
5. For jobs: pair **D-002 actor** with **D-003 `tenantId`** as first-class job fields — never in capability input for system actors.  
6. Grep resolvers for `input[` / `_tenant` — system scope must not appear there.

### Testing (P2-005)

```php
// System job without first-class tenant fails when tenancy required
expect(fn () => RunCapability::dispatch(
    name: 'daily-reconciliation',
    input: [],
    actingAs: SystemActor::named('scheduler'),
    // tenantId omitted
))->toThrow(MissingJobTenantException::class);
// or job runs and ScopeResolver throws UnresolvedScopeException

// Smuggled input tenant must not become scope
RunCapability::dispatchSync(
    name: 'daily-reconciliation',
    input: ['_tenant_id' => 'evil_tenant'],
    actingAs: SystemActor::named('scheduler'),
    tenantId: 'tenant_7',
);
Capability::assertLastScopeTenant('tenant_7'); // not evil_tenant

// Even if input alone is provided, resolver must not honor it for SystemActor
RunCapability::dispatchSync(
    name: 'daily-reconciliation',
    input: ['_tenant_id' => 'evil_tenant'],
    actingAs: SystemActor::named('scheduler'),
    // no tenantId
);
// → UnresolvedScopeException / MissingJobTenantException — never scoped to evil_tenant
```

### Relationship to D-002

| Decision | Answers |
|---|---|
| **D-002** | *Who* is invoking? (`User` \| `SystemActor`) |
| **D-003** | *Where* (which tenant/team) may they touch? (`CapabilityScope`) |
| **P2-005** | Tenant for system actors comes from **trusted job/context fields**, not wire input |

Both actor and scope must be non-null/usable before `run` for multi-tenant apps. Neither replaces the other.

---

## Approval flow

When `needsApproval` returns `true`:

1. Registry **does not** call `run`.
2. An **approval request** is stored (state machine — see [D-006](#d-006--approval-store-replay-staleness-double-accept-and-crash-recovery)).
3. Surfaces receive a structured `approval_required` result (approval id + summary).
4. A human accepts/rejects via HTTP, UI, product CLI, or **Telegram** (signed short-lived callbacks).
5. On accept: **re-validate** input, check approver policy, transition to `executed` **once**, then `run` under the original caller + scope + idempotency key (D-005). Concurrent/double accept does not double-`run`. Crash between decision and execution is recovered — see [Crash recovery (P2-004)](#crash-recovery-p2-004).

`ApprovalManager` owns the state machine and notifiers (`http`, `cli`, `telegram`) — channel adapters never execute capabilities themselves.

```bash
# On the user's machine (product CLI)
capabilities approvals list
capabilities approvals accept {id}
capabilities approvals reject {id} --reason="Amount too high"
```

```http
POST /capabilities/approvals/{id}/accept
Idempotency-Key: 01J8Z…   # optional; defaults to key stored on the approval row

POST /capabilities/approvals/{id}/reject
```

---

## D-006 — Approval store: replay, staleness, double-accept, and crash recovery

| | |
|---|---|
| **ID** | D-006 |
| **Category** | Reliability / security |
| **Effort** | Medium |
| **Impact** | Security, reliability |
| **Also closes** | P2-004 (approved → executed limbo) |

### Problem

Storing “validated input; on accept, run with preserved caller” is necessary but incomplete. Gaps:

| Gap | Failure mode |
|---|---|
| Double-click / concurrent accept | Double `run` / double-charge |
| Stale payload | Input was valid at request time; customer deleted or moved tenant before accept |
| Unclear approver | Requester only? Any staff? Role-based? |
| No expiry | Pending approvals live forever; surprise execution weeks later |
| Telegram callbacks | Unsigned or long-lived button payloads → forgery / replay by the wrong user |
| **Crash after `approved`, before `executed`** (**P2-004**) | Process dies mid-accept; row stuck; re-accept blocked if only `pending` can accept; operators have no repair path |

### Decision

Approvals are a **persisted state machine** with **exactly-once execution**, **re-validation on accept**, **explicit approver authorization**, **TTL**, **signed short-lived chat callbacks**, and a **defined recovery path for `approved` limbo** (P2-004). Audit links **request → decision → execution**.

### State machine

Two implementable shapes are allowed. Pick **one** per install via config; the package ships both patterns with the same external semantics (accept is exactly-once; clients see `approval_required` then a terminal result).

#### Shape A — Intermediate `approved` + resume (default)

```text
                  ┌──────────┐
                  │ pending  │
                  └────┬─────┘
           accept/     │      \ reject          timeout
           approve     │       \                  │
                       ▼        ▼                 ▼
                 ┌──────────┐ ┌──────────┐  ┌──────────┐
                 │ approved │ │ rejected │  │ expired  │
                 └────┬─────┘ └──────────┘  └──────────┘
                      │ execute (once); crash → ResumeApprovedApprovals
                      ▼
                 ┌──────────┐
                 │ executed │  (terminal; result stored)
                 └──────────┘
```

`approved` is a **recoverable non-terminal** state: decision is durable; execution may still be in flight or aborted.

#### Shape B — Atomic `pending` → `executed` under lock

```text
                  ┌──────────┐
                  │ pending  │
                  └────┬─────┘
     accept+run        │      \ reject          timeout
     (one lock)        │       \                  │
                       ▼        ▼                 ▼
                 ┌──────────┐ ┌──────────┐  ┌──────────┐
                 │ executed │ │ rejected │  │ expired  │
                 └──────────┘ └──────────┘  └──────────┘
```

No intermediate status: under row lock, re-validate + `run` + write `result_json` + set `executed` (or terminal failure) before commit. Decision metadata (`decided_by`, `decided_at`) still stored on the row. Prefer when `run()` is short and fits the accept transaction; use Shape A when execution is long or intentionally deferred after the decision.

| Status | Meaning |
|---|---|
| `pending` | Awaiting human decision; not yet run |
| `approved` | **Shape A only:** decision recorded; execution in progress, about to start, **or stuck after crash** (recoverable) |
| `rejected` | Terminal; never runs |
| `expired` | Terminal; TTL elapsed while still pending |
| `executed` | Terminal; `run` completed (success or domain failure stored on the row) |

Optional sub-status on `executed`: `result_status` = `ok` | `failed` (domain/validation after re-validate).

#### Transitions (enforced in DB + app)

| From | To | Rule |
|---|---|---|
| `pending` | `approved` | **Shape A:** Approver authorized; conditional update / row lock |
| `pending` | `executed` | **Shape B:** Approver authorized; re-validate + `run` under same lock; or Shape A fast-path if execution finishes before commit |
| `pending` | `rejected` | Approver authorized |
| `pending` | `expired` | Scheduler / on-read TTL check |
| `approved` | `executed` | **Shape A:** Single winner of execution lock (accept path **or** resume job) |
| `executed` / `rejected` / `expired` | * | **No** further accept/reject that re-runs |

Use a **conditional update** (e.g. `UPDATE … WHERE id = ? AND status = 'pending'`) or row lock so two concurrent accepts cannot both enter execution. For Shape A, `approved → executed` uses `WHERE status = 'approved'` (or lease columns) so accept and resume cannot double-`run`.

### Exactly-once execution

```text
accept request
  → begin transaction
  → lock approval row
  → if status = executed  → return stored CapabilityResult (replay)
  → if status = rejected|expired → 409 / 410
  → if status = approved (Shape A) → join in-flight path or return 202/409 "execution in progress"
       (do not re-run; resume job owns stuck rows past lease)
  → if status ≠ pending → conflict
  → if status = pending:
       Shape A: set approved + decided_by + decided_at (+ execution_lease_until)
       Shape B: keep pending until run succeeds, then set executed in same txn
  → re-validate (portable + server-only) + re-resolve scope resources
  → if invalid → set executed + failed result (or rejected_stale — pick one; default executed/failed)
  → authorize capability as original actor (or document elevating — default: original actor)
  → run() once
  → set executed + result_json
  → commit
  → idempotency store complete (D-005) if key present
```

Double-accept after `executed` = **idempotent replay** of `result_json`, not a second `run`.

### Crash recovery (P2-004)

Exactly-once under **concurrent accept** is necessary but not sufficient. If the process dies after `status = approved` and before `executed` + result store:

| Bad outcome | Why |
|---|---|
| Stuck `approved` forever | No operator path; business action never runs |
| Re-accept forbidden | Only `pending` can accept → dead letter with a human “yes” already recorded |
| Naïve re-accept re-runs | Double charge if first `run` actually committed before crash |

**Required:** terminal-or-resume design. Implement Shape B (no limbo) **or** Shape A with a scheduled resume job. Default package path is **Shape A + resume** so accept can return after recording the decision even if `run` is heavy.

#### Shape A — `ResumeApprovedApprovals` job (normative when intermediate state is used)

```text
schedule every N minutes (default 1)
  → select rows WHERE status = 'approved'
       AND (execution_lease_until IS NULL OR execution_lease_until < now())
       AND approved_at < now() - grace (default 30s)   # avoid racing live accepts
  → for each row (chunked):
       lock / claim lease (conditional UPDATE lease)
       re-validate + re-resolve scope (same as accept)
       if invalid → executed + failed (stale)
       else run() once under original actor + caller + scope
       set executed + result_json
       complete D-005 idempotency key if present
       emit approval.executed (+ metrics)
```

| Property | Rule |
|---|---|
| **Idempotent with D-005** | Same `idempotency_key` as the original invoke; store completion prevents double domain apply if `run` was partially visible |
| **Lease / claim** | `execution_lease_until` + optional `execution_attempt` so two workers do not both `run` |
| **Not a second accept** | Resume does not require a second human click; decision already final |
| **Replay after executed** | Further accept/resume returns stored `result_json` |
| **Stuck alert** | If `approved` longer than `approval.stuck_after_seconds` (default 300), increment metric and optional log/alert |
| **Manual repair** | `php artisan capabilities:approvals-resume {id?}` forces the same code path as the scheduler |

#### Shape B — Atomic accept (no sweeper required for limbo)

When `approval.execution = 'atomic'`:

- Accept transaction holds the row lock through re-validate + `run` + `executed`.
- Process death before commit → row stays `pending`; client may retry accept (same exactly-once rules).
- Process death after commit → already `executed`; replay applies.
- **No** `approved` stuck state; `ResumeApprovedApprovals` is a no-op / not scheduled.

Trade-off: long `run()` holds DB locks and request time — prefer Shape A for heavy work.

#### Metrics (D-019)

| Metric | When |
|---|---|
| `approvals_stuck_approved_total` | Gauge or counter of rows in `approved` longer than `stuck_after_seconds` (sweeper sample) |
| `approvals_resume_total{result}` | Resume attempts: `executed_ok`, `executed_failed`, `skipped_lease`, `stale` |
| `approvals_accept_total{result}` | Accept path outcomes including `in_progress` for live `approved` |

#### Config

```php
'approval' => [
    'store' => 'database',
    'ttl_hours' => 24,
    'default_policy' => 'requester_or_role',
    // atomic = Shape B (pending → executed under lock)
    // deferred = Shape A (pending → approved → executed + resume job)
    'execution' => env('CAPABILITIES_APPROVAL_EXECUTION', 'deferred'), // deferred | atomic
    'resume' => [
        'enabled' => true,              // schedule ResumeApprovedApprovals when execution=deferred
        'every_seconds' => 60,
        'grace_seconds' => 30,          // ignore freshly approved rows (live accept in flight)
        'stuck_after_seconds' => 300,   // metric / alert threshold
        'lease_seconds' => 120,         // claim duration for resume worker
    ],
],
```

### Re-validation on accept (staleness)

Validation at **request** time is not enough. On accept, the pipeline re-runs:

1. JSON Schema (portable) on stored input  
2. Server-only rules (`exists`, etc.)  
3. **D-003** scoped re-resolve of every resource id  
4. `authorize()` for the **original actor** under current scope (default)

If the customer was deleted, moved tenants, or the actor lost permission:

- Do **not** run with stale assumptions  
- Transition to terminal failure (`executed` + failed, or explicit `failed_stale`)  
- Return a clear error to the approver  

Never “accept means force-run regardless of current state.”

### Who may approve

Configured per app and optionally overridden per capability.

```php
#[Capability(
    name: 'create-invoice',
    // …
    approvalPolicy: 'role:finance-approver', // or use config default
)]
```

| Policy (illustrative) | Who can accept/reject |
|---|---|
| `requester` | Only the original requester (self-confirm UX) |
| `requester_or_role` | Requester **or** users with a given ability/role (default) |
| `role:…` | Only users with that role/ability (requester cannot self-approve) |
| `any_staff` | Any authenticated user in the tenant with `capabilities.approve` |
| Custom | App `ApprovalPolicy` class |

Rules:

- Approver must pass **D-003 scope** (same tenant as the approval row).  
- Approver identity is stored on the row (`decided_by`).  
- **SystemActor cannot approve** (humans only).  
- Telegram: the clicking Telegram user must be **identity-linked** to an allowed approver (D-006 + messaging identity).

### Expiry

| Setting | Default |
|---|---|
| `approval.ttl_hours` | `24` |

- While `pending`, if `now > created_at + ttl` → treat as `expired` (lazy on read + scheduled sweeper).  
- Accept/reject on expired → `410 Gone` / domain error.  
- Notifiers may edit Telegram messages to “expired.”  

Capabilities may set `approvalTtlHours` lower for high-risk ops.

### Row shape (illustrative)

```text
capability_approvals
  id
  capability_name
  status                  # pending|approved|rejected|expired|executed
  scope / tenant_id
  requester_actor_type + requester_actor_id
  original_caller         # agent|mcp|http|cli|…
  input_json              # canonical validated input at request time
  input_hash
  idempotency_key         # nullable; D-005
  result_json             # set on executed
  decided_by              # user id on accept/reject
  decided_at
  decision_reason         # optional reject reason
  expires_at
  execution_lease_until   # Shape A: resume claim; null when free
  execution_attempt       # Shape A: increment on each resume claim
  messaging               # nullable { channel, chat_id, message_id }
  created_at, updated_at
```

### Telegram (and chat) callbacks

Inline buttons must **not** embed forgeable “approve id=5” alone.

| Requirement | Spec |
|---|---|
| **Signed payload** | HMAC or asymmetric token over `approval_id + action + exp + approver_hint` |
| **Short TTL** | e.g. 15 minutes (`telegram_callback_ttl_seconds`); re-send button message if needed |
| **User binding** | Callback effective only if Telegram user is linked to a Laravel user allowed by approval policy |
| **Replay** | One-time or status-gated: after `executed`/`rejected`/`expired`, callback is no-op with “already handled” |
| **No capability input in the button** | Server loads input from the approval row only |

```text
callback_data / URL token
  → verify signature + exp
  → resolve linked user
  → ApprovalManager::accept|reject(id, decidedBy: user)
  → same state machine as HTTP
```

### Audit chain

Every approval produces linked audit events:

```text
approval.requested  → approval_id, requester, capability, input redacted, idempotency_key
approval.decided    → approval_id, decided_by, decision (approved|rejected), reason
approval.executed   → approval_id, result, replay=false, via=accept|resume
approval.replayed   → approval_id, result (on double-accept)
approval.expired    → approval_id
approval.resume     → approval_id, attempt (optional; stuck recovery / P2-004)
```

Clients and support can reconstruct **request → decision → execution** without guessing — including whether execution came from the live accept path or the resume sweeper.

### Relationship to D-005 / D-022

| Concern | Owner |
|---|---|
| Same logical mutate retried across HTTP | D-005 idempotency key |
| Same approval accepted twice | D-006 state machine **and** D-005 key on execution |
| Execution after accept | Single transition to `executed` inside D-006; result also written to idempotency store when key present |
| Crash after `approved`, before `executed` | **D-006 crash recovery** — atomic Shape B **or** `ResumeApprovedApprovals` (P2-004); resume uses same D-005 key |
| `needsApproval` branches on caller | **D-022** — caller is server-derived; header spoofing must not skip gates |

### What we refuse

| Anti-pattern | Why |
|---|---|
| `status` flag without conditional updates | Race → double run |
| Accept without re-validation | Stale customer / cross-tenant after delay |
| “Any authenticated user can approve” as silent default in multi-tenant | Confused deputy |
| Eternal pending rows | Surprise runs; compliance mess |
| **`approved` without resume or atomic execution (P2-004)** | Stuck limbo; decision without repair path |
| Re-accept that blindly re-`run`s while status is `approved` | Double charge if first execution partially completed |
| Unsigned Telegram `callback_data` with only approval id | Forgery / open approve |
| Approver runs as themselves with elevated rights by default without documenting | Changes authorization semantics; if you need elevating approvers, make it an explicit policy mode |

### Testing

```php
// Concurrent accept → one run
$approval = /* pending create-invoice */;
$r1 = Capability::approvals()->accept($approval->id, $approver);
$r2 = Capability::approvals()->accept($approval->id, $approver);
expect($r1->data['invoice_id'])->toBe($r2->data['invoice_id']);
expect(Invoice::count())->toBe(1);

// Stale resource
$customer->delete();
Capability::approvals()->accept($approval->id, $approver)
    ->assertFailed(); // or assertGone / assertStale

// Wrong approver
Capability::approvals()->accept($approval->id, $randomUser)
    ->assertForbidden();

// Expiry
travel(25)->hours();
Capability::approvals()->accept($approval->id, $approver)
    ->assertExpired();

// Telegram token
$token = TelegramCallback::sign($approval->id, 'accept', $ttl);
TelegramCallback::verify($token, $tampered)->assertInvalid();

// P2-004: crash after approved — resume completes exactly once
$approval = /* force status=approved, decided_by set, no result_json */;
ResumeApprovedApprovals::dispatchSync(); // or artisan capabilities:approvals-resume
$approval->refresh();
expect($approval->status)->toBe('executed');
expect($approval->result_json)->not->toBeNull();
// second resume is replay
ResumeApprovedApprovals::dispatchSync();
expect(Invoice::count())->toBe(1);

// P2-004: live approved within grace is not double-claimed by resume
$approval = /* approved_at = now() */;
ResumeApprovedApprovals::dispatchSync();
expect($approval->fresh()->status)->toBe('approved'); // still in-flight; accept owns it
```

### Config recap

```php
'approval' => [
    'store' => 'database',
    'ttl_hours' => 24,
    'default_policy' => 'requester_or_role',
    'role_ability' => 'capabilities.approve',
    'telegram_callback_ttl_seconds' => 900,
    'execution' => 'deferred', // deferred | atomic  (P2-004)
    'resume' => [
        'enabled' => true,
        'every_seconds' => 60,
        'grace_seconds' => 30,
        'stuck_after_seconds' => 300,
        'lease_seconds' => 120,
    ],
],
```

---

## D-005 — Idempotency for mutating invokes

| | |
|---|---|
| **ID** | D-005 |
| **Category** | Reliability |
| **Effort** | Medium |
| **Impact** | Reliability |

### Problem

Agents, MCP hosts, CLI users, and HTTP clients **retry freely** (timeouts, flaky networks, agent loops). Without idempotency:

- `create-invoice` runs twice → double-create / double-charge  
- Approval **accept** is a second execution path for the same payload → double-`run` if the client retries accept  

This is table stakes for any agent-driven mutation bus.

### Decision

Support an optional **idempotency key** on invoke and on approval accept. When present, the server stores `(scope, actor, capability, key) → outcome` with a TTL and replays the outcome on repeat.

#### Wire format

| Surface | How to send the key |
|---|---|
| **HTTP** | Header `Idempotency-Key: <string>` or body `idempotency_key` (header wins) |
| **CLI** | Auto-generates a UUID per `run` unless `--idempotency-key=` is set; always sends the header |
| **MCP / AI tools** | Optional tool argument `idempotency_key` (agents should pass a stable key for a logical action) |
| **Jobs** | Optional `idempotencyKey` on `RunCapability` (recommended for at-least-once queues) |
| **Approval accept** | Same header/field; default = key from the original approval row if the invoke had one |

Key constraints (illustrative): 1–128 chars, `[A-Za-z0-9._:-]+`, opaque to the server.

#### Storage record

```text
idempotency_keys
  tenant_id / scope key
  actor_type + actor_id   # user id or system actor name
  capability_name
  idempotency_key
  request_hash            # hash of canonical input JSON (see conflict rules)
  status                  # processing | completed | failed
  result_json             # CapabilityResult on completed
  approval_id             # if gated
  created_at, expires_at  # TTL
```

Unique index: `(scope, actor, capability_name, idempotency_key)`.

Default **TTL**: 24 hours (config: `capabilities.idempotency.ttl_hours`). Expired rows may be purged; a reuse after expiry is treated as a new key.

#### Pipeline behaviour

| Situation | Behaviour |
|---|---|
| No key | Invoke runs normally (**non-idempotent path**). Document risk for mutating caps. |
| Key, first seen | Insert `processing` → authorize → run (or approval_required) → store `completed`/`failed` + result |
| Key, `completed`, **same** input hash | Return stored result (**replay**), HTTP 200, no second `run` |
| Key, `completed`, **different** input hash | **409 Conflict** — key reused with different payload |
| Key, `processing` | **409 Conflict** or **425 Too Early** — in-flight; client should retry with backoff (same key) |
| Key, `failed` (validation/auth) | Configurable: replay failure **or** allow retry; default **replay failure** for the TTL so agents don’t hammer |
| Approval accept, same key already completed | Replay stored success; no double `run` |

`readOnly: true` capabilities ignore idempotency keys (no store).

#### Capability flags

```php
#[Capability(
    name: 'create-invoice',
    // …
    idempotent: 'optional', // default for mutating: optional key supported
)]
```

| `idempotent` value | Meaning |
|---|---|
| `'optional'` (default for mutations) | Key honored if present; without key, run every time |
| `'required'` | Missing key → 400; for high-stakes creates/charges |
| `'none'` / `false` | Keys ignored (rare; document why — e.g. pure append-only log where duplicates are desired) |
| `readOnly: true` | N/A — no mutation store |

Catalog metadata may expose `"idempotent": "optional" | "required" | "none"` so CLI/agents know policy.

**Default documentation stance:** mutating capabilities are **non-idempotent unless a key is supplied**. Prefer CLI always supplying a key; prefer agents generating one key per user-visible intent.

#### CLI rules

1. Each `capabilities run` generates a new UUID key by default.  
2. `--idempotency-key=` overrides for intentional retries of the **same** logical operation.  
3. Retries after network failure should **reuse** the same key (CLI may persist last key in session for `--retry-last`).  
4. Local schema validation still runs before send; replay happens only on the server.

#### Approval path

```text
invoke (key=K) → approval_required (pending row + store K)     # D-006
accept (key=K or default K) → state machine → run once → executed + store result under K
accept again (key=K) → D-006 replay of executed result (no second run)
crash after approved  → ResumeApprovedApprovals (or atomic Shape B) → executed once under K  # P2-004
```

Reject does not execute `run`; repeated reject is a no-op after first reject (`rejected` terminal). See **D-006** for staleness, expiry, concurrent accept, and crash recovery.

#### What we refuse

| Anti-pattern | Why |
|---|---|
| Relying on clients “not to retry” | Agents and proxies always retry |
| Idempotency only on HTTP, not MCP/CLI/jobs | Dual-path reliability failure |
| Approval accept without tying to invoke key | Double-charge on double-click / double-accept |
| Global “dedupe by input only” without key | Collapses two intentional identical creates |

#### Testing

```php
$key = 'test-idem-1';

$a = Capability::invoke('create-invoice', $input, caller: 'http', idempotencyKey: $key);
$b = Capability::invoke('create-invoice', $input, caller: 'http', idempotencyKey: $key);

$a->assertOk();
$b->assertOk();
expect($b->data['invoice_id'])->toBe($a->data['invoice_id']);
expect(Invoice::query()->count())->toBe(1);

// Different body, same key
Capability::invoke('create-invoice', $otherInput, caller: 'http', idempotencyKey: $key)
    ->assertConflict();
```

Also: approval accept twice → one `run`; job with same key at-least-once → one side effect.

#### Config sketch

```php
'idempotency' => [
    'enabled' => true,
    'ttl_hours' => 24,
    'header' => 'Idempotency-Key',
    // mutating caps without a key: allow but metrics/warn in non-production
    'warn_missing_key' => env('CAPABILITIES_IDEMPOTENCY_WARN', true),
],
```

---

## Audit

Mutating capabilities (default: all non-`readOnly`) emit an audit record. Semantics for failures, transactions, and app events are defined in **[D-010](#d-010--transaction--side-effect-consistency)** — not left implicit.

Typical fields:

- capability name  
- caller surface  
- user id / system actor  
- scope / tenant id  
- idempotency key + replay flag  
- redacted input  
- result status  
- duration  
- approval id (if any)

```php
#[Capability(name: 'list-customers', readOnly: true, surfaces: ['agent', 'mcp'])]
```

Read-only capabilities skip audit unless `audit: true` is forced.

Query helpers (planned):

```php
Capability::audit()
    ->for('create-invoice')
    ->caller('mcp')
    ->latest()
    ->get();
```

Default **`audit.mode = best_effort`**: a successful domain `run` is not rolled back if audit persistence fails; the bus queues/retries audit (outbox) when `audit.required` is true. Set **`audit.mode = strict`** only when you intentionally couple business success to audit write success.

---

## D-010 — Transaction / side-effect consistency

| | |
|---|---|
| **ID** | D-010 |
| **Category** | Reliability |
| **Effort** | Medium |
| **Impact** | Reliability |

### Problem

Pipeline order is roughly: validate → authorize → approve? → **run** → audit / events.

Undefined today without an explicit policy:

| Scenario | Ambiguity |
|---|---|
| `run()` commits invoices; audit insert throws | Is the invoice rolled back? Is the client told failure? |
| `run()` fires domain events that fail mid-listener | Partial side effects? |
| Outer `DB::transaction` wraps run + audit | Money mutation blocked by logging? |
| Audit is “best effort” but not documented | Operators cannot reason about compliance vs availability |

### Decision

1. **`run()` owns domain transactions** — the capability (or domain service it calls) opens/commits what it needs.  
2. **Registry outer transaction is opt-in** (`transactions.wrap_run`, default **false**).  
3. **Audit is at-least-once when required**, via queue/outbox — not “silently drop.”  
4. **Audit failure does not roll back successful domain mutations** unless **`audit.mode = strict`**.  
5. **Domain-neutral bus events** are emitted for app listeners: `CapabilityInvoked`, `CapabilityFailed`, `CapabilityApprovalRequested` (and decision/executed variants from D-006).

### Transaction boundaries

```text
┌─ Registry (no default outer txn) ─────────────────────────┐
│  validate · actor · scope · idempotency · authorize       │
│  approval gate                                            │
│                                                           │
│  ┌─ run() / domain ─────────────────────────────────────┐ │
│  │  DB::transaction { … invoices, ledger … }  // owned  │ │
│  │  or multiple explicit txns / side effects            │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                           │
│  idempotency store complete                               │
│  audit record  ──► sync write and/or outbox → queue       │
│  event(CapabilityInvoked) ──► afterCommit listeners       │
└───────────────────────────────────────────────────────────┘
```

| Setting | Behaviour |
|---|---|
| `transactions.wrap_run = false` (**default**) | Registry does not wrap `run()`. Domain commits independently. |
| `transactions.wrap_run = true` | Registry wraps `run()` (+ optional sync audit) in one `DB::transaction`. **Only** for apps that understand coupling; not default for payment-like work. |

**Guidance:** Prefer domain-owned transactions. Use outer wrap only for tiny, single-connection apps where you want “all or nothing” including sync audit in the same connection.

### Audit modes

| `audit.mode` | On audit write failure after successful `run` |
|---|---|
| **`best_effort`** (default) | Invoke returns **success** with domain result. Audit is enqueued (outbox) if `audit.required`; otherwise log error + metric. **Domain state remains committed.** |
| **`strict`** | Invoke returns **failure** (or 500). Domain is rolled back **only if** it participated in an uncommitted outer transaction or the capability explicitly rolls back. If domain already committed inside `run()`, strict mode **cannot** un-commit — document that strict + domain-owned commit requires the capability to throw before commit when audit pre-write fails, or use outbox-first. |

Practical recommendation:

- **Default production:** `best_effort` + `required: true` + **outbox/queue** for at-least-once audit.  
- **`strict`:** compliance environments that accept availability tradeoffs and design `run()` accordingly (often: write audit intent first, or single shared transaction).

```php
'audit' => [
    'enabled' => true,
    'mode' => 'best_effort', // or strict
    'required' => true,      // must not silently drop
    'driver' => 'database',  // or queue
    'queue' => 'capabilities-audit',
],
```

### Outbox / at-least-once

When audit must not be lost:

```text
after successful run (same DB connection when possible):
  insert audit_outbox (pending)   // durable intent
  commit domain txn (if domain used same connection carefully)
  // or: domain commits first, then outbox insert with retry

queue worker:
  drain outbox → write audit_events → mark outbox done
  at-least-once delivery; consumers must tolerate duplicates
```

Idempotency keys (D-005) and audit rows should include enough identity to dedupe (`invocation_id`).

### Domain events vs bus events

| Kind | Who emits | Purpose |
|---|---|---|
| **Domain events** | Inside `run()` / domain services | Business language (`InvoiceCreated`) |
| **Bus events** | Registry | Cross-cutting (`CapabilityInvoked`) |

Bus events (always available when `events.enabled`):

| Event | When |
|---|---|
| `CapabilityInvoked` | `run` succeeded (includes name, actor, scope, caller, duration, result summary, invocation_id) |
| `CapabilityFailed` | Validation/auth/`run` threw or returned failure (after the attempt) |
| `CapabilityApprovalRequested` | Entered pending approval (D-006) |
| `CapabilityApprovalDecided` | approved/rejected |
| `CapabilityApprovalExecuted` | post-accept `run` finished |

Listeners should use **`afterCommit()`** when they touch DB, so they do not run on rolled-back domain work.

Domain events fired inside `run()`:

- Are the **app’s** responsibility (ShouldDispatchAfterCommit, queues, failure handling).  
- The registry does not swallow or retry arbitrary domain listeners.  
- Document: partial domain event failure mid-`run` is a **domain bug** unless the capability uses a single transaction + afterCommit.

### Failure matrix (normative)

| Stage fails | Domain writes | Audit | Client sees |
|---|---|---|---|
| Before `run` | None | Optional deny audit | 4xx/deny |
| `run` throws / domain rolls back | None (if txn correct) | `CapabilityFailed`; optional audit fail row | Error |
| `run` succeeds; audit sync fails; **best_effort** | **Kept** | Outbox/retry | **200 + result** |
| `run` succeeds; audit fails; **strict** + outer txn | Rolled back if not yet committed | Fail | Error |
| `run` succeeds; audit fails; **strict** + domain already committed | **Kept** (cannot magic rollback) | Error logged; invoke may still surface error — **avoid this combo** | Documented footgun |
| Idempotent replay | None new | May skip or mark replay | Prior result |

### What we refuse

| Anti-pattern | Why |
|---|---|
| Undefined “audit after run” with no mode | Operators cannot reason |
| Default outer transaction wrapping money + audit | Logging outage = payment outage |
| Silent drop of audit on failure | Compliance hole |
| Requiring every capability to open no transactions | Unrealistic; domain owns consistency |
| Firing bus events before domain commit by default | Listeners see phantom success |

### Testing

```php
// best_effort: domain survives audit failure
Audit::shouldReceive('write')->andThrow(new RuntimeException('disk full'));
$result = Capability::invoke('create-invoice', $input, caller: 'http');
$result->assertOk();
expect(Invoice::count())->toBe(1);
// outbox has pending audit row when required

// bus event after success
Event::fake([CapabilityInvoked::class]);
Capability::invoke('create-invoice', $input, caller: 'http')->assertOk();
Event::assertDispatched(CapabilityInvoked::class);
```

### Package layout (additions)

```text
Events/
  CapabilityInvoked.php
  CapabilityFailed.php
  CapabilityApprovalRequested.php
  …
Audit/
  AuditLogger.php
  AuditOutbox.php
  WriteAuditJob.php
```

---

## Catalog

Agents, UIs, MCP hosts, and the product CLI discover capabilities via HTTP. **The wire contract is JSON Schema only** — same documents as [Type safety & schemas](#type-safety--schemas). Laravel rule strings never appear in the catalog (they are server-only on the DTO; see `CreateInvoiceInput::rules()`).

```http
GET /capabilities
GET /capabilities/create-invoice
```

### List (illustrative)

Compact index; clients that need full schemas call describe (or use `?include=schemas`).

```json
{
  "capabilities": [
    {
      "name": "create-invoice",
      "description": "Create an invoice for a customer.",
      "surfaces": ["agent", "mcp", "http", "cli"],
      "readOnly": false,
      "schema_version": "1",
      "idempotent": "optional",
      "aliases": [],
      "deprecated": false,
      "successor": null
    }
  ]
}
```

### Describe (illustrative) — `GET /capabilities/create-invoice`

```json
{
  "name": "create-invoice",
  "description": "Create an invoice for a customer.",
  "surfaces": ["agent", "mcp", "http", "cli"],
  "readOnly": false,
  "schema_version": "1",
  "input_schema": {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "additionalProperties": false,
    "required": ["customer_id", "amount_cents", "currency"],
    "properties": {
      "customer_id": { "type": "integer", "minimum": 1 },
      "amount_cents": { "type": "integer", "minimum": 1 },
      "currency": { "type": "string", "minLength": 3, "maxLength": 3 },
      "memo": { "type": ["string", "null"], "maxLength": 500 }
    }
  },
  "output_schema": {
    "type": "object",
    "required": ["invoice_id"],
    "properties": {
      "invoice_id": { "type": "integer" }
    }
  }
}
```

| Field | Meaning |
|---|---|
| `input_schema` / `output_schema` | **JSON Schema** — portable; CLI/MCP/AI use this |
| `schema_version` | Cache invalidation for CLI |
| *(not present)* | Laravel `required\|integer\|exists:…` strings — **never in catalog** |

MCP tool definitions and AI tool schemas are derived from `input_schema`, not from a second hand-written copy.

---

## D-004 — Catalog ships JSON Schema only

| | |
|---|---|
| **ID** | D-004 |
| **Category** | Consistency / contracts |
| **Effort** | Trivial |
| **Impact** | Maintainability, DX |

### Problem

An earlier catalog example used Laravel rule strings (`"customer_id": "required|integer|exists:..."`) while the type-safety section declared **JSON Schema** as the cross-surface source of truth. Two contracts in one README — implementers copy the wrong one; MCP/AI/CLI cannot consume rule strings portably.

### Decision

- Catalog and describe responses expose only **`input_schema` / `output_schema` (JSON Schema)** plus metadata.  
- Laravel / server-only rules live exclusively on the DTO (`rules()`, `exists`, tenancy re-resolve) and are **not** serialized to the catalog.  
- Delete any rule-string catalog blobs; keep a single illustrative JSON Schema (aligned with the type-safety section).

---

## Testing

```php
use Rawphp\Capabilities\Facades\Capability;
use Rawphp\Capabilities\Testing\FakeCapabilities;

public function test_create_invoice_via_registry(): void
{
    $this->actingAs($user = User::factory()->create());

    $result = Capability::invoke('create-invoice', [
        'customer_id' => Customer::factory()->for($user->tenant)->create()->id,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ], caller: 'http');

    $result->assertOk();
    $this->assertDatabaseHas('invoices', [
        'id' => $result->data['invoice_id'],
        'tenant_id' => $user->current_tenant_id,
    ]);
}

public function test_agent_surface_requires_approval_for_large_amounts(): void
{
    FakeCapabilities::partial(); // or real registry

    $result = Capability::invoke('create-invoice', [
        'customer_id' => 1,
        'amount_cents' => 500_000,
        'currency' => 'USD',
    ], caller: 'agent');

    $result->assertApprovalRequired();
}

public function test_cannot_create_invoice_for_other_tenant_customer(): void
{
    $userA = User::factory()->create();
    $customerB = Customer::factory()->for(Tenant::factory())->create();

    $this->actingAs($userA);

    Capability::assertCannotInvokeAcrossTenant(
        name: 'create-invoice',
        input: [
            'customer_id' => $customerB->id,
            'amount_cents' => 100,
            'currency' => 'USD',
        ],
        foreignTenant: $customerB->tenant,
    );
}
```

Pest-friendly fakes: freeze catalog, assert capability was invoked with input, assert not double-run under approval, **assert cross-tenant deny on every mutating capability that accepts resource ids**.

**Parity and snapshots (D-020)** — required package helpers (real argument shapes):

```php
// Durable snapshot file locks input_schema + output_schema (name-only call does NOT lock):
Capability::assertSchemaSnapshot(
    'create-invoice',
    base_path('tests/fixtures/capability-schemas/create-invoice.schema.json'),
);
// Or conventional dir: Capability::assertSchemaSnapshot('create-invoice', null, $dir);
// Or in-memory: Capability::assertSchemaSnapshot('create-invoice', [
//     'input_schema' => [/* … */], 'output_schema' => [/* … */],
// ]);

Capability::assertParity('create-invoice', [
    'input' => [/* valid tenant-scoped */],
    'surfaces' => ['http', 'registry', 'ai'], // required non-empty; empty options rejected
    'assert' => fn ($result) => expect($result->data['invoice_id'])->toBeInt(),
]);
```

Registry unit tests and adapter contract tests must share the same schema snapshot document (file or envelope). Package helpers are **unit-path** (registry/adapters with mocks/fakes) — not a live multi-surface HTTP/feature suite.

---

## Package layout (planned)

Three deliverables; **messaging is never folded into core** (D-007).

```text
# 1) Core — rawphp/laravel-capabilities
src/
  Attributes/Capability.php
  Contracts/
    DefinesCapability.php
    ScopeResolver.php
    ScopedQueryFactory.php
    ConversationIngress.php      # messaging package implements against this
    ConversationReply.php
    ConversationIdentity.php
    ApprovalNotifier.php         # http/cli in core; telegram in messaging pkg
  Support/
    CapabilityContext.php
    CapabilityResult.php
    CapabilityScope.php
    SystemActor.php
  Registry/CapabilityRegistry.php
  Pipeline/
    ResolveActor.php
    ResolveTenantFromCaller.php
    IdempotencyGuard.php
  Idempotency/IdempotencyStore.php
  Schema/                        # DTO → JSON Schema, validators
  Adapters/
    AdapterApi.php                 # D-011 internal bridge version
    PeerVersionProbe.php
    Ai/
      AiToolAdapter.php            # interface
      AiToolAdapterV1.php
    Mcp/
      McpToolAdapter.php
      McpToolAdapterV1.php
      McpAuthProfileResolver.php   # D-023: PAT | integration | delegated → actor
    Http/
      CapabilityController.php      # ONE invoke/catalog API (D-009)
      AuthController.php             # token + device-code (shared by CLI & API)
      ApprovalController.php
    RunCapabilityJob.php
  Http/
    DetectsCaller.php                # D-022: credential class → caller; header telemetry/downgrade only
    CliJsonEnvelope.php              # optional Accept: vnd.capabilities.cli+json
  Approval/
    ApprovalManager.php
    ApprovalStateMachine.php
    ApprovalPolicy.php
    ResumeApprovedApprovals.php      # D-006 / P2-004: sweep stuck approved → executed
    Notifiers/HttpApprovalNotifier.php
    Notifiers/CliApprovalNotifier.php
  Audit/
    AuditLogger.php
    AuditOutbox.php
    WriteAuditJob.php
  Events/
    CapabilityInvoked.php
    CapabilityFailed.php
    CapabilityApprovalRequested.php
    CapabilityApprovalDecided.php
    CapabilityApprovalExecuted.php
  Facades/Capability.php
  CapabilitiesServiceProvider.php
config/capabilities.php
routes/capabilities.php
database/migrations/...          # approvals, idempotency, audit_outbox — NOT telegram identities
tests/

# 2) Messaging — rawphp/laravel-capabilities-messaging
#    requires: rawphp/laravel-capabilities, laravel/ai
src/
  Telegram/
    TelegramAdapter.php
    TelegramWebhookController.php
    TelegramCallbackSigner.php
    ProcessTelegramUpdate.php
  Identity/IdentityLinker.php
  Threads/ThreadStore.php
  Notifiers/TelegramApprovalNotifier.php
  MessagingServiceProvider.php
config/capabilities-messaging.php
routes/messaging.php
database/migrations/...          # telegram_user_links, message threads
tests/

# 3) Client — rawphp/capabilities-cli (Go — D-016)
cmd/capabilities/
  main.go
internal/
  auth/          # keychain token store
  catalog/
  run/
  mcpstdio/
  api/           # HTTP client → single capability API (D-009)
dist/            # goreleaser binaries + install.sh + brew formula
```

The CLI never embeds domain `run()`. Messaging never embeds domain `run()`. Only the registry executes capabilities.

---

## Versioning principles

1. **Stable capability names** are a public contract — renames require [D-012](#d-012--capability-name-deprecation-protocol) (aliases + dual-name period), not silent breaks.
2. Prefer additive schema changes; use deprecation windows for required field changes.
3. **Peer adapters** (`laravel/ai`, `laravel/mcp`) follow [D-011](#d-011--peer-package-churn-laravelai--laravelmcp): support matrix, contract tests, adapter API version, fail/disable on break.
4. Never put provider secrets or raw MCP OAuth into capability `run` inputs.
5. Semver of this package: breaking changes to registry/HTTP/capability attributes are major; adapter-only fixes for peer minors may be minor/patch if the public capability API is unchanged.

---

## Next design pass (D-012–D-021)

Decisions below are **normative for v1 / v0.2** so external CLIs and agents do not bake in ambiguity.

---

### D-012 — Capability name deprecation protocol

| | |
|---|---|
| **ID** | D-012 |
| **Category** | Contracts |
| **Effort** | Small–medium |
| **Impact** | Maintainability, DX |

**Problem:** Names are a public contract, but without `aliases` / `deprecated` / `successor`, cached CLI/agent catalogs break on rename.

**Decision:** Catalog and describe include:

```json
{
  "name": "create-invoice",
  "aliases": ["invoice.create"],
  "deprecated": false,
  "deprecated_at": null,
  "successor": null,
  "sunset_at": null
}
```

| Field | Meaning |
|---|---|
| `name` | Canonical name (preferred in new code) |
| `aliases` | Still invokeable; resolve to canonical before `run` |
| `deprecated` | Clients should migrate; warn in CLI |
| `successor` | Canonical replacement name when renaming |
| `sunset_at` | After this, alias/name may 410 |

Invoke accepts **canonical or alias**. Dual-name period: both work until `sunset_at`. Attribute:

```php
#[Capability(
    name: 'create-invoice',
    aliases: ['invoice.create'],
    deprecated: true,
    successor: 'create-invoice-v2',
    sunset_at: '2027-01-01',
)]
```

---

### D-013 — Rate limiting and agent loop protection

| | |
|---|---|
| **ID** | D-013 |
| **Category** | Reliability / security |
| **Effort** | Medium |
| **Impact** | Reliability, security |

**Problem:** Agents and MCP hosts retry and loop; unlimited `run` is abuse and cost.

**Decision:** Laravel `RateLimiter` integration on the registry (all surfaces).

```php
'rate_limits' => [
    'enabled' => true,
    // keys composed of: actor + capability + surface (+ tenant)
    'defaults' => [
        'per_minute' => 60,
        'per_capability_per_minute' => 30,
    ],
    'agent_turn' => [
        'max_tool_calls' => 16, // AI adapter / messaging turn budget (D-008 related)
    ],
],
```

- Exceeded → stable error `rate_limited` (HTTP 429, CLI exit **6**).  
- AI adapter stops the tool loop when `max_tool_calls` hit and returns a structured message to the model.  
- Per-capability overrides: `#[Capability(rateLimit: ['per_minute' => 10])]`.

---

### D-014 — Output contract enforcement default on

| | |
|---|---|
| **ID** | D-014 |
| **Category** | Contracts |
| **Effort** | Small |
| **Impact** | Reliability (agent/MCP loops) |

**Problem:** Optional output validation lets broken `run()` return shapes that break tool loops.

**Decision:**

```php
'validation' => [
    'validate_output' => true, // v1 default — ON
    // readOnly may skip if no output schema declared
],
```

- If `output` / `outputSchema` is declared → always validate after `run` when `validate_output` is true.  
- Failure → do not return body to agent as success; emit `CapabilityFailed`, log, HTTP 500-class envelope with `code: output_invalid`.  
- Authors may set `validate_output: false` only with explicit reason (rare).

---

### D-015 — Package-native DTO primary; Spatie optional bridge

| | |
|---|---|
| **ID** | D-015 |
| **Category** | DX / dependencies |
| **Effort** | Medium |
| **Impact** | Adoption |

**Problem:** Docs said “Spatie or package-native” without a primary story → fragmented apps.

**Decision: (a) package-native first.**

| Layer | Choice |
|---|---|
| **Primary** | `CapabilityData` + `#[Field]` + reflection → JSON Schema; `rules()` for server-only |
| **Contract** | `SchemaProvider` interface (`jsonSchema(): array`, `validate(array): object`) |
| **Optional bridge** | Spatie Laravel Data adapter implementing `SchemaProvider` when `spatie/laravel-data` is installed |
| **Escape hatch** | Any class implementing `SchemaProvider` |

v1 docs and generators use **only** package-native examples. Spatie is documented under “Integrations,” not the happy path.

---

### D-016 — CLI primary language: Go

| | |
|---|---|
| **ID** | D-016 |
| **Category** | Architecture |
| **Effort** | Medium (build) |
| **Impact** | v0.2 schedule, install UX |

**Problem:** `go|ts|php` undecided delays v0.2 and fragments docs.

**Decision: Go is the primary product CLI** for v0.2.

| Why Go | |
|---|---|
| Single static binary | brew / curl install without Node/PHP on user machines |
| Keychain / libsecret | Mature patterns for token storage |
| MCP stdio | Straightforward long-running process |
| Cross-compile | darwin/linux/windows amd64/arm64 |

| Later (not v0.2 blockers) | |
|---|---|
| TypeScript SDK | `npm` thin client for Node agents |
| PHP Phar | Optional; not the product install story |

Docs refer to one binary name: `capabilities`. No multi-language CLI matrix in v0.2.

---

### D-017 — Canonical definition: attributed class

| | |
|---|---|
| **ID** | D-017 |
| **Category** | Architecture / DX |
| **Effort** | Small |
| **Impact** | Registration correctness |

**Problem:** Attribute class + fluent + “implements only” = three discovery mechanisms and dual registration bugs.

**Decision:**

| Style | Status |
|---|---|
| **`#[Capability]` + `DefinesCapability` class** under `config path` | **Canonical** — auto-discovered |
| **`Capability::define()` fluent** | **Alternate** — explicit register; same `CapabilityDefinition` object |
| Ad-hoc invokable without registry | **Forbidden** for product mutations |

One discovery pass builds the registry. Fluent calls insert into the same map (duplicate name = boot exception). Tests assert single definition per name.

---

### D-018 — Shared error envelope (HTTP + CLI)

| | |
|---|---|
| **ID** | D-018 |
| **Category** | Contracts |
| **Effort** | Small–medium |
| **Impact** | DX, agent reliability |

**Problem:** CLI exit codes exist; HTTP lacks a stable machine envelope.

**Decision:** Every non-success response (and CLI stderr JSON) uses:

```json
{
  "ok": false,
  "error": {
    "code": "validation_failed",
    "message": "Human-readable summary",
    "violations": [
      { "field": "customer_id", "message": "must be integer" }
    ],
    "approval_id": null,
    "request_id": "01J…",
    "retryable": false
  }
}
```

| `code` (normative set) | HTTP | CLI exit |
|---|---|---|
| `validation_failed` | 422 | 2 |
| `unauthenticated` | 401 | 3 |
| `forbidden` | 403 | 3 |
| `approval_required` | 202 / 409-with-body | 4 |
| `domain_error` | 422 / 409 | 5 |
| `rate_limited` | 429 | 6 |
| `conflict` (idempotency) | 409 | 5 |
| `not_found` | 404 | 5 |
| `output_invalid` | 500 | 5 |
| `internal` | 500 | 1 |

Success:

```json
{
  "ok": true,
  "data": { "invoice_id": 1 },
  "meta": {
    "request_id": "01J…",
    "capability": "create-invoice",
    "idempotent_replay": false
  }
}
```

CLI `--json` prints the same envelope; exit code maps from `error.code`.

---

### D-019 — Observability (metrics + tracing)

| | |
|---|---|
| **ID** | D-019 |
| **Category** | Ops |
| **Effort** | Medium |
| **Impact** | Reliability |

**Problem:** Product audit ≠ ops metrics/traces.

**Decision:**

| Signal | What |
|---|---|
| **Metrics** | `capabilities_invoke_total{capability,caller,status}`, latency histogram, `approval_required_total`, `approvals_stuck_approved_total` (D-006 / P2-004), `approvals_resume_total{result}`, `authz_deny_total`, `rate_limited_total`, `idempotent_replay_total` |
| **OpenTelemetry** | Span `capabilities.invoke` attributes: `capability`, `caller`, `surface`, `tenant_id`, `actor_type`, `approval_id`, `idempotency_key` (hashed if needed) |
| **Failed jobs** | `RunCapability` + messaging `ProcessTelegramUpdate` use Laravel failed-job hooks; tag with capability/channel |
| **Driver** | Laravel Pulse / OTel exporter optional; package emits via contracts `Metrics` / `Tracer` with log fallback |

Audit remains the compliance trail (D-010); metrics/traces are for operators.

---

### D-020 — Parity tests and schema snapshots as package features

| | |
|---|---|
| **ID** | D-020 |
| **Category** | Testing |
| **Effort** | Medium |
| **Impact** | Dual-path prevention |

**Problem:** Fakes alone do not prevent HTTP vs registry vs AI adapter drift.

**Decision:** Ship test helpers on `CapabilityRegistry` / `Capability` facade with the argument shapes below (implemented unit-path DX — not a live multi-surface HTTP/feature suite):

```php
// assertSchemaSnapshot — lock input_schema + output_schema; returns true on match;
// throws SchemaSnapshotException on drift/missing file (names capability + side).
// Modes: file path | conventional directory | in-memory envelope.
// Name-only (no path/envelope/dir) resolves the capability and returns true without comparing.
Capability::assertSchemaSnapshot(
    'create-invoice',
    base_path('tests/fixtures/capability-schemas/create-invoice.schema.json'),
);
// Capability::assertSchemaSnapshot('create-invoice', null, $snapshotDirectory);
// Capability::assertSchemaSnapshot('create-invoice', [
//     'input_schema' => [/* JSON Schema */],
//     'output_schema' => [/* JSON Schema */],
// ]);

// assertParity — same success/deny class across listed surfaces via registry invoke.
// options.surfaces required (non-empty); empty options throw InvalidArgumentException.
// Aliases: ai → agent, registry → http. Optional assert callback runs on success results only.
Capability::assertParity('create-invoice', [
    'input' => [/* valid */],
    'surfaces' => ['http', 'registry', 'ai'], // invoke each surface path (unit-path / mocks)
    'assert' => fn ($result) => expect($result->data['invoice_id'])->toBeInt(),
]);
```

| Helper | Role |
|---|---|
| `assertSchemaSnapshot` | CI fails if catalog **input_schema** / **output_schema** drift without intentional snapshot update (file path, conventional dir, or envelope) |
| `assertParity` | Same input → same success/deny class across listed surfaces (registry choke point; not empty-arg) |
| `assertCannotInvokeAcrossTenant` | D-003 |
| Adapter group tests | D-011 |

Document: app CI should run schema snapshots for every capability before release. Consumer-facing usage: package README **Testing helpers (D-020)** and [first-capability tutorial](tutorials/first-capability.md#7-lock-it-in-ci-d-020-helpers).

---

### D-021 — Deferred env validation for messaging (artisan-friendly boot)

| | |
|---|---|
| **ID** | D-021 |
| **Category** | DX / ops |
| **Effort** | Small |
| **Impact** | DX, reliability |

**Problem:** Hard-fail boot on missing `TELEGRAM_*` breaks `php artisan migrate` and local console when messaging is enabled in config but unused.

**Decision:**

| Check | When |
|---|---|
| Messaging package installed + surface enabled | Boot may register routes; **do not** require secrets yet |
| First webhook / `messaging:telegram-setup` / first outbound notify | **Validate secrets**; fail that request/command loudly |
| Core peer adapters (`laravel/ai`) when surface enabled | Keep boot fail/disable (D-011) — needed to register tools safely |
| `CAPABILITIES_SKIP_BOOT_CHECKS=true` | Skips **only** deferred-style checks in CI; **forbidden in production** (detect `APP_ENV=production` → ignore skip or abort) |

Document: production deploy healthcheck should hit `GET /capabilities/health` (includes messaging config readiness when surface on).

---

## D-011 — Peer package churn (`laravel/ai` / `laravel/mcp`)

| | |
|---|---|
| **ID** | D-011 |
| **Category** | Dependencies |
| **Effort** | Ongoing / medium |
| **Impact** | Maintainability, reliability |

### Problem

Official AI and MCP packages will move faster than most app code. For `agent` and `mcp` surfaces, **adapters are the whole value prop**. A one-liner “track minor versions” is not enough:

- No **support matrix** apps can trust  
- No **contract tests** against pinned peers  
- No **adapter interface version** when peer APIs rewrite  
- Boot `require_package` only checks **presence**, not **compatibility**  

Silent half-broken tools are worse than a loud boot failure.

### Decision

1. **Publish an explicit support matrix** (package `PeerSupportMatrix` + README release gate + release notes).
2. **Package CI contract tests** are **unit-only** (fixtures + mocks/fakes for tool shapes, probe, boot fail/disable). Default monorepo CI does **not** install live `laravel/ai` / `laravel/mcp`.
3. **Version the adapter layer** inside this package (`AdapterApi::V1`, …).
4. **On missing or incompatible peer:** `on_incompatible` = `fail` (default) or `disable` with CRITICAL log — no partial tool registration.
5. **Release gate:** matrix change or adapter change without a green **unit** contract suite is not shippable.
6. **Optional consumer peer-live:** apps that install real peers may run their own smoke/integration jobs against pinned minors and confirm matrix cells — package-owned but not package-default CI.

### Support matrix

See [Peer support matrix](#peer-support-matrix-maintained-in-readme--ci) and package README **Peer support / D-011 release gate**. Maintainers update the matrix when:

- Adding a new peer minor/major after unit contract fixtures stay green
- Dropping a peer version (document migration)
- Bumping `AdapterApi` when this package’s bridge API breaks

```text
composer.json (illustrative)
  suggest: laravel/ai, laravel/mcp
  # optional conflict: with known-broken peer versions

# Default package CI (monorepo policy — unit only, no live peers):
composer test:core -- --filter=PeerSupportMatrix
composer test:core -- --filter=PeerContract
composer test:core -- --filter=Adapter

# Optional consumer app job (not default package CI):
#   composer require laravel/ai laravel/mcp
#   run app-owned peer-live smoke against pinned minors
```

### Adapter interface versioning

```php
namespace Rawphp\Capabilities\Adapters;

/**
 * Internal bridge version — not the peer package version.
 * Bump when our Tool/Mcp mapping contracts change.
 */
final class AdapterApi
{
    public const V1 = 1;
    public const CURRENT = self::V1;
}

interface AiToolAdapter
{
    public function supportsInstalledPeer(): bool;

    /** @return iterable<\Laravel\Ai\Contracts\Tool|object> */
    public function toolsFor(ToolSelection $selection, CapabilityContext $ctx): iterable;
}

interface McpToolAdapter
{
    public function supportsInstalledPeer(): bool;

    public function register(ToolSelection $selection): void;
}
```

- **`supportsInstalledPeer()`** probes installed package version + required interfaces/classes (feature detection, not only `composer.lock` string).  
- Multiple adapter implementations may live behind a factory: `AiToolAdapterV1`, `AiToolAdapterV2` selected by probe.  
- Apps depend on **stable** `Capability::aiTools(profile: …)`; adapter version is an internal detail.

### Boot / runtime behaviour

| Situation | `on_incompatible = fail` (default) | `on_incompatible = disable` |
|---|---|---|
| Peer not installed, surface enabled, `require_package` | **Boot exception** | Soft-disable surface + CRITICAL log |
| Peer installed, `supportsInstalledPeer() === false` | **Boot exception** with “upgrade rawphp/… or pin peer” | Soft-disable surface + CRITICAL log + metric |
| Surface disabled | No probe required | — |

```text
// Loud disable log (never silent)
capabilities.surface.disabled peer=laravel/ai reason=incompatible
  installed=0.12.0 supported=^0.10 || ^0.11 adapter_api=1
```

Health endpoint (optional): `GET /capabilities/health` reports surface status (`up` / `disabled_incompatible` / `disabled_config`) for ops.

### Contract tests (CI)

Minimum **unit** suite (package default CI — mock/fake peers only; no live `laravel/ai` / `laravel/mcp`):

| Test | Asserts |
|---|---|
| Tool schema mapping | Capability JSON Schema → peer tool definition fields present (fixtures) |
| Invoke round-trip | Peer-shaped tool call → registry `run` → result shape peer expects (mocks) |
| Profile filter | D-008 selection still applies through adapter |
| Authorization deny | Peer call with unauthorized user does not mutate |
| Idempotency header/arg | Passed through when present |
| Missing peer | Boot/disable path as configured |
| Unsupported peer version (mock/pin) | `supportsInstalledPeer` false → fail/disable |
| Frozen fixtures / AdapterApi | Shape drift fails unit suite; bump rule via `requiresBump` |

Run on **every PR** that touches `Adapters/` or peer matrix config. Optional **consumer** peer-live jobs (install real peer minors) are app-owned and never required for default package green.

### What we refuse

| Anti-pattern | Why |
|---|---|
| “We support whatever laravel/ai is current” with no tests | Breaks production on peer release day |
| Catch-all try/catch that swallows adapter errors | Silent empty tool lists |
| Documenting support only in Discord | Matrix must be in README + CI |
| Shipping adapter rewrites without `AdapterApi` bump when call shapes change | App code can’t detect upgrade needs |

### Relationship to other decisions

| Decision | Interaction |
|---|---|
| **Compose, don’t replace** | Peers own protocols; we own bridges — bridges need maintenance |
| **D-008** | Profiles tested through real adapter mapping |
| **D-007** | Messaging has its own peer story (Bot API); same fail/disable pattern |
| Boot `require_package` | Necessary but not sufficient — D-011 adds compatibility |

### Maintainer checklist (each release)

- [ ] Support matrix (`PeerSupportMatrix`) updated
- [ ] Unit adapter contract suite green (PeerSupportMatrix / PeerContract / Adapter filters)
- [ ] Default package CI still does not install live peers
- [ ] CHANGELOG lists declared peer constraints (and any consumer peer-live notes if applicable)
- [ ] Known-bad peers `conflict`ed or documented
- [ ] `AdapterApi` bumped if bridge call shapes changed


---

## Roadmap (indicative)

**Status vs roadmap:** roadmap phases describe design scope. **Unit coverage in this monorepo does not mean Packagist ship or a frozen public API.** Markers below are honesty labels for maintainers/consumers — not marketing release status.

| Phase | Package | Scope | Unit monorepo | Residual / publish |
|---|---|---|---|---|
| **v0.1** | **core** | Registry, package-native DTOs (D-015), single HTTP API (D-009), **D-022** caller derivation, D-002/D-003, D-012 names, D-014 output validation, D-017 discovery, conversation contracts | largely covered | path/VCS only; no Packagist |
| **v0.2** | **core + cli (Go)** | Product CLI as HTTP client (D-016); CLI tokens map to `caller: cli` (D-022); schema validate; auto idempotency; error envelope (D-018) | largely covered | CLI binary releases not published |
| **v0.3** | **core** | `laravel/ai` + `laravel/mcp` adapters; **D-005** / **D-008**; **D-023** MCP auth profiles; **D-011** support matrix + adapter contract CI | unit matrix + fixtures green | **live peer CI residual** (consumer-app path) |
| **v0.4** | **core** | D-006 approval SM + **P2-004 resume/atomic crash recovery**; jobs; CLI MCP stdio; D-010 audit/events; D-013 rate limits; D-019 metrics/OTel (`approvals_stuck_approved_total`); D-020 parity helpers | largely covered; **D-020 helpers done** (`assertSchemaSnapshot` + `assertParity` unit-path) | not multi-surface live HTTP feature suite; no Packagist |
| **v0.5** | **messaging** (new package) | Telegram webhooks, identity, threads, signed callbacks, chat approvals via contracts | package unit-covered (mocked Bot API) | messaging defaults off; no Packagist |
| **v0.6** | **messaging** | Harden Telegram; schema snapshots; docs | partial / residual | first-capability tutorial residual; hardening residual |
| **Later** | **messaging** / core | Slack / WhatsApp / email adapters; Livewire helpers; OpenAPI; soft A2A | not started | future |

Cross-cutting residuals (also in root README): packaging/Packagist publish, first-capability tutorial, live peer CI. Release notes / per-package CHANGELOGs and D-020 unit helpers are **done** relative to monorepo design work — still pre-stable 0.x.

Non-goals for core: generative UI, multi-app workspaces, template galleries, Artisan-as-product-CLI, shipping Telegram in `laravel-capabilities`, full agent-native Dispatch clone.

---

## D-007 — Package boundary: messaging vs thin core

| | |
|---|---|
| **ID** | D-007 |
| **Category** | Architecture / dependencies |
| **Effort** | Medium (split) |
| **Impact** | Maintainability, DX |

### Problem

Beliefs say **thin framework, fat domain** and **not a chat UI kit**. Earlier drafts put Telegram webhooks, identity linking, thread stores, and Bot API adapters under the same server package layout and roadmap “v0.5 messaging.”

That fights the product:

- Messaging is a **product of its own** (ops, security, test matrix, env, queues).  
- In-core bots bloat installs that only need a capability bus + CLI.  
- “Optional surface” becomes a lie if Telegram code always ships.  
- The package starts to feel like **agent-native-with-PHP** instead of a **capability bus**.

### Decision

**Make the split the plan from day one of messaging work — not a later maybe.**

| Package | Composer name | Ships |
|---|---|---|
| **Core** | `rawphp/laravel-capabilities` | Registry, schema, HTTP/CLI API, AI/MCP/job adapters, approval state machine, audit, scope, idempotency, **`ConversationIngress` / related contracts** |
| **Messaging** | `rawphp/laravel-capabilities-messaging` | Telegram (first), identity, threads, webhooks, Bot API, chat approval notifier, signed callbacks |
| **CLI** | `rawphp/capabilities-cli` | Generic downloadable client |

Optional finer split later (`-telegram` only) if channel weight demands it; **do not** put Telegram in core “for convenience.”

### Core contracts (illustrative)

```php
namespace Rawphp\Capabilities\Contracts;

interface ConversationIngress
{
    /**
     * Deliver a user message into the configured agent turn.
     * Implementations live in the messaging package; core only defines the shape.
     */
    public function handle(ConversationMessage $message): ConversationTurnResult;
}

interface ApprovalNotifier
{
    public function notifyPending(ApprovalRequest $approval): void;
}
```

- Core registers HTTP/CLI notifiers.  
- Messaging package binds `ApprovalNotifier` channel for Telegram and implements ingress using `Capability::aiTools()`.  
- **Messaging never calls `run()`** except through the registry/agent tools.

### Defaults

| Surface | Core default |
|---|---|
| agent, mcp, http, cli, job | **enabled** (opt out) |
| messaging | **disabled** until messaging package installed |

### What we refuse

| Anti-pattern | Why |
|---|---|
| `Messaging/` directory inside core “until we extract” | Extraction never happens; test matrix suffers |
| Core `composer.json` requiring a bot SDK | Every bus user pays messaging cost |
| Messaging owning domain mutations | Dual path; kills the bus |
| README “optional split later” | Becomes permanent ambiguity |

### Relationship to other decisions

| Decision | Still holds |
|---|---|
| Conversation ≠ invoke | Yes — in messaging package |
| D-006 Telegram callbacks | Implemented in **messaging**; core only has approval accept HTTP API |
| D-002 / D-003 / D-005 | Core pipeline only |
| D-008 tool profiles | Messaging bots use a **named profile**, not full catalog |

---

## D-008 — Agent tool surface: profiles, not full catalog dump

| | |
|---|---|
| **ID** | D-008 |
| **Category** | Architecture / DX |
| **Effort** | Small–medium |
| **Impact** | Reliability, security |
| **Also closes** | P2-007 (meta-tools re-expand blast radius) |

### Problem

`Capability::aiTools()` with **no filter** as the happy path dumps every `agent`-surface capability into one model.

| Failure | Why |
|---|---|
| **Tool-selection degradation** | Models worsen with ~50–200 tools; overlapping descriptions increase wrong-tool calls |
| **Attack surface** | Every listed tool is reachable via prompt injection / confused deputy, even if `authorize` later denies (wasted calls, probing, social engineering) |
| **DX lie** | Allowlists exist as an optional second example; teams copy the one-liner dump |
| **Meta-tool escape hatch (P2-007)** | `list_capabilities` + `run_capability` over the **full** discoverable catalog gives a prompt-injected model a map of the attack surface plus a single invoke tool — profiles become optional again for “large apps” |

### Decision

1. **Default examples and APIs center on agent profiles / tool groups**, not a global dump.  
2. **Filter** by allowlist/profile **and** discoverability (actor + scope).  
3. **Document max tool counts** (soft warn / hard cap).  
4. **Large products:** optional progressive disclosure (`list_capabilities` + `run_capability` meta-tools) instead of mounting hundreds of tools — **meta-tools inherit the same profile/group allowlist** (P2-007). Progressive disclosure is a **listing strategy**, not an escape hatch from least privilege.

`Capability::aiTools()` with zero arguments is **deprecated / discouraged** — prefer required profile name or explicit list. If unfiltered dump remains for escape hatches, it logs a loud warning and still applies visibility filters + hard cap.

### Profiles and groups

```php
// config/capabilities.php
'surfaces' => [
    'agent' => [
        'enabled' => true,
        'profiles' => [
            'billing' => [
                'create-invoice',
                'void-invoice',
                'list-invoices',
                'get-customer',
            ],
            'support' => [
                'list-invoices',
                'get-customer',
                'preference_upsert', // example
            ],
        ],
        'max_tools_warn' => 32,
        'max_tools_hard' => 64,
    ],
],
```

```php
// On the capability — optional group tags for profile composition
#[Capability(
    name: 'create-invoice',
    surfaces: ['agent', 'mcp', 'http', 'cli'],
    groups: ['billing', 'finance'],
    // …
)]
```

```php
// Compose profile from groups
Capability::aiTools(groups: ['billing']);

// Explicit names (tests, one-off agents)
Capability::aiTools(only: ['create-invoice', 'list-invoices']);

// Named profile (preferred in app code)
Capability::aiTools(profile: 'billing');
```

MCP mirrors the API: **`mcpTools(profile: 'billing')` is required** (`require_profile` default true). Who acts on each call is **D-023** (auth profile), not “the same as full UI for this user.”

### Discoverability filter

Before a tool is added to the model/MCP list:

```text
capability has agent|mcp surface
  → in requested profile / only list / groups
  → actor may discover (canDiscover / policy)
  → under active scope (D-003)
  → include JSON Schema + description
```

| Hook | Purpose |
|---|---|
| `canDiscover(CapabilityContext): bool` | Optional on capability; default true if `authorize` would be callable for a dry probe — or simpler: gate on permission name |
| Policy ability | e.g. `capabilities.discover:create-invoice` |

**Discovery ≠ execution.** A tool may be hidden from the list and still denied in `authorize` if listed via misconfiguration. Both layers stay.

### Max tool guidance

| Band | Guidance |
|---|---|
| **≤ 15** | Comfortable for most models / tasks |
| **16–32** | OK if names/descriptions are sharp and non-overlapping |
| **33–64** | Soft warn (`max_tools_warn`); split profiles |
| **> 64** | Hard fail by default (`max_tools_hard`) unless app raises the cap knowingly |

Guidance is for **one agent turn’s tool list**, not total capabilities in the app.

Keep descriptions short and non-overlapping (same as agent-native “keep the action surface small”).

### Progressive disclosure (advanced, large products) — P2-007

When the product has many capabilities, prefer **two meta-tools** (or catalog + invoke) over mounting all of them as first-class tools:

| Tool | Role |
|---|---|
| `list_capabilities` | Search/filter the **profile-bounded** catalog for this actor (name, group, description) — read-only |
| `run_capability` | Invoke by name with JSON args — full registry pipeline **and** the **same profile allowlist** as direct tools |

**Normative:** progressive disclosure is **not** an escape hatch from least privilege. Meta-tools **inherit a profile or group filter** (same as D-008 direct mounts). `run_capability`’s effective allowlist **= that profile** (intersected with discoverability + authorize).

| Layer | Bound |
|---|---|
| Profile / groups / `only` | Hard allowlist of names meta-tools may list or run |
| `canDiscover` + scope (D-003) | Further hides names the actor should not see |
| `authorize` + approval (D-006) | Execution gate if a name still slips through |

```php
// REQUIRED: same profile the agent would use for direct tools
return [
    ...Capability::aiMetaTools(profile: 'billing'),
    // optional: pin a few hot tools for latency (also within profile)
    ...Capability::aiTools(profile: 'billing', only: ['get-today-session']),
];

// Also valid
Capability::aiMetaTools(groups: ['billing', 'finance']);
Capability::aiMetaTools(only: ['create-invoice', 'list-invoices', 'void-invoice']);

// MCP
Capability::mcpMetaTools(profile: 'billing');
```

```text
list_capabilities(query)
  → candidates = profile|groups|only ∩ agent/mcp surface ∩ canDiscover ∩ scope
  → never returns names outside that set (including mutating ones)

run_capability(name, args)
  → if name ∉ profile allowlist → tool error capability_not_in_profile (no registry run)
  → else full pipeline (schema, actor, scope, authorize, approval, run, audit)
```

| Anti-pattern (P2-007) | Why |
|---|---|
| `aiMetaTools()` with no profile/groups/only | Re-opens full discoverable catalog; profiles optional again for “large apps” |
| `list_capabilities` returns every name the user could hit in HTTP UI | Attack-surface map for prompt injection |
| `run_capability` allowlist = “anything authorize might allow” | Bypasses intentional agent/MCP profile narrowness |
| Documenting meta-tools as “use this instead of profiles when you have many caps” | Escape hatch from D-008 |

**Package defaults:** `aiMetaTools()` / `mcpMetaTools()` **require** `profile`, `groups`, or `only` (same spirit as `require_profile`). Unscoped meta-tools log CRITICAL and throw in non-local env, or are hard-forbidden when `surfaces.agent.require_profile` / `surfaces.mcp.require_profile` is true.

Tradeoff: extra hop and model skill at choosing names; gain: bounded **context size** without unbounded **privilege**. Profiles remain the default for most products; progressive disclosure only shrinks how many tool *schemas* sit in the prompt — not who may be invoked.

### What we refuse

| Anti-pattern | Why |
|---|---|
| `aiTools()` full dump as the README happy path | Guarantees copy-paste footgun |
| One “god agent” with every mutation | Prompt injection buffet |
| Relying only on `authorize` without shrinking the list | Model still wastes turns and probes |
| Identical 80-tool list on every agent class | No least privilege between personas |
| **Unscoped `aiMetaTools()` / `mcpMetaTools()` (P2-007)** | Map + single invoke tool over the whole attack surface |
| Progressive disclosure as a substitute for profiles | Listing strategy ≠ least privilege |

### Testing

```php
$tools = Capability::aiTools(profile: 'support', context: $supportUserCtx);
expect($tools)->not->toHaveKey('void-invoice'); // billing-only

$tools = Capability::aiTools(profile: 'billing', context: $userWithoutBilling);
// void-invoice filtered by canDiscover even if in profile

expect(fn () => Capability::aiTools(profile: 'everything_huge'))
    ->toThrow(TooManyToolsException::class);

// P2-007: meta-tools inherit profile
$meta = Capability::aiMetaTools(profile: 'support', context: $supportUserCtx);
$listed = $meta->listCapabilities(query: 'void');
expect($listed)->not->toContain('void-invoice');

$meta->runCapability('void-invoice', $input)
    ->assertError('capability_not_in_profile'); // never reaches authorize as success path

// Unscoped meta-tools rejected
expect(fn () => Capability::aiMetaTools())
    ->toThrow(ProfileRequiredException::class);
```

### Relationship to other decisions

| Decision | Interaction |
|---|---|
| **D-003** | Discover and run both scoped |
| **D-005 / D-006** | Fewer tools still use full invoke/approval pipeline; meta `run` does too |
| **D-007** | Messaging bot config picks a **profile** (meta-tools use that same profile) |
| Catalog HTTP | Full catalog for CLI/humans ≠ agent/MCP tool list **or** meta-tool list |
| **D-009** | CLI and generic HTTP share one invoke controller |
| **D-023** | MCP uses the same profile discipline **plus** explicit auth profiles for the principal; `mcpMetaTools(profile:)` required |

---

## D-009 — One HTTP capability API (not CLI vs HTTP controllers)

| | |
|---|---|
| **ID** | D-009 |
| **Category** | Architecture |
| **Effort** | Small (design) |
| **Impact** | Maintainability |

### Problem

This package exists to **kill dual mutation paths**. Shipping both `HttpCapabilityController` and `CliApiController` for invoke/catalog/auth recreates dual-path risk **inside the framework**:

- Two places to forget idempotency, scope, or approval  
- Two response shapes that drift  
- `caller: cli` implemented as a **separate tree** instead of metadata on one pipeline  

### Decision

**One HTTP capability API.** The product CLI is a client of that API.

| Concern | Where it lives |
|---|---|
| Catalog list/describe | `CapabilityController` |
| Invoke | `CapabilityController` → same registry pipeline as in-process `Capability::invoke` |
| Approval accept/reject | `ApprovalController` (same for UI, CLI, API) |
| Token / device-code auth | `AuthController` (usable by CLI **and** other API clients) |
| `caller: cli` vs `http` | **Server-derived** from credential class (OAuth `client_id`, Sanctum ability) — [D-022](#d-022--server-derived-caller-not-client-spoofable-header) |
| CLI ergonomics | Optional `Accept: application/vnd.capabilities.cli+json` for envelope/fields; **not** a second invoke implementation |

```text
Product CLI  ──HTTP──┐
Mobile app  ──HTTP──┼──► CapabilityController ──► Registry pipeline
Integration ──HTTP──┘         │
                              ├── DetectsCaller (credential → cli|http; D-022)
                              ├── Idempotency / Scope / Actor
                              └── run()
```

In-process callers (`Capability::invoke`, jobs, AI tool adapter) skip HTTP but hit the **same** registry pipeline. That is intentional fan-in, not dual business logic. Adapters set `caller` in code; they never read it from model/tool input.

### What may differ by caller (presentation only)

- Error message verbosity / machine-readable codes in CLI Accept type  
- Device-code polling UX on auth routes  
- Catalog field defaults (`?include=schemas`)  

### What must not differ by caller

- Validation, authorize, scope, approval, idempotency, audit  
- Which `run()` executes  
- Tenancy rules  

Approval and rate-limit **policy** may still branch on `caller` (e.g. large invoices need approval for `agent|mcp|cli` but not staff `http`) — but only because caller is **server truth**, not a spoofable header. See D-022.

### What we refuse

| Anti-pattern | Why |
|---|---|
| `CliApiController@invoke` + `HttpCapabilityController@invoke` | Dual path by construction |
| Copy-pasted catalog actions for “CLI shape” | Drift |
| Encoding CLI as a second route prefix `/cli/capabilities/…` that reimplements invoke | Same disease, different URL |
| Trusting `X-Capabilities-Caller` as the sole source of `caller` | Dual-path governance under one endpoint (P2-001) |

### Testing

```php
// Same capability, http vs cli caller metadata — one pipeline (in-process)
Capability::invoke('create-invoice', $input, caller: 'http')->assertOk();
Capability::invoke('create-invoice', $input, caller: 'cli')->assertOk();

// HTTP: CLI credential → server sets caller=cli (not a header claim)
$token = $user->createToken('cli', ['capabilities:cli'])->plainTextToken;
$this->withToken($token)
    ->postJson('/capabilities/create-invoice', $input, [
        'Idempotency-Key' => '…',
    ])
    ->assertOk();
// assert audit / context caller === 'cli'

// Spoof attempt: PAT without cli ability cannot self-upgrade via header
$apiToken = $user->createToken('api')->plainTextToken;
$this->withToken($apiToken)
    ->postJson('/capabilities/create-invoice', $input, [
        'X-Capabilities-Caller' => 'cli',
        'Idempotency-Key' => '…',
    ]);
// caller remains http (header ignored or rejected); approval rules for http apply
```

Assert there is a **single** invoker class used by the HTTP layer (static analysis / architecture test optional).

---

## D-022 — Server-derived caller (not client-spoofable header)

| | |
|---|---|
| **ID** | D-022 |
| **Category** | Security |
| **Effort** | Small |
| **Impact** | Security, reliability |
| **Closes** | P2-001 (architecture audit pass 2) |

### Problem

HTTP invoke historically allowed `X-Capabilities-Caller: http|cli` (and similar) as a signal. Policies and `needsApproval` branch on caller — e.g. large invoices need approval for `agent|mcp|cli` but not staff `http`.

Any authenticated bearer token client can send `X-Capabilities-Caller: http` and **skip** agent/cli approval rules, or send `cli` to hit different rate/approval paths. Caller becomes a **client claim**, not a **server-derived fact**. That reintroduces dual-path governance under one endpoint ([D-009](#d-009--one-http-capability-api-not-cli-vs-http-controllers) alone is not enough).

Without fixing this, D-006 approval differences by surface and D-008/D-013 surface-aware limits are theatre.

### Decision

**Derive `caller` from credential class and adapter code. Never trust a free-form header to set policy privilege.**

| Source | How `caller` is set |
|---|---|
| Sanctum personal access token | Mapped ability (e.g. `capabilities:cli` → `cli`) or token name pattern in `config/capabilities.php` `clients.token_abilities`. Unmapped → `http`. |
| OAuth / device-code (`client_id`) | Registered client type in `clients.oauth` → `cli`, `http`, etc. Unregistered → `http` (or reject if `cli` surface requires registration). |
| In-process AI adapter | Sets `caller: agent` in code when invoking the registry (model never supplies caller). |
| In-process MCP adapter | Sets `caller: mcp` in code. |
| Jobs / scheduler | Sets `caller: job` via `RunCapability` / dispatch helpers ([D-002](#d-002--job--scheduler-caller-identity)). |
| In-process app code | Explicit `Capability::invoke(..., caller: 'http'\|…)` argument — trusted only because it is **server code**, not the wire. |
| `X-Capabilities-Caller` header | **Optional telemetry** and/or **downgrade only** (see below). Never the sole authority. |

### Credential → caller matrix (normative)

| Credential | Default `caller` | Notes |
|---|---|---|
| Sanctum PAT with ability `capabilities:cli` | `cli` | Issued by CLI device-code / `capabilities auth login` |
| Sanctum PAT without mapped ability | `http` | Mobile, SPA, generic API |
| OAuth client registered as `cli` | `cli` | Product CLI public client |
| OAuth client registered as mobile / integration | `http` | Same policy bucket as staff/API unless app maps a finer type |
| `laravel/ai` tool adapter | `agent` | In-process only |
| `laravel/mcp` tool adapter | `mcp` | In-process only |
| `RunCapability` job | `job` | Requires actor ([D-002](#d-002--job--scheduler-caller-identity)) |
| Operator `php artisan capability:run` | `http` or dedicated `artisan` if configured | Still hits registry with explicit actor |

Apps may add finer labels (`mobile`, `server_integration`) **only if** they also define how those labels affect policy; the package core still treats unknown wire clients as `http` unless mapped.

### Header policy: telemetry or downgrade, never self-upgrade

Optional `X-Capabilities-Caller` may still be sent for observability or Accept-like shaping hints.

| Claimed header vs derived caller | Behaviour |
|---|---|
| Header absent | Use derived caller only |
| Header **matches** derived | No-op |
| Header requests a **stricter** bucket (e.g. derived `http`, claim `cli` where `cli` is stricter for approval) | **Allowed as downgrade** if `privilege_order` says claim is less privileged; policy uses the stricter value |
| Header requests a **more privileged** bucket (e.g. derived `cli` or `agent` path, claim `http` to skip approval) | **Ignored** (default) or **400 `caller_claim_rejected`** when `clients.reject_upgrade_attempts` is true |
| Header value unknown | Ignored |

**Privilege order** (config default): `http` is more privileged for typical approval gates than `cli` / `mcp` / `agent` (staff UI may skip gates agents cannot). Downgrade means moving toward the stricter approval/rate bucket, never away from it.

```php
// Illustrative DetectsCaller resolution
$derived = match (true) {
    $this->isInProcessAgent() => 'agent',
    $this->isInProcessMcp() => 'mcp',
    $tokenAbility = $this->mappedTokenAbility() => $tokenAbility, // e.g. cli
    $clientType = $this->mappedOauthClientId() => $clientType,
    default => 'http',
};

$claimed = $request->header('X-Capabilities-Caller');
$caller = $this->applyOptionalDowngradeOnly($derived, $claimed);
// $caller is what authorize / needsApproval / audit see
```

### What may still differ by caller (policy)

Intentional product rules remain valid **after** D-022:

```php
public function needsApproval(CreateInvoiceInput $input, CapabilityContext $ctx): bool
{
    return $input->amount_cents >= 100_000
        && in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true);
}
```

Staff/mobile HTTP tokens cannot skip this by sending `X-Capabilities-Caller: http` if they were issued as CLI, and a generic API token cannot claim `cli` to hit a different path without holding a CLI credential.

### What we refuse

| Anti-pattern | Why |
|---|---|
| Trusting `X-Capabilities-Caller` alone | Spoofable dual governance |
| Letting the model / MCP client pass `caller` in tool arguments | Same spoof, different wire |
| One shared long-lived token used by both CLI and staff UI without ability split | Collapses matrix; issue distinct credentials |
| Documenting “send this header to identify as CLI” as the primary contract | Teaches clients to claim identity |

### Config

See `clients` in [Configuration](#configuration-which-outchannels-to-support) (`oauth`, `token_abilities`, `privilege_order`, `reject_upgrade_attempts`).

### Testing

```php
/** @test */
public function bearer_cannot_spoof_http_to_skip_cli_approval_rules(): void
{
    $cliToken = $user->createToken('cli', ['capabilities:cli'])->plainTextToken;

    // Large invoice: cli requires approval
    $this->withToken($cliToken)
        ->postJson('/capabilities/create-invoice', $largeInvoice, [
            'X-Capabilities-Caller' => 'http', // spoof attempt
            'Idempotency-Key' => 'k1',
        ])
        ->assertStatus(202) // or package approval_required envelope
        ->assertJsonPath('status', 'approval_required');
}

/** @test */
public function generic_api_token_cannot_self_assign_cli_caller(): void
{
    $apiToken = $user->createToken('api')->plainTextToken;

    $this->withToken($apiToken)
        ->postJson('/capabilities/create-invoice', $input, [
            'X-Capabilities-Caller' => 'cli',
            'Idempotency-Key' => 'k2',
        ]);

    $this->assertSame('http', Audit::last()->caller);
}

/** @test */
public function ai_adapter_sets_agent_caller_in_process(): void
{
    // Tool adapter invokes registry with caller: agent; model input has no caller field
    $this->fakeAiToolCall('create-invoice', $input);
    $this->assertSame('agent', Audit::last()->caller);
}
```

### Relationship to other decisions

| Decision | Link |
|---|---|
| **D-006** | Approval branches on caller are trustworthy only with D-022 |
| **D-008 / D-013** | Surface-aware tool lists and rate limits need real caller identity |
| **D-009** | One HTTP API is necessary but not sufficient; metadata must be server-derived |
| **D-002** | Jobs already set caller in code — same pattern for HTTP credentials |
| **D-019** | Metrics `caller` label reflects derived value; optional claimed header may be a separate attribute |
| **D-023** | MCP auth profile is derived from credential class the same way; integration ≠ invented User |

### Package layout

- `Http/DetectsCaller.php` — implements matrix + downgrade rules  
- Config `clients.*` — app registration of OAuth clients and token abilities  
- Auth issuance for CLI always attaches `capabilities:cli` (or registered OAuth client)

---

## D-023 — MCP principal model and auth profiles

| | |
|---|---|
| **ID** | D-023 |
| **Category** | Authorization / security |
| **Effort** | Medium |
| **Impact** | Security |
| **Closes** | P2-006 (MCP principal model still thin) |

### Problem

Docs once summarized MCP as:

> OAuth/token user → `User`

That is **too thin**. Real MCP deployments include:

| Gap | Failure mode |
|---|---|
| **Service accounts / app-level MCP tokens** | No end user; treating the token as a staff `User` elevates or invents a person |
| **Host multi-user** | Claude Desktop / Cursor family or org account vs the product user who authorized the app — wrong person on the audit trail |
| **Confused deputy** | MCP client authorized broadly; tools act as a linked user with wider UI powers than the host should have; host prompt injection rides that user’s full capability set |

Profiles (D-008) shrink the **tool list**. They do not define **who** is acting. Without an explicit principal model, `caller: mcp` + “some User” is theatre next to D-002/D-022.

### Decision

**MCP authentication maps to one of three auth profiles.** The adapter sets `actor`, `caller: mcp`, and `mcp` context metadata **in code** from the credential — never from tool arguments.

#### Auth profile matrix (normative)

| Token type | Actor | Context / audit | Notes |
|---|---|---|---|
| **User PAT** | That `User` | `mcp.auth_profile = user_pat` | **Default.** Personal access token (or equivalent) bound to one human product user. |
| **Integration client credentials** | `SystemActor` **or** dedicated bot `User` | `mcp.auth_profile = integration`, `mcp.client_id`, actor name/id | App-level / service tokens with **no** end user. Must use `allowSystemCallers` (or bot user policies) **and** narrow `mcpTools(profile: …)`. Opt-in via `surfaces.mcp.auth.allow_integration_credentials`. |
| **User-delegated OAuth** | Delegating `User` | `mcp.auth_profile = user_delegated`, **`mcp.client_id` required**, optional host metadata | “Sign in with …” for an MCP host. Audit **both** the user and the OAuth client. Scope/consent should be narrower than full UI session when possible. |

```text
MCP host  ──credentials──► laravel/mcp adapter
                              │
                              ├─ resolve auth profile (D-023)
                              ├─ set actor (User | SystemActor | bot User)
                              ├─ set caller = mcp
                              ├─ set mcp.client_id / auth_profile on context
                              ├─ tools from mcpTools(profile: …) only (D-008)
                              └─ registry pipeline (authorize, scope, approval, audit)
```

### Confused deputy controls

| Control | Rule |
|---|---|
| **Named tool profiles** | `Capability::mcpTools(profile: …)` **required** when `surfaces.mcp.require_profile` is true (default). Unfiltered mount is error or loud deprecation — same spirit as D-008 for agents. |
| **Not full UI powers** | Document and enforce: an MCP server is **not** “every capability the user could invoke in the staff UI.” Profile ⊆ user permissions ∩ product intent for that host. |
| **Separate servers** | Prefer `Mcp::web('billing', …)` / `Mcp::web('support', …)` over one god server. |
| **Integration tokens** | Fail closed unless `allow_integration_credentials` is true; map `client_id` → registered `SystemActor` or bot user; capabilities must allow that system name. |
| **Delegated OAuth** | Store and audit `client_id`; do not collapse “Cursor’s client” and “the human” into one id. |
| **Host multi-user** | Product user = **token subject** (PAT or delegated resource owner), not “whoever is signed into the host OS account.” Family/shared host seats are out of band; our audit row is the product principal. |

### Adapter behaviour

```php
// Illustrative — McpToolAdapter on each tools/call
$credential = $this->resolveMcpCredential($request); // PAT | client_credentials | delegated

[$actor, $mcpMeta] = match ($credential->profile()) {
    'user_pat' => [$credential->user(), [
        'auth_profile' => 'user_pat',
        'client_id' => $credential->clientId(), // optional
    ]],
    'integration' => [
        $this->integrationActor($credential), // SystemActor::named(…) or bot User
        [
            'auth_profile' => 'integration',
            'client_id' => $credential->clientId(), // required
        ],
    ],
    'user_delegated' => [$credential->resourceOwner(), [
        'auth_profile' => 'user_delegated',
        'client_id' => $credential->clientId(), // required
        'host' => $credential->hostHint(),
    ]],
};

return Capability::invoke(
    $name,
    $input,
    caller: 'mcp',
    actor: $actor,
    mcp: $mcpMeta,
    idempotencyKey: $request->idempotencyKey(),
);
```

- Model/tool JSON **must not** include `actor`, `user_id`, or `client_id` as authoritative fields.  
- `authorize()` / `needsApproval()` see the resolved actor; integration `SystemActor` paths use the same allowlists as jobs (D-002).  
- Scope (D-003): user profiles use user membership; integration system actors need first-class tenant from **trusted** MCP session/app config — not tool input (P2-005).

### Config

See `surfaces.mcp.auth` and `surfaces.mcp.profiles` under [Configuration](#configuration-which-outchannels-to-support).

```php
// Registration of integration clients (illustrative)
'surfaces.mcp.auth' => [
    'default_profile' => 'user_pat',
    'allow_integration_credentials' => env('CAPABILITIES_MCP_INTEGRATION', false),
    'integration_actors' => [
        'mcp-billing-service' => 'billing-bot', // → SystemActor::named('billing-bot')
    ],
    'audit_client_id' => true,
],
```

### What we refuse

| Anti-pattern | Why |
|---|---|
| “MCP → OAuth/token user → User” as the whole model | Hides service accounts and client identity |
| Mounting `mcpTools()` without a profile | Confused deputy buffet (P2-006 + D-008) |
| Integration token silently acting as a random admin user | Invented person; broken audit |
| Trusting host-provided “acting user” headers without binding to token subject | Spoofable deputy |
| One MCP server with every mutating capability the user has in UI | Profile and UI permission sets must diverge by design |
| Tool args choosing `tenant_id` / `user_id` for the actor | Dual path; same class of bug as P2-005 |

### Testing

```php
/** @test */
public function user_pat_mcp_acts_as_that_user_only(): void
{
    $this->withMcpUserPat($alice);
    $this->mcpCall('create-invoice', $input, profile: 'billing');
    expect(Audit::last()->actor_id)->toBe($alice->id);
    expect(Audit::last()->mcp['auth_profile'])->toBe('user_pat');
}

/** @test */
public function integration_credentials_require_allowlist_and_system_actor(): void
{
    config(['capabilities.surfaces.mcp.auth.allow_integration_credentials' => true]);

    $this->withMcpClientCredentials('mcp-billing-service');
    // capability must allowSystemCallers: ['billing-bot']
    $this->mcpCall('daily-reconciliation', [], profile: 'ops');
    expect(Audit::last()->actor_type)->toBe('system');
    expect(Audit::last()->mcp['client_id'])->toBe('mcp-billing-service');
}

/** @test */
public function delegated_oauth_audits_user_and_client(): void
{
    $this->withMcpDelegatedOAuth($alice, clientId: 'cursor-mcp');
    $this->mcpCall('list-invoices', [], profile: 'support');
    expect(Audit::last()->actor_id)->toBe($alice->id);
    expect(Audit::last()->mcp['client_id'])->toBe('cursor-mcp');
    expect(Audit::last()->mcp['auth_profile'])->toBe('user_delegated');
}

/** @test */
public function mcp_tools_require_profile(): void
{
    expect(fn () => Capability::mcpTools())
        ->toThrow(ProfileRequiredException::class);
}

/** @test */
public function mcp_profile_is_not_full_user_ui_surface(): void
{
    $tools = Capability::mcpTools(profile: 'support', context: $aliceCtx);
    expect($tools)->not->toHaveKey('void-invoice');
    // even if Alice can void-invoice in the HTTP UI
}
```

### Relationship to other decisions

| Decision | Link |
|---|---|
| **D-002** | Integration MCP may use `SystemActor` + `allowSystemCallers` like jobs |
| **D-003** | Scope from actor membership or trusted integration tenant — not tool input |
| **D-008** | `mcpTools(profile:)` is mandatory product practice; same least-privilege as agents |
| **D-022** | Credential class derives caller/auth profile; clients do not self-describe as user |
| **D-006 / D-013** | Approvals and rate limits can branch on `caller=mcp` and `mcp.client_id` |
| **D-019** | Metrics/traces include `mcp.auth_profile` and `client_id` (hashed if needed) |

### Package layout

- `Adapters/Mcp/McpAuthProfileResolver.php` — PAT / integration / delegated → actor + meta  
- `Adapters/Mcp/McpToolAdapter*.php` — invokes registry with `caller: mcp`  
- Config `surfaces.mcp.auth` + `surfaces.mcp.profiles`  
- Tests: profile required; integration closed by default; delegated audits both ids  

---

## Relationship to agent-native (TS)

| | Agent-native | Laravel Capabilities |
|---|---|---|
| Language | TypeScript | PHP / Laravel |
| Action unit | `defineAction` | Capability class / fluent define |
| Agent runtime | Built-in | **`laravel/ai`** |
| MCP | Built-in | **`laravel/mcp`** |
| UI hooks | React | App-owned (Livewire/Inertia/iOS/…) |
| Host | Nitro | Laravel |

Same architectural invariant (**one definition, many surfaces, shared governance**). Different industrial base.

---

## License

MIT (planned).

---

## Summary

**Laravel Capabilities** makes agent-era apps safe to grow:

- Humans (HTTP/UI), in-app agents (`laravel/ai`), remote MCP clients, the **downloadable CLI**, and jobs share **one** operation definition via **core**.
- Users install a CLI on their machine, authenticate to *their* app, and let local agents operate real capabilities — without shipping domain code to the laptop.
- Optional **messaging** (`laravel-capabilities-messaging`) adds Telegram (etc.) as conversation into the same agent and tools — never a second write path, never weight on pure bus installs.
- Agents and **MCP** use **tool profiles** (D-008), not a dump of every capability into one model — MCP is not “full UI powers.” Meta-tools inherit that profile (P2-007); progressive disclosure is not least-privilege escape.
- MCP principals use **auth profiles** (D-023): user PAT, integration credentials → SystemActor/bot, user-delegated OAuth (audit user + `client_id`).
- Names deprecate with aliases (D-012); errors share one envelope (D-018); CLI is **Go** (D-016); output schemas validate by default (D-014).
- Authorization, approval, audit, scope, and idempotency are **properties of the capability bus**, not copy-pasted per surface.
- Approvals recover from crash mid-accept (D-006 / P2-004): atomic execute under lock **or** `ResumeApprovedApprovals` + `approvals_stuck_approved_total`.
- **SystemActor tenant** is a first-class job/context field (D-003 / P2-005) — never `input['_tenant_id']` or other wire magic keys.
- **Caller is server-derived** (D-022) from credentials and adapters — never a free-form header that can skip approval rules.
- Official Laravel packages stay protocol experts; **core** owns the bus; **CLI** owns the laptop client; **messaging** owns chat adapters.

Define once. Invoke from every surface under the same law — least privilege for models, full law on the server.
