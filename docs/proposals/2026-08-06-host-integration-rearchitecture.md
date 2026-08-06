# Proposal: Host-integration re-architecture

**Status:** **build-ready** (revision 4)  
**Date:** 2026-08-06 (rev 4: 2026-08-07 — AI-chat mode detection + implementer polish)  
**Audience:** package maintainers + first production hosts (MesoPrep and next apps)  
**Input:** MesoPrep architecture audit, package code, four review passes  

**Primary requirement:** `laravel-capabilities` / `laravel-capabilities-ai` (and siblings) keep the **simplest, cleanest architecture possible**. The proposal defines that package shape — not a thick host extension framework.

**Spec follow-up:** **D-024 host integration contract** recorded in `docs/spec.md` (build started 2026-08-07 / UR-062).

---

## 1. Problem statement

The packages already solve the **domain** problem well: one capability definition, many surfaces (HTTP, CLI, MCP, bus, jobs).

What went wrong on the first production host (MesoPrep) was mostly **host glue**, not a wrong bus:

| Host pattern | Cost |
|--------------|------|
| Rebinding package services for missing knobs (queue on dispatch) | Minor upgrades break prod |
| Route surgery / hijacking package HTTP for product UX | Brittle vs package renames |
| Dual chat stacks without a kill date | Reapers, undo, docs drift |
| Soft-fail MCP + frozen tool lists + AlwaysReady default | Silent wrong prod behaviour |

### 1.1 Already true (do not overstate)

| Overstated claim | Code today |
|------------------|------------|
| Host must rebind for `claim_ttl` | SP already uses `claimTtlFromConfig` → `makeConversationService` (**default 120**) |
| Host must re-copy full Conversation/Turn/Proposal wiring | SP already owns those + Progress/LLM drivers |

**Real package gaps** (only these earn new package surface):

| Gap | Why package must own it |
|-----|-------------------------|
| Default dispatch ignores queue name/connection | Happy-path knob; without it hosts rebind `ConversationService` |
| No package stale-turn reaper | Host reapers hit wrong tables; package owns `capabilities_ai_*` turns |
| MCP register soft-fail / empty tools | Product surface; fail policy belongs in package |
| AlwaysReady as default readiness | Package ships unsafe default for accept path |
| No integration diagnostic | Hosts cannot see “product-ready” vs “Composer-installed” |

**Not package gaps** (Laravel/host already solves):

| Host need | Prefer |
|-----------|--------|
| TTS / progress side-effects | `$app->extend(ProgressStore::class, …)` |
| Product/iOS HTTP UX | Host routes → bus / AI services |
| Exotic dispatch | Host binds own dispatch when needed; no package abstract until proven |
| Decorator lists, HTTP controller maps | Do not ship unless a second host proves host routes/`extend` insufficient |

**Goal:** packages small and hard to misuse — a few config knobs on existing SP wiring, fail-closed defaults, one health command. Prefer zero new extension mechanisms when container composition or host routes suffice.

---

## 2. Design principles (package architecture)

0. **Prefer zero new package APIs** when Laravel host composition already solves it (`extend`, host routes, config on existing SP). Every new config key, command, and dual-path must earn its place against real rebind pain.

1. **Host owns domain; package owns runtime.** Capabilities, authorizer, product schemas, context/tool catalog text = host. Bus, surfaces, turn runner, progress drivers, claims, proposals (when enabled) = package.

2. **Configure defaults — do not re-construct package services.** Hosts must not `new ConversationService(...)` for happy path. Side-effects use `extend`, not full rebinds of redis/array wiring.

3. **One chat system of record when AI chat is on.** Package reaper + host residual kill-list template.

4. **Surfaces optional; product-enabled surfaces fail closed.**  
   - `on_incompatible` — peer missing / version matrix (D-011)  
   - `on_register_error` — unexpected mid-register  
   Empty MCP plan stays soft-fail (ORI-801). Non-empty plan never silent-empty tools.

5. **D-008 profiles only.** Never full-catalog dump. One profile shape: `name => list<string>` capability names.

6. **0.x honesty.** Pre-Packagist — small breaks + changelog beat multi-phase hospitality for AlwaysReady and unsafe drivers.

