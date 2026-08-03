# Devil's Advocate Log

## Session: 2026-08-04 — AI package tip: tool loop / accept / maintainability
- **Proposal:** Ship/merge `cleanup/dead-code` tip as a coherent AI runtime (tool loop + proposals + DI) without further structural fixes
- **Owner:** Tom (accept decisions); agent builder this session
- **Started:** 2026-08-04T12:00:00Z

### Round 1 — Critic

| ID | Objection | Impact | Status | Notes |
|----|-----------|--------|--------|-------|
| R1-01 | TurnRunner always appends `role=tool` and re-calls `complete`; AnthropicLlmClient throws on any `role=tool`. First Anthropic tool_use batch runs `bus->invoke`, then the next LLM round hard-fails. FakeLlmClient tests stay green; production Anthropic + multi-round tools is structurally dead. Half-fixed fail-closed is worse than honest non-support. | high | verified | Evidence at open: `TurnRunner.php` tool loop; `AnthropicLlmClient.php` L41–44 |
| R1-02 | After `bus->invoke`, runner feeds `{'ok': true, 'name': ...}` and never reads `CapabilityResult`. Deny / failure / approval_required all look like success to the next model turn. Toy adapter, not a tool runtime. | high | verified | |
| R1-03 | `ProposalService::accept` invokes bus then sets accepted. Crash between lines re-accepts as pending and invokes again. Idempotent only after status flip — opposite of D-005 spirit. | high | verified | Residual: concurrent double-accept still needs bus idempotency store; claim closes crash window |
| R1-04 | Identical `connection()` try/app/DB/Manager blocks in `TurnClaim` and `TurnService`. Accidental framework coupling copied twice. | medium | verified | |
| R1-05 | Cancel: SQL status update succeeds then progress appends. Progress failure leaves DB cancelled while events lie. Looks transactional; is not. | medium | verified | Documented best-effort + throw on progress failure (no silent lie) |
| R1-06 | `ContainerBindings` double driver matching: `resolve*Driver` then `make*` re-matches. | medium | verified | Single `normalizeDriver` allowlist + describe/make paths |
| R1-07 | SP still duck-types Redis (`method_exists connection`) and config (`method_exists get`). Half-typed container, fuzzy leaf seams. | medium | accepted | Config uses `ConfigRepository`; redis host binding remains untyped object — residual |
| R1-08 | `maybeCreateProposalsFromFence` regex lives inside TurnRunner — presentation parsing bolted onto claim/LLM/tool loop. | medium | verified | Extracted `ProposalFenceExtractor` |
| R1-09 | Branch still named `cleanup/dead-code` while tip is multi-UR AI stack + format + maintainability. Review hazard; earlier packaging finding not closed in git terms. | medium | accepted | Process residual; owner Tom to rename/split before merge |
| R1-10 | `CapabilityRegistry` remains ~2.2k lines; branch reformats without peel plan. Maintainability tax on every invoke path. | medium | deferred | Out of scope for this tip; need explicit peel plan before more registry feature work |
| R1-11 | `RunTurnJob::$timeout = 120` with comment claiming claim_ttl alignment; value hard-coded, silent drift when config changes. | low | verified | Honest comment + optional constructor timeout; not live-bound |

### Round 1 — Builder

| ID | Response | Status | Evidence |
|----|----------|--------|----------|
| R1-01 | **Option B:** `LlmClient::supportsToolRounds()`. Anthropic → false; Fake → true. Runner (1) passes empty tool defs when false so models are not advertised tools, (2) still refuses bus invoke if tool_calls appear. No more mutate-then-crash. Full Anthropic `tool_result` blocks remain future work (A). | verified | `composer test:ai` green; tests: `does not pass tool definitions…`, `refuses tool invokes…`, `does not advertise multi-round tool support` |
| R1-02 | Tool messages use `CapabilityResult::toArray()` + `name` (ok/data/error/meta). Progress tool events include `ok` + `error_code`. | verified | test: `feeds real CapabilityResult into tool messages (not invented ok:true)` |
| R1-03 | Claim-style `pending → accepting` (atomic UPDATE) before bus invoke; resume from `accepting` with `idempotency_key=proposal:{ulid}`; only mark `accepted` on `$result->ok`. | verified | tests: `accept claims pending→accepting…`, `resume from accepting…`; `composer test:ai` |
| R1-04 | Single `Support\DatabaseConnection::resolve()` used by TurnClaim, TurnService, ProposalService claim. Private clones deleted. | verified | `DatabaseConnection.php`; claim/cancel paths compile in suite |
| R1-05 | Docblock: DB cancel atomic, progress best-effort after. Progress throw wraps as `cancelled in DB but progress append failed` so callers cannot assume events published. | verified | `TurnService::cancel`; existing cancel tests still pass |
| R1-06 | One `normalizeDriver` allowlist; `describe*` / `make*` use it; no dual resolveLlmDriver+match strings. | verified | ContainerBindingsTest still green |
| R1-07 | Config path: `instanceof ConfigRepository`. Redis: is_callable `connection` + comment that host should bind ProgressStore for typed seams. Residual: untyped redis manager. | accepted | Residual owner: package maintainer; prefer host ProgressStore binding |
| R1-08 | `ProposalFenceExtractor` owns fence regex/JSON; TurnRunner only orchestrates create. | verified | `ProposalFenceExtractorTest` |
| R1-09 | Not fixed in git: branch name still `cleanup/dead-code`. Accept as process risk; do not treat history as mergeable packaging without rename/split. | accepted | Residual: Tom renames branch / opens PR with accurate title before merge |
| R1-10 | No peel this session. Deferred until a dedicated registry decomposition plan. | deferred | Do not pile more feature work into CapabilityRegistry without peel plan |
| R1-11 | Dropped false "aligned with claim_ttl" claim; optional `$timeout` ctor; default 120 still mirrors default config but documented as not live-bound. | verified | `RunTurnJob.php`; RunTurnJobTest timeout=120 |

