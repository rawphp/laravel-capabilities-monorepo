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
- **New objections:** none that change merge decision.
  - Note (not reopened): Anthropic multi-round tools remain **product-unavailable** (B/C shrink) until true `tool_result` (A). That is intentional scope shrink, not a half-wired path.
  - Note: proposal accept concurrent races still depend on core idempotency store when two workers both see `accepting` — residual under D-005, acceptable for MVS.
- **Same issues without new evidence:** none.
- **High-impact open/reopened remaining:** none.

## Decision

- **Outcome:** proceed-with-accepts
- **Proposal:** Ship AI runtime tip only after high-impact tool/accept correctness; remaining packaging/god-object items are conscious residual risk
- **Resolved (verified fixes):** R1-01 (tool-round capability + no advertise tools), R1-02 (real CapabilityResult), R1-03 (accept claim + idempotency key), R1-04 (DatabaseConnection), R1-05 (cancel progress honesty), R1-06 (single driver normalize), R1-08 (ProposalFenceExtractor), R1-11 (job timeout honesty)
- **Accepted (conscious risk):** R1-07 (redis still untyped object; host ProgressStore preferred), R1-09 (branch packaging still misnamed — owner Tom)
- **Deferred:** R1-10 (CapabilityRegistry peel plan)
- **Stalemate / still open high-impact:** none
- **Evidence:** this log Round 1; `composer test:ai` → 89 passed (226 assertions); files under `packages/laravel-capabilities-ai/src/{Contracts,Domain,Support,Jobs}/`
- **Next step:**
  1. Rename branch / PR title away from `cleanup/dead-code` before merge (R1-09).
  2. When Anthropic multi-round tools are a product requirement, implement (A) structured `tool_use`/`tool_result` end-to-end and flip `supportsToolRounds()` true.
  3. Schedule CapabilityRegistry peel (R1-10) before more registry features.
  4. Optional: wire `RunTurnJob` timeout from `claim_ttl` at ConversationService dispatch.