7. **Fewer knobs.** One `proposals.enabled`. AI-chat for health = `routes.enabled` **or** non-empty `queue.name` (ops signal that turns are queued) — not package-routes-only, not a third `product_chat` bool unless A proves too heuristic later.

---

## 3. Target package architecture (simplest end-state)

### 3.1 Package map (unchanged product split)

```
rawphp/laravel-capabilities          Core bus, registry, HTTP, MCP/agent, CLI catalog
rawphp/laravel-capabilities-ai       Turn / conversation / proposal runtime
rawphp/laravel-capabilities-messaging Optional channel sibling
capabilities-cli                     Go HTTP client against host
```

No fifth host-skeleton package. Docs + tutorial only.

### 3.2 Who does what

```
Host
  • define capabilities + Authorizer
  • AI-chat: bind Context + ToolCatalog; set queue / progress / llm config
  • side-effects: app()->extend(ProgressStore::class, …) in boot (after package bind)
  • product HTTP: host routes → CapabilityBus / AI services (package AI routes optional)
  • MCP: named profile allowlists (capability names)

Package core
  • bus, surfaces, ProfileSelector as today
  • MCP: validate allowlist names + surface at register; on_register_error
  • capabilities:integration-health (Artisan ≠ HTTP …/health)

Package AI
  • SP owns Conversation / Turn / Proposal wiring
  • queue.name + queue.connection on default dispatch (RunTurnJob onQueue/onConnection)
  • reaper command + threshold config (host schedules the command)
  • proposals.enabled (single flag); accept routes + TurnRunner fence path gated
  • live IdempotencyReadiness default; AlwaysReady testing-only
  • production guards on array/fake (Phase 3 / early 0.x)
```

### 3.3 Explicitly out of package v1 of this work

| Drop | Why |
|------|-----|
| `progress.decorators` config | Host `extend(ProgressStore::class)` |
| `surfaces.http.controllers` action map | Product UX = host routes |
| `dispatch_binding` config | Queue on default dispatch is enough |
| Dual proposal flags | One `proposals.enabled` |
| Multi-shape MCP / nested selector body | `name => list<string>` only |
| `integration.mode` / `product_chat` bool | Mode detection §3.5 (option A) |
| Package reaper auto-schedule | Host `Schedule::command` |
| AlwaysReady as prod default | Live default Phase 1 |

### 3.4 Forbidden host patterns

| Forbidden | Prefer |
|-----------|--------|
| Full rebind of `ConversationService` for queue | Config `queue.name` / `connection` on package default dispatch |
| `singleton(ProgressStore::class, …)` replacing package store | `extend(ProgressStore::class, …)` wrapping inner |
| `Route::setAction` on package routes | Host product routes → services/bus |
| AlwaysReady in production | Package live default (tests only for AlwaysReady) |
| Dual turn tables without kill date | Package reaper + residual kill list |
| Messaging reimplements turn runner | Bus + AI contracts only |

### 3.5 Mode detection (health only) — option A

| Mode | Detection |
|------|-----------|
| **AI-chat** | `capabilities-ai.routes.enabled = true` **OR** non-empty `capabilities-ai.queue.name` |
| Bus-only | AI may be installed; routes off **and** queue name empty/unset |
| MCP product | `surfaces.mcp.enabled` and non-empty plan |

Rationale: greenfield happy path is **host product routes** with package AI routes left off. Queue name is the ops signal that turns are dispatched to workers. Do not require package routes on for health to treat the host as AI-chat.

Do not treat “AI on Composer” alone as AI-chat. No `product_chat` / `integration.mode` unless option A is proven too heuristic on a real host.

### 3.6 Messaging / CLI

- **Messaging:** optional sibling; no second conversation runtime; no AI service rebinds for channel identity.  
- **CLI:** HTTP-only + host PAT minting; package does not mint tokens.

---

## 4. Concrete package changes (minimal)

### 4.1 Core: `laravel-capabilities`

#### A. HTTP product bridge — **docs only, no package map**

Package HTTP stays **package-owned** for CLI / generic capability API.