### Round 1 — Critic re-check

- **Reopened:** none on high-impact after second-pass hardening (strip tool defs when `!supportsToolRounds`).
- **New objections:** none that change merge decision at that moment.
- **High-impact open/reopened remaining:** none (later residual attack → session 2).

## Decision (session 1 — superseded by session 2)

- **Outcome:** proceed-with-accepts (later residual critic reopened accept/idempotency completeness)
- **Resolved:** R1-01 … R1-11 as above
- **Accepted:** R1-07, R1-09
- **Deferred:** R1-10

---

## Session: 2026-08-04 — Round-2 residual attack on accept / fence / dispatch packaging
- **Proposal:** Treat session-1 tip as merge-ready after residual critic findings (accept limbo, idempotency not fail-closed, nested fence, timeout wiring, packaging, host tool-round honesty)
- **Owner:** Tom (accept decisions); agent builder this session
- **Started:** 2026-08-04T14:00:00Z
- **Source findings:** paste-in residual critic after session-1 fixes
- **path:** in-session code fixes (do-work skill present; fixes applied directly with unit verification — no UR pipeline this round)

### Round 1 — Critic

| ID | Objection | Impact | Status | Notes |
|----|-----------|--------|--------|-------|
| R1-01 | Proposal accept leaves rows stuck in `accepting` with no terminal recovery. Permanent failures (authz deny, bad target, validation) leave limbo forever. | high | verified | Supercedes residual of session-1 R1-03 |
| R1-02 | Idempotent accept conditional on core store config — not fail-closed. Crash after successful bus invoke but before `accepted` re-invokes; without store = second side effect. | high | verified | Supercedes residual of session-1 R1-03 |
| R1-03 | `ProposalFenceExtractor` silently drops nested proposal JSON (`\{.*?\}`). | medium | verified | |
| R1-04 | `RunTurnJob` timeout still not wired from cheap-create path. | medium | verified | Closes session-1 R1-11 footgun |
| R1-05 | Branch packaging still wrong (`cleanup/dead-code`). | medium | accepted | Process; owner Tom |
| R1-06 | Hosts that implement `LlmClient` and lie about `supportsToolRounds` reopen bus-then-crash. Docs gap. | low | verified | README note |
| R1-07 | Cancel residual (outbox later). | low | accepted | Do not re-block |

**Do not re-block (session-1 improvements held):** tool loop gated; Anthropic no tool defs; real CapabilityResult tool messages; pending→accepting→accepted; DatabaseConnection; fence extracted; cancel honesty.

### Round 1 — Builder

| ID | Response | Status | Evidence |
|----|----------|--------|----------|
| R1-01 | Non-retryable bus errors → `status=failed` + `last_error`; missing target same. Retryable stays `accepting` for resume. Re-accept of `failed` throws with last_error. | verified | tests: `non-retryable bus failure marks failed…`, `retryable bus failure leaves accepting…`, `missing target_capability marks failed`; `composer test:ai` 97 passed |
| R1-02 | (1) Phase-record `accept_outcome` after successful invoke while still accepting, then mark accepted — crash resume is local without re-invoke. (2) Fail closed when `idempotencyStoreReady=false` (SP derives from `capabilities.idempotency` via `ContainerBindings::isIdempotencyStoreReady`). Still passes `idempotency_key=proposal:{ulid}` for concurrent workers. | verified | tests: `resume from accepting with recorded outcome does not re-invoke bus`, `fail closed when idempotency store is not ready`, `isIdempotencyStoreReady fails closed…`; path: in-session |
| R1-03 | Brace-balanced scan of fence body (string-aware); nested objects decode. | verified | test: `extracts nested JSON objects inside proposal fence` |
| R1-04 | `ConversationService` takes `jobTimeoutSeconds`; cheap create dispatches `new RunTurnJob($ulid, $timeout)`. SP passes `capabilities-ai.claim_ttl`. | verified | tests: `passes custom jobTimeoutSeconds…`, default job timeout 120 on cheap create |
| R1-05 | Not a code fix. Residual: rename/split before merge theater. | accepted | Residual owner: Tom |
| R1-06 | README: opt-in `supportsToolRounds()===true` only if tool results accepted on next `complete()`. | verified | `packages/laravel-capabilities-ai/README.md` Host seams section |
| R1-07 | Accept residual: DB flip + best-effort progress + explicit throw is honest enough for MVS. | accepted | Residual: outbox/shared TX later; owner package maintainer |