**Greenfield product/iOS wire:** host routes → `CapabilityBus` / AI services. Package AI routes (`capabilities-ai.routes.enabled`) remain optional.

Success metric: **zero package APIs whose only consumer is MesoPrep route surgery.**

#### B. MCP profiles — one shape; Phase 2 = validation + fail policy

```php
// surfaces.mcp.profiles
'lab' => ['coach.ping', 'meals.log'],  // capability names only
```

Tools already expand against the live registry today. **Phase 2 is not a second resolver.** It is:

1. Validate each allowlist name exists in the registry and is eligible for MCP surface  
2. `on_register_error` when register blows up mid-mount  
3. Empty plan remains soft-fail (ORI-801); non-empty plan + register failure → throw (default)

No `'auto'`. No nested `groups:` profile bodies unless a later host refuses static allowlists (one additive path + tests then).

**Docs:** groups/tags on definitions matter only if a future selector path is added; allowlists are capability names.

**Agent profile parity:** later, not Phase 2.

#### C. Fail policy (under `surfaces.mcp`)

```php
'on_incompatible' => 'fail',   // D-011 peer / version
'on_register_error' => 'throw', // unexpected mid-mount; empty plan still soft-fail
```

#### D. `php artisan capabilities:integration-health`

Distinct from HTTP `GET …/capabilities/health`. Do not merge.

**AI-chat** = §3.5 option A (`routes.enabled` OR non-empty `queue.name`).

**Fail** (product cannot run):

| Check | When |
|-------|------|
| Authorizer bound | When any **enabled** surface that performs authorized invokes is on (HTTP/MCP/agent/job/cli as registered). If none of those surfaces are enabled, skip. One rule: no “soft maybe.” |
| Context + ToolCatalog bound | AI-chat |
| `claim_ttl` > 0 | AI-chat |
| Idempotency is AlwaysReady | **`proposals.enabled` only** → **fail**; proposals off → **skip** |
| MCP peer up + tools > 0 | non-empty MCP plan |

**Warn** (Phase 1) → **fail** (Phase 3 prod guards):

| Check | When |
|-------|------|
| Progress driver is `array` | AI-chat |
| Queue name empty | AI-chat via **routes only** (routes on, name empty) — if AI-chat was entered solely via queue.name being set, name is non-empty by definition |

Custom host dispatch: health only checks `queue.name` as **ops label** (which worker to run); it does not prove a custom callable applies it.

### 4.2 AI: `laravel-capabilities-ai`

#### A. Queue on default dispatch

```php
// local $env helper — not Illuminate env() at file load
'claim_ttl' => (int) $env('CAPABILITIES_AI_CLAIM_TTL', Package::DEFAULT_CLAIM_TTL), // 120
'queue' => [
    'connection' => $env('CAPABILITIES_AI_QUEUE_CONNECTION'),
    'name' => $env('CAPABILITIES_AI_QUEUE_NAME'),
],
```

Default dispatch **must** set Laravel `onQueue` / `onConnection` on `RunTurnJob` **before** dispatch when config is non-empty. **Unit-test that path.**

Custom host dispatch (rare): host replaces the dispatch callable themselves — no `dispatch_binding` config key.

#### B. Progress side-effects — host `extend`

Package binds `ProgressStore` in `register()` when unbound. Host **must** `extend` in `boot()` (or after the package bind) so the wrapper receives the package store as `$inner`.

```php
// Host AppServiceProvider::boot
$this->app->extend(ProgressStore::class, function (ProgressStore $inner, $app) {
    return new TtsDispatchingProgressStore($inner, $app->make(TtsService::class));
});
```

**Forbidden:** host `singleton(ProgressStore::class, …)` that replaces redis/array wiring. That is a rebind, not an extend.

Do **not** ship `progress.decorators`.

#### C. Idempotency — live default in Phase 1 (one algorithm)

**Default (v1 — single path, no multi-backend menu):**

1. If core `IdempotencyStore` (or equivalent) is bound → readiness pings that store.  
2. Else → `isReady() = false` (fail-closed).  

Never AlwaysReady outside tests. `AlwaysReadyIdempotency` remains for unit tests only.

#### D. Proposals — single flag; full gating

```php
'proposals' => [
    'enabled' => (bool) $env('CAPABILITIES_AI_PROPOSALS_ENABLED', true), // Phase 1 BC; greenfield docs: false
],
```

When `enabled=false`:

| Layer | Behaviour |
|-------|-----------|
| Routes | Do **not** register accept/reject (condition in bootRoutes / route include — today they always register when `routes.enabled`) |
| TurnRunner | **Skip fence → proposal extract path** (not only HTTP) |
| History | Omit or empty proposals collection |

When `enabled=true`: current fence + accept behaviour.  
Breaking phase: default `false`.

#### E. Reaper

```php
'reaper' => [
    'stale_queued_minutes' => (int) $env('CAPABILITIES_AI_REAPER_STALE_QUEUED', 30),
    'stale_running_grace_seconds' => (int) $env('CAPABILITIES_AI_REAPER_RUNNING_GRACE', 60),
],
```

`php artisan capabilities-ai:reap-stale-turns`  
- queued: age(`created_at`) > stale_queued_minutes  
- running: age(`claimed_at`) > max(claim_ttl, grace)  

Host schedules the command. No package auto-schedule config.

#### F. Production guards (Phase 3; may merge early)

Outside testing: `array` progress / `fake` llm **throw** unless `CAPABILITIES_AI_ALLOW_UNSAFE=1` (local demos only — keep out of happy-path greenfield docs).

### 4.3 Docs only

- Greenfield checklist (§6)  
- Forbidden rebinds vs `extend`  
- Host product routes; package AI routes optional  
- Cutover residual kill-list template  
- Optional Pest trait: assert resolved readiness class, not a config key  

### 4.4 Testing (unit-only monorepo)

- Default dispatch sets `onQueue` / `onConnection` on `RunTurnJob`  
- Reaper thresholds / status filters  
- integration-health: AI-chat via routes **or** queue.name; AlwaysReady fail only when proposals on  
- Proposals off: no accept routes; TurnRunner skips fences; history empty proposals  
- Live readiness: store bound → ready path; unbound → not ready  
- MCP: allowlist validation + `on_register_error`; empty plan soft-fail  

---

## 5. Integration modes

| Mode | Host provides | Forbidden |
|------|---------------|-----------|
| Bus-only | Capabilities + authorizer | Treating AI install alone as AI-chat |
| AI-chat | Context, ToolCatalog, queue worker; redis progress in prod | Legacy host turn runner; package route hijack for product UX |
| MCP lab | Named allowlist profiles; host wires peer | Full catalog; silent empty tools after register error |
| Product CLI | HTTP + host PAT | Non-bus CLI paths |

---

## 6. Greenfield checklist (aligned with health)

```bash
composer require rawphp/laravel-capabilities rawphp/laravel-capabilities-ai
php artisan vendor:publish --tag=capabilities-config --tag=capabilities-ai-config
php artisan vendor:publish --tag=capabilities-ai-migrations
php artisan migrate
```

**AI-chat host** (package AI routes optional):

1. Bind `Authorizer`  
2. Bind `ConversationContextProvider` + `ToolCatalog`  
3. Register domain capabilities  
4. Set `CAPABILITIES_AI_QUEUE_NAME=…` + `queue:work --queue=…` (also makes health AI-chat without package routes)  
5. Optional: `capabilities-ai.routes.enabled=true` only if you want package chat HTTP  
6. Prod: `CAPABILITIES_AI_PROGRESS_DRIVER=redis` — health **warns** on array until Phase 3 **fails**  
7. Greenfield: `CAPABILITIES_AI_PROPOSALS_ENABLED=false` (if left on, live readiness default must not be AlwaysReady — health **fails** on AlwaysReady)  
8. Side-effects: `extend(ProgressStore::class, …)` in **boot**  
9. Product UX: **host routes** → bus / AI services  
10. `php artisan capabilities:integration-health` → **fail** set clean; **warns** OK only for array progress / empty queue-when-routes-only until Phase 3  

---

## 7. Phased delivery

### Phase 0 — Document

This proposal + D-024 recorded in docs/spec.md (build started).