### Round 1 — Critic re-check

- **Reopened:** none. High-impact accept limbo and double-fire window closed with unit evidence.
- **New objections:** none material.
  - Note: concurrent two-worker race on `accepting` still relies on core idempotency store — now **fail-closed** if store missing, rather than silent double-fire.
  - Note: fence still returns null on invalid JSON (no structured log hook in unit package) — silent drop only when decode fails; nested valid JSON no longer false-invalid.
- **Same issues without new evidence:** none.
- **High-impact open/reopened remaining:** none.

## Decision

- **Outcome:** proceed-with-accepts
- **Proposal:** Merge AI runtime tip only after accept terminal recovery + fail-closed idempotency + nested fence + claim_ttl wiring; packaging rename remains process residual
- **Resolved (verified fixes):** R1-01 (failed terminal), R1-02 (accept_outcome + fail-closed store), R1-03 (brace-balanced fence), R1-04 (claim_ttl → job timeout), R1-06 (README host honesty)
- **Accepted (conscious risk):** R1-05 (branch still `cleanup/dead-code` — owner Tom), R1-07 (cancel non-atomicity residual), session-1 R1-07 redis untyped, session-1 R1-10 registry peel deferred
- **Stalemate / still open high-impact:** none
- **Evidence:** this log session 2 Round 1; `composer test:ai` → **97 passed (256 assertions)**; key files: `ProposalService.php`, `Proposal.php`, proposals migration, `ProposalFenceExtractor.php`, `ConversationService.php`, `ContainerBindings.php`, `CapabilitiesAiServiceProvider.php`, `README.md`
- **Next step:**
  1. **Tom:** rename branch / PR title away from `cleanup/dead-code` (R1-05) before merge.
  2. Commit this residual fix set on the tip when ready.
  3. CapabilityRegistry peel (session-1 R1-10) remains deferred.
  4. Anthropic true tool_result (session-1 option A) when product requires multi-round tools.

---

## Session: 2026-08-04 — Round-3 residual attack on accept_outcome / reject SM / fail-closed defaults
- **Proposal:** Treat session-2 tip (accept_outcome + config-sniff readiness + free reject) as merge-ready after paste-in residual findings
- **Owner:** Tom (accept decisions); agent builder this session
- **Started:** 2026-08-04T15:00:00Z
- **Source findings:** user paste residual critic (10 items) after session-2 Decision
- **path:** in-session code fixes (do-work skill present; tracker.backend=linear; residual redteam round closed with unit verification — same pattern as session 2)

### Round 1 — Critic

| ID | Objection | Impact | Status | Notes |
|----|-----------|--------|--------|-------|
| R1-01 | `accept_outcome` is incidental complexity once store is mandatory: three recovery mechanisms (claim, accept_outcome phase-1, core idempotency). Local resume vs fail-closed store cannot both be load-bearing. Delete accept_outcome; resume = re-invoke + terminal write. | high | verified | Supercedes session-2 R1-02 design |
| R1-02 | Accept is a SM; reject is free write — can overwrite accepting mid-invoke or flip terminal rows. Need atomic pending→rejected only. | high | verified | |
| R1-03 | Fail-closed defaults open: ProposalService / makeProposalService default `$idempotencyStoreReady=true`; SP starts true when config unbound. Safe default is false. | high | verified | |
| R1-04 | Idempotency readiness is AI-local config sniff, not container truth. Prefer `bound(IdempotencyStore::class)`. | high | verified | |
| R1-05 | accept() status handling copy-pasted before claim and after lost race. Collapse to single match path. | medium | verified | |
| R1-06 | Non-atomic terminal accept (outcome then accepted) leaves multi-write story. Subsumed by R1-01 if accept_outcome deleted; else one terminal UPDATE. | high | verified | Subsumed by R1-01 (no intermediate durable outcome) |
| R1-07 | `supportsToolRounds()` hard BC; interface default-false would fail closed. (Critic claimed PHP 8.2 interface bodies — **false** on PHP 8.x.) | medium | accepted | Trait `LlmClientDefaults` instead; residual host must implement or use trait |
| R1-08 | SP config two dialects: configFromApp vs method_exists get for claim_ttl/idempotency. | medium | verified | claim_ttl via configFromApp; idem via bound() |
| R1-09 | Tool-loop dual-gated hides thinner product: MVS = tools-off until tool_result. Docs must own that as package default. | low | verified | README MVS product default |
| R1-10 | Branch still `cleanup/dead-code` while tip is accept SM + tools + DI. Process residual. | medium | accepted | Owner Tom; supersedes session-2 R1-05 residual |