### Phase 1 — Minimal package ship

1. Queue name/connection on default dispatch (`RunTurnJob` onQueue/onConnection + unit test)  
2. Reaper + config thresholds  
3. Live readiness default (store ping or fail-closed); AlwaysReady tests only  
4. `proposals.enabled` + accept routes **and** TurnRunner fence gating + history empty  
5. `capabilities:integration-health` with §3.5 / §4.1 D  
6. Docs: `extend` order, host routes, checklist, kill list, claim_ttl **120**  

### Phase 2 — MCP ops

- Allowlist name/surface validation at register  
- `on_register_error`  
- Not a second profile language  

### Phase 3 — Prod guards (merge early while 0.x if cheap)

- array/fake throw outside testing (`CAPABILITIES_AI_ALLOW_UNSAFE` escape)  
- Health **fail** on array progress / empty queue when AI-chat  
- Proposals default off for greenfield  

### Phase 4 — MesoPrep host cleanup

Existing URs: drop rebinds, route surgery, host `chat_*`. Reference = this checklist.

---

## 8. Deliberately not doing

- Mega-package; product domain in package  
- Decorator config DSL; HTTP action override map  
- `dispatch_binding`; `product_chat` / `integration.mode` (unless A fails)  
- Full-catalog MCP; nested profile DSL  
- Fifth package; package reaper auto-schedule  
- AlwaysReady as prod default  

---

## 9. Success metrics

| Metric | Target |
|--------|--------|
| New package APIs | Only §4 gaps (queue, reaper, readiness, proposals flag, health, MCP fail policy) |
| APIs only for MesoPrep route surgery | **0** |
| Host happy-path SP | binds + config + optional `extend` |
| Greenfield AI-chat | checklist → fail-clean health in &lt; 1 day (host routes + queue.name OK) |
| Dual chat after cutover | 0 |
| Monorepo tests | Unit-only |

---

## 10. Locked decisions

| # | Answer |
|---|--------|
| AI-chat health detection | **Option A:** `routes.enabled` OR non-empty `queue.name` |
| Proposals default off next break | Yes; Phase 1 may keep true + docs OFF for greenfield |
| Skeleton package | No |
| MCP `on_register_error` | `throw` when plan non-empty |
| Queue name | Explicit for workers; health warn then Phase 3 fail when routes on without name |
| HTTP controller map | **Out** — host routes |
| Progress decorators | **Out** — host `extend` |
| Live idempotency | **Phase 1**; store ping else fail-closed |
| AlwaysReady when proposals on | **Fail** health (skip when proposals off) |

---

## 11. Summary

Package simplicity is the requirement.

**Ship:** queue on default dispatch, reaper, live readiness, single proposals flag (routes + TurnRunner + history), MCP allowlist validation + loud register errors, integration-health with AI-chat = routes **or** queue name.

**Host:** Progress `extend`, product HTTP routes, reaper schedule, domain + authorizer + context/tools.

---

## Appendix A — Audit → package fix

| Theme | Fix |
|-------|-----|
| Constructor rebinds L-004 | Queue on dispatch |
| Route rebind L-005 | Host routes (docs) |
| Stale turns L-003 | Reaper |
| MCP soft fail L-010 | `on_register_error` + health |
| MCP freeze L-013 | Allowlist validation (not second resolver) |
| AlwaysReady L-011 | Live default Phase 1; health fail if proposals on |
| Dual chat L-009 | Reaper + host kill list |
| Progress TTS | Host `extend` |

## Appendix B — MesoPrep URs

UR-032–045 clean **host** residuals. This proposal is the **package** half.

## Appendix C — Review disposition

| Pass | Outcome |
|------|---------|
| 1 Direction | Approved |
| 2 Contract | Precision then simplified |
| 3 Simplicity | Cut decorator/HTTP-map DSLs; host extend + host routes |
| 4 Build-ready | AI-chat = routes **or** queue.name; AlwaysReady fail when proposals on; Phase 2 = validation not second resolver; authorizer rule; implementer one-liners (extend order, job queue flags, TurnRunner fence, allow-unsafe, readiness algorithm) |