### Round 1 — Builder

| ID | Response | Status | Evidence |
|----|----------|--------|----------|
| R1-01 | Removed `accept_outcome` (migration, model cast, hasSuccessfulOutcome, two-phase save). Resume always re-invokes with `proposal:{ulid}`; single terminal `accepting→accepted` UPDATE. Docblock: store is the only D-005 system. | verified | `ProposalService.php`; tests no longer assert accept_outcome; migration column gone; path: in-session |
| R1-02 | Reject: load → rejected idempotent → refuse non-pending → atomic UPDATE pending→rejected; race re-check. Tests: refuse accepting/accepted/failed; idempotent rejected. | verified | `reject refuses accepting/accepted/failed`; `reject is idempotent…` |
| R1-03 | Defaults `idempotencyStoreReady=false` on ctor + `makeProposalService`. Happy-path unit tests use `readyProposalService()` (prove true). New test: bare `new ProposalService($bus)` fails closed. | verified | `defaults fail closed when idempotency readiness is unproven`; `makeProposalService defaults fail closed…` |
| R1-04 | Deleted `isIdempotencyStoreReady` config sniffer. SP: `$app->bound(IdempotencyStore::class)`. | verified | SP tests: not bound → false; bound InMemory → true |
| R1-05 | Single `match ($status)` in accept; lost claim re-enters `accept()` (no duplicated policy arm). | verified | code structure + suite |
| R1-06 | No intermediate outcome write; `markAccepted` is one UPDATE WHERE status=accepting. | verified | subsumes with R1-01 |
| R1-07 | PHP interfaces **cannot** have method bodies (verified PHP 8.5.8). Shipped `LlmClientDefaults` trait (`return false`); Anthropic uses it. Residual: host that implements interface without trait still fatals until they add the method — acceptable pre-stable; not silent tools-on. | accepted | Residual owner: package maintainer; hosts use trait |
| R1-08 | ConversationService claim_ttl from `configFromApp`; ProposalService readiness not config. Redis residual still accepted (session-1). | verified | `CapabilitiesAiServiceProvider.php` |
| R1-09 | README: MVS product default = multi-round tools off; dual-gate is defense-in-depth. | verified | README Host seams |
| R1-10 | Not a code fix. | accepted | Residual owner: Tom rename/split before merge |

### Round 1 — Critic re-check

- **Reopened:** none on high-impact. R1-01/02/03/04/06 have unit evidence and remove the contradictory dual-safety story.
- **New objections:** none material.
  - Note: R1-07 critic premise (interface default methods) is factually wrong on PHP; trait is the closest fail-closed default without lying about language support.
  - Note: concurrent accept still relies on core store + claim; now only one recovery path.
  - Note: reject of `expired` also refuses (same non-pending law) — correct.
- **Same issues without new evidence:** none.
- **High-impact open/reopened remaining:** none.

## Decision

- **Outcome:** proceed-with-accepts
- **Proposal:** Ship AI tip only after accept_outcome deletion + reject SM + fail-closed defaults + container-bound readiness; process packaging + LlmClient interface-body residual remain accepts
- **Resolved (verified fixes):** R1-01, R1-02, R1-03, R1-04, R1-05, R1-06, R1-08, R1-09
- **Accepted (conscious risk):** R1-07 (trait not interface body — language limit; residual host implementor), R1-10 (branch name), session-1 redis untyped, session-1 registry peel deferred
- **Stalemate / still open high-impact:** none
- **Evidence:** this log session 3; `pest` AI package → **100 passed (267 assertions)**; key files: `ProposalService.php`, `Proposal.php`, proposals migration, `CapabilitiesAiServiceProvider.php`, `ContainerBindings.php`, `LlmClientDefaults.php`, `AnthropicLlmClient.php`, `README.md`, `CHANGELOG.md`, `ProposalServiceTest.php`, `ServiceProviderBindingsTest.php`
- **Next step:**
  1. **Tom:** rename branch / PR title away from `cleanup/dead-code` (R1-10).
  2. Commit session-3 tip when ready.
  3. Hosts: `use LlmClientDefaults` or implement `supportsToolRounds()`.
  4. Anthropic true tool_result remains future product work.
