# Architecture Audit — laravel-capabilities-monorepo

**Generated:** 2026-09-29T12:22:44Z
**Stack:** Laravel package monorepo (illuminate 11–13, PHP 8.2+; core bus + messaging + AI packages) + Go CLI (HTTP client only); Pest unit tests, PHPStan installed; GitHub Actions tests + package split; no frontend
**Mode:** audit

## Summary

- **58** total findings
- **0** critical, **15** high, **32** medium, **11** low
- Estimated total effort: 17 trivial, 33 small, 8 medium, 0 large
- **31** quick wins
- Blocking chains flagged by auditors: **L-001 + L-002** (no capability can request approval from any real surface, so L-005/L-012/L-013/L-014 stay latent until these land); **M-001 + M-002 + M-003** (Telegram cannot work end to end on real infrastructure; M-006 explains why unit tests still pass).

## Critical (fix now)

_None._

## High Priority (this sprint)

### L-003 — Sanctum CLI tokens are always derived as caller=http, never cli
- **Severity:** high · **Effort:** small · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities/src/Http/IlluminateHttpBridge.php:310`, `packages/laravel-capabilities/src/Http/CallerDeriver.php:49`, `packages/laravel-capabilities/src/Http/CallerDeriver.php:65`, `packages/laravel-capabilities/tests/Unit/Http/IlluminateHttpBridgeTest.php:50`
- **Current:** fromIlluminate() adds adapter='http' to every authenticated credential, including ones that carry token_abilities. CallerDeriver checks adapter before token_abilities, so the configured clients.token_abilities map ('capabilities:cli' => 'cli') never applies on the real HTTP path. The Go CLI deliberately does not send X-Capabilities-Caller (packages/capabilities-cli/internal/api/client.go:84), so CLI requests always run as caller=http.
- **Recommended:** Derive the caller from token abilities first; fall back to adapter=http only when no ability maps. Either stop the bridge injecting adapter when token_abilities is non-empty, or make CallerDeriver treat adapter 'http' as the lowest-precedence fact.
- **Why it matters:** D-022: the caller is derived from the credential class. Because of this bug, surfaces.cli.enabled=false and per-capability surfaces without 'cli' do not block the CLI (it passes the http surface gate at CapabilityRegistry.php:894). A cli-only capability is unreachable from the CLI. Approval, rate-limit and audit logic keyed on the caller also sees the wrong surface. The unit test pins the buggy intermediate state and never composes bridge + deriver.
- **How to fix:** In IlluminateHttpBridge::buildCredential, set adapter='http' only when `$tokenAbilities === [] && $oauthClientId === null`. Add a unit test that runs IlluminateHttpBridge::fromArray(['authenticated'=>true,'user'=>$u,'token_abilities'=>['capabilities:cli']]) through CapabilityController::resolveCaller() and expects caller 'cli'. Add one more where the CLI token hits a capability with surfaces ['http'] and expects forbidden.

### L-004 — Invokes without an actor silently run as fabricated user id 1
- **Severity:** high · **Effort:** small · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Pipeline/ResolveActor.php:40`, `packages/laravel-capabilities/src/Pipeline/ResolveActor.php:45`, `packages/laravel-capabilities/docs/user-guide.md:107`
- **Current:** If the 'actor' key is absent and caller != job, ResolveActor returns a stdClass with id=1. CapabilityContext::user() returns it as the user, and it drives authorize(), rate-limit keys, idempotency identity and audit. The user guide's basic invoke example omits the actor, and the Capability facade invoke passes whatever options the host gives it.
- **Recommended:** Fail closed: throw 'Actor principal is required' for every caller when no actor/context is given, as already done for job and explicit null. Keep a defaultUser only inside test fixtures (e.g. PipelineHelpers::options()).
- **Why it matters:** D-002 forbids implicit principals. Host code that calls the bus from its own controller or listener without an actor gets an identity it never authenticated, and it is usually the id of the first (admin) user. Any authorize() that checks `$ctx->user() !== null`, or compares ids, passes for that principal. Where authorize calls `$user->can()`, the result is an opaque internal error.
- **How to fix:** Replace lines 36-40 with `throw new RuntimeException('Actor principal is required (D-002).');`. Move defaultUser() into tests/Fixtures and update the helper options that relied on the implicit default. Fix the user-guide example to pass 'actor' => $request->user(). Unit test: invoke without actor for callers http, cli, mcp and agent returns unauthenticated, and run() is not called.

### L-005 — Approved execution runs as a stdClass stub, not the requester
- **Severity:** high · **Effort:** small · **Impact:** reliability, security
- **Locations:** `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:596`, `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:146`, `packages/laravel-capabilities/src/Approval/OriginalActorAuthorizer.php:42`
- **Current:** On accept or resume, OriginalActorAuthorizer loads the real requester through the auth user provider to re-check authorize. executeApproval() then rebuilds a bare stdClass (id, tenant_id) and invokes the pipeline with it. authorize() and run() therefore see a non-User actor: `$actor->can(...)` (the user-guide example) throws, and domain code that reads user attributes breaks.
- **Recommended:** Resolve the requester once through the same resolver OriginalActorAuthorizer uses, and pass that object as the actor to executeApproval. If the requester cannot be resolved, fail closed with a clear code.
- **Why it matters:** D-006 requires the stored invoke to run as the original requester. A stub that looks like a user but is not one turns every approved execution of a normal policy-based capability into an 'internal' failure after the approver has already decided.
- **How to fix:** Inject a `Closure(string $type, string $id): ?object` user resolver into CapabilityRegistry (or into the ApprovalManager executor closure in the provider), and use it in executeApproval instead of `new stdClass`. Unit test: a fake resolver returns an object with can(); the approved row executes and authorize() receives that same instance.

### M-009 — Stop tool invokes when a running turn is cancelled
- **Severity:** high · **Effort:** small · **Impact:** reliability, security
- **Locations:** `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php:71`, `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php:160`, `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php:188`, `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php:197`, `packages/laravel-capabilities-ai/src/Domain/TurnService.php:75`, `packages/laravel-capabilities-ai/docs/user-guide.md:194`
- **Current:** Cancel flips the row to cancelled and emits a terminal event, but TurnRunner only re-reads status after the loop. A cancelled turn keeps calling the LLM and invoking mutating capabilities for up to max_tool_rounds (8), appending tool events after the terminal one. The final completed write is a plain save, so a cancel landing between the check and the save is overwritten.
- **Recommended:** The runner re-checks status at the start of each round and before each bus invoke and stops when cancelled; the completed/failed writes are CAS updates (`WHERE status = running`).
- **Why it matters:** Users and hosts treat cancel as 'stop acting on my behalf'. Continuing capability mutations after cancel breaks that and makes the progress stream lie (terminal then more tool events).
- **How to fix:** Add `private function cancelled(string $ulid): bool { return Turn::query()->where('ulid',$ulid)->value('status') === Turn::STATUS_CANCELLED; }`; call at loop top and before `$this->bus->invoke`, returning the fresh turn. Replace the completed `$turn->save()` with a CAS update on status=running. Unit test: FakeLlmClient returns tool_calls twice; cancel after round 1 -> bus invoked once, no events after terminal.

### L-007 — Bus events never reach Laravel's dispatcher and accumulate on the singleton
- **Severity:** high · **Effort:** small · **Impact:** reliability, performance, maintainability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokeResultFinalizer.php:161`, `packages/laravel-capabilities/src/Pipeline/InvokeResultFinalizer.php:184`, `packages/laravel-capabilities/src/Pipeline/InvokeObservation.php:1`
- **Current:** CapabilityInvoked, CapabilityFailed and CapabilityApproval* are only appended to arrays on InvokeObservation, which is owned by the singleton CapabilityRegistry. No Illuminate Dispatcher is called anywhere in src. beginInvoke() does not reset the arrays, so each invoke keeps its event (including output data) and log lines for the life of the process.
- **Recommended:** Dispatch events through an injected Illuminate\Contracts\Events\Dispatcher when events.enabled is true. Keep in-memory capture only as an opt-in test recorder, or cap and reset it per invoke.
- **Why it matters:** Spec §transactions item 5 (docs/spec.md:2454) says bus events are emitted for app listeners. In queue workers and Octane the arrays grow without bound (memory leak proportional to invokes x output size), and host listeners never fire.
- **How to fix:** Add an optional `?Dispatcher $events` to InvokeResultFinalizer, bound in makeRegistry from `$app->bound('events')`, and call `$events?->dispatch($event)`. Add `$this->observation->reset()` in beginInvoke() for the event/log arrays, or keep them only while a fake() recorder is active. Unit tests: a fake Dispatcher receives CapabilityInvoked once, and 1000 invokes leave the observation arrays bounded.

### L-012 — Approval store SQL fails on MySQL/Postgres (JSON scope, '' lease compare)
- **Severity:** high · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php:33`, `packages/laravel-capabilities/src/Boot/ContainerBindings.php:55`, `packages/laravel-capabilities/database/migrations/2026_07_27_000001_create_capabilities_approvals_table.php:25`, `packages/laravel-capabilities/src/Persistence/QueryTableGateway.php:178`
- **Current:** ApprovalManager::request stores the tenant id string as 'scope'. The gateway maps it to the JSON column scope_json and only JSON-encodes arrays or objects, so a tenant such as 'acme' or a UUID is inserted as invalid JSON. claimLease also compares the timestamp column execution_lease_until with ''. PostgreSQL rejects both (invalid json / invalid timestamp input), and MySQL rejects the JSON insert and in strict mode can reject the '' datetime comparison in UPDATE. Unit tests run on SQLite, which accepts both.
- **Recommended:** Always JSON-encode values for *_json columns (or store scope as {'tenant_id': ...}). Drop the '' branch from the lease predicate (the column is a nullable timestamp). Write timestamps in a portable 'Y-m-d H:i:s' UTC form instead of DATE_ATOM, which MariaDB rejects.
- **Why it matters:** approval.store=database is the package default. In any multi-tenant app with non-numeric tenant ids, the first approval request raises a QueryException, and on PostgreSQL every accept fails before it reaches the domain.
- **How to fix:** QueryTableGateway::encodeValue: `if ($this->isJsonColumn($k)) return json_encode($value, ...)` for scalars too, with decodeValue symmetric. updateWhereLeaseFree: keep only whereNull OR <= now. Unit tests (still DB-free): assert the encoded row for scope 'acme' is the JSON string '"acme"', and assert the builder SQL for claimLease via `$query->toSql()` on a grammar-only connection contains no `= ?` with '' binding.

### M-002 — Make ProcessTelegramUpdateJob actually queue and let failures fail the job
- **Severity:** high · **Effort:** small · **Impact:** reliability, performance
- **Locations:** `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdateJob.php:11`, `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:89`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:84`
- **Current:** Illuminate\Bus\Dispatcher::dispatch() only queues commands that implement ShouldQueue; this job does not, so the whole update (identity, agent turn, registry invokes, Bot API reply) runs inline inside the webhook HTTP request. ProcessTelegramUpdate::handle() also catches every Throwable and returns an array, so the job can never fail, retry, or land in failed_jobs.
- **Recommended:** ProcessTelegramUpdateJob implements ShouldQueue (with Queueable, tries/backoff, optional queue/connection config) and transient failures (Bot API 429/5xx, registry retryable) propagate so the queue retries; terminal outcomes (identity_unresolved, rate_limited) return without throwing.
- **Why it matters:** Spec pipeline is 'verify webhook secret -> queue ProcessTelegramUpdate'. Inline processing makes Telegram wait on LLM/tool latency; Telegram re-delivers webhooks that do not answer in time, doubling replies. D-019 failed-job tags (failedJobTags()) are unreachable because no job ever fails.
- **How to fix:** Add `implements ShouldQueue` + `use Queueable` (or public $tries/$backoff/$queue/$connection like RunTurnJob). In ProcessTelegramUpdateJob::handle, rethrow when the result is a retryable failure (map from error code / TelegramBotApiException::$retryable). Unit test: `expect(new ProcessTelegramUpdateJob([]))->toBeInstanceOf(ShouldQueue::class)` and a retryable Bot API failure makes handle() throw.

### M-003 — Shrink approval callback_data to Telegram's 64-byte limit
- **Severity:** high · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities-messaging/src/Notifiers/TelegramApprovalNotifier.php:83`, `packages/laravel-capabilities-messaging/src/Telegram/TelegramCallbackSigner.php:100`
- **Current:** encode() base64s a JSON object carrying approval_id, action, exp, approver_hint and a 64-hex HMAC. Measured: 195 bytes for approval id '1' with empty hint, 228 bytes for a 26-char ULID. Telegram rejects inline buttons whose callback_data exceeds 64 bytes (BUTTON_DATA_INVALID).
- **Recommended:** callback_data is at most 64 bytes, e.g. a compact 'a|<approval_id>|<exp>|<truncated HMAC>' token or a short random handle mapped server-side to the signed payload, with a unit test pinning the size budget.
- **Why it matters:** Every real notifyPending() through HttpTelegramBotClient will 400, get audited as approval.notify_failed and rethrown, so chat approvals never reach a human. FakeTelegramBotClient does not validate size, so the unit suite cannot see it.
- **How to fix:** Pack as `{a|r}{approval_id}.{exp base36}.{base64url(hmac)[0..15]}` (~50 bytes for a ULID; hint bound into the HMAC input but not transmitted — verify against the row's stored approver hint or drop hint binding). Add `expect(strlen($signer->encode($signer->sign(str_repeat('Z',26),'accept','12345'))))->toBeLessThanOrEqual(64)` and make FakeTelegramBotClient reject callback_data > 64 bytes so tests mirror the Bot API.

### L-001 — Class-based capabilities never run their own authorize()/needsApproval()
- **Severity:** high · **Effort:** medium · **Impact:** security, reliability, maintainability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:572`, `packages/laravel-capabilities/src/Discovery/AttributeDiscoverer.php:100`, `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:898`, `packages/laravel-capabilities/src/Contracts/DefinesCapability.php:13`, `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:168`
- **Current:** For #[Capability] classes (the canonical D-017 path), the pipeline only calls the handler's run(). The class's authorize() is never invoked: allows() falls through to the global Authorizer, which the provider never binds, so it defaults to StubAuthorizer::deny(). needsApproval() on the class is never read either. Handlers are built with `new`, so constructor dependencies cannot be injected.
- **Recommended:** When a definition has handlerClass, resolve the handler once per invoke through the container. Call its authorize() in stageAuthorize and in authorizes(), and its needsApproval() in stageNeedsApproval, with the same arity rules run() already uses. A handler that implements DefinesCapability but has no authorize() should fail closed at discovery with a clear BootException.
- **Why it matters:** Spec §capability class (docs/spec.md:755-800) makes authorize/needsApproval/run on the class the canonical contract. As written, a host that follows the spec gets every class capability denied (fail-closed but unusable). If the host binds a permissive Authorizer to get unblocked, the per-capability authorize() and approval rules are silently skipped. The suite hides this because PipelineHelpers::harness uses StubAuthorizer::allow() and RunArityTest only checks run().
- **How to fix:** In InvokePipeline, add `private function handler(CapabilityDefinition $d): ?object` that uses `Container::getInstance()->make($d->handlerClass)` (injectable factory for units) and memoises it on InvokeState. In allows(): `if ($h && method_exists($h,'authorize')) return (bool) $h->authorize($input,$ctx);`. Do the same for needsApproval in stageNeedsApproval. Unit tests: fixture class with authorize() returning false plus registry built with StubAuthorizer::allow() gives forbidden; class needsApproval() returning true gives approval_required; a class with a constructor dependency resolves from a fake container.

### L-002 — Approval is never requested; per-capability approval policy is ignored
- **Severity:** high · **Effort:** medium · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:617`, `packages/laravel-capabilities/src/Registry/CapabilityDefinitionBuilder.php:208`, `packages/laravel-capabilities/src/Approval/ApprovalManager.php:378`, `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:638`
- **Current:** Approval is only triggered by invoke options (needs_approval, needs_approval_callback, require_approval). No adapter (HTTP, MCP, AI, job, artisan) sets them, and neither CapabilityDefinition nor the fluent builder can declare needsApproval. At accept time, ApprovalManager applies its single global policy (default requester_or_role), never the capability's approvalPolicy (e.g. 'role:finance'). approvalTtlHours is never passed to request().
- **Recommended:** Make approval part of the definition. Add a needsApproval callable to CapabilityDefinition/Builder (plus class-method support, see L-001) and evaluate it in stageNeedsApproval. Persist the capability's approvalPolicy and TTL on the approval row. Have accept/reject build the ApprovalPolicy from the row's policy, falling back to the global default.
- **Why it matters:** Locked decision in AGENTS.md: governance (authz, approval, audit) is part of the capability on every surface. Today the whole D-006 state machine (store, controllers, resumer, notifiers) cannot be reached from any real surface. A capability that declares approvalPolicy 'role:finance' would let the requester self-approve under the global requester_or_role.
- **How to fix:** CapabilityDefinition: add `public readonly mixed $needsApproval = null`, plus Builder::needsApproval(callable). In buildApprovalRequired, add 'approval_policy' => $def->approvalPolicy and 'approval_ttl_hours' => $def->approvalTtlHours. In ApprovalManager::accept/reject: `$policy = isset($row['approval_policy']) ? ApprovalPolicy::fromString($row['approval_policy']) : $this->policy;` (add a nullable approval_policy column in a new migration). Unit tests: fluent needsApproval true on the HTTP caller gives approval_required; a role:finance row accepted by the requester without the role gives forbidden.

### L-006 — Audit is silently off in production; strict mode and approval audit are no-ops
- **Severity:** high · **Effort:** medium · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokeAuditStage.php:44`, `packages/laravel-capabilities/src/Boot/ContainerBindings.php:283`, `packages/laravel-capabilities/src/Boot/ContainerBindings.php:522`, `packages/laravel-capabilities/src/Audit/AuditOutbox.php:1`, `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:128`
- **Current:** The defaults are audit.enabled=true and driver=database, and the package ships a capabilities_audit_outbox migration. But no production AuditWriter exists (only InMemory and Failing), the provider never injects a bound AuditWriter, and the outbox is a PHP array. Every audit record is dropped, audit.mode=strict / required=true cannot fail, and approval request/decision audit is dropped too. The only signal is a warning in IntegrationHealthChecker:109.
- **Recommended:** Ship a first-party DatabaseAuditWriter (TableGateway on capabilities_audit_outbox) for driver=database. Have the provider inject any bound AuditWriter into both the registry and ApprovalManager. In strict or required mode with no writer, fail at boot (BootGuard) instead of passing silently.
- **Why it matters:** AGENTS.md locked decisions: audit is part of the capability and D-010 defines best_effort vs strict. A governance product whose audit trail is empty by default, with no error, is the worst failure mode: operators believe they have records they do not have.
- **How to fix:** Add Persistence/DatabaseAuditWriter implements AuditWriter (gateway insert into MigrationCatalog::TABLE_AUDIT_OUTBOX). In makeRegistry, choose the writer by audit.driver (database → DatabaseAuditWriter, log → Log channel writer). In the provider, `if ($app->bound(AuditWriter::class)) $registry->withAuditWriter($app->make(AuditWriter::class))`, and the same for ApprovalManager::withAudit. BootGuard: throw when mode=strict or required=true and the writer would be null. Unit tests with ArrayTableGateway.

### M-010 — Invoke AI turn tool calls as caller=agent, not job
- **Severity:** high · **Effort:** medium · **Impact:** security, maintainability
- **Locations:** `packages/laravel-capabilities-ai/src/Support/ResolveConversationActor.php:18`, `packages/laravel-capabilities-ai/src/Support/ResolveConversationActor.php:107`, `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php:105`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:291`
- **Current:** Every LLM-chosen tool call from TurnRunner reaches the bus as caller=job. The core surface gate and needsApproval(ctx) therefore see a job, not an agent: CAPABILITIES_SURFACE_AGENT=false does not stop AI turns, a capability narrowed to surfaces [http, job] (deliberately no agent) is still callable by the model, and approval rules keyed on caller=agent do not fire. The messaging sibling uses caller=agent for the identical case.
- **Recommended:** TurnRunner tool invokes pass caller=agent (plus tool_profile for audit); the proposal-accept path's caller is decided explicitly in the spec (it is a human confirming via HTTP) instead of inheriting a legacy job shape.
- **Why it matters:** Spec Surfaces §1 treats in-app LLM tool use as the agent surface, and 'per-capability surfaces can only narrow' is a governance guarantee. Only README text records caller=job, as a legacy shape; no D-0xx decision covers it. The host ToolCatalog limits what is offered, but surface narrowing and the agent kill switch exist precisely as defense in depth against catalog mistakes.
- **How to fix:** Add `invokeOptions(object $actor, string $caller, array $extra)` (or CALLER_AGENT) and use 'agent' in TurnRunner; add `capabilities-ai.agent_profile` (nullable) passed as tool_profile. Keep ProposalService on its current caller until a spec line decides it. Unit test with a bus spy: TurnRunner options['caller'] === 'agent'. Record the decision in docs/spec.md.

### C-001 — Implement device-code polling; CLI expects access_token from device start
- **Severity:** high · **Effort:** medium · **Impact:** reliability, dx
- **Locations:** `packages/capabilities-cli/internal/auth/session.go:50`, `packages/capabilities-cli/internal/auth/session.go:59`, `packages/laravel-capabilities/src/Contracts/AuthTokenIssuer.php:25`, `packages/laravel-capabilities/tests/Fixtures/HttpHelpers.php:189`, `packages/capabilities-cli/internal/auth/auth_test.go:69`, `packages/laravel-capabilities/src/Adapters/Http/AuthController.php:105`
- **Current:** `capabilities auth login --base-url=URL` (the documented default mode) makes one POST /capabilities/auth/device and requires `access_token` in that response. The core contract says issueDeviceCode *starts* a flow, and the core's own fixture returns the RFC 8628 start shape (device_code, user_code, verification_uri, interval) with no token. The CLI unit test fakes a server that returns a token straight from the device endpoint, so the two sides are each tested against a different contract.
- **Recommended:** The CLI prints user_code + verification_uri, then polls POST /capabilities/auth/token with the device-code grant at `interval` until it gets a token, is denied, or passes `expires_in`. The core documents the pending/slow_down shape in AuthTokenIssuer so hosts can signal it.
- **Why it matters:** Against any host issuer written to the core contract, the default login path always fails with 'device login response missing access_token' (exit 1). The spec lists 'Device-code polling UX on auth routes' (docs/spec.md:3749) as expected. AuthController wraps whatever the issuer returns in ok:true, so today an issuer has no error-envelope way to say 'authorization_pending'. Both sides need to agree on that.
- **How to fix:** Core: in the AuthTokenIssuer docblock, define issueToken for grant_type=urn:ietf:params:oauth:grant-type:device_code, returning either {access_token,...} or {status:'authorization_pending'|'slow_down'|'access_denied'|'expired_token'}. Unit-test AuthController with the fake issuer. CLI: in LoginDeviceCode, parse data.{device_code,user_code,verification_uri,interval,expires_in}, write the prompt to stderr, and loop on client.LoginToken({grant_type, device_code, client_id}) with an injectable sleeper/clock. Keep the rule that the base URL is written only after a token is obtained. Replace the auth_test.go fake with the core fixture shape plus a pending-then-token sequence.

### M-001 — Wire Telegram ingress to a real agent and profile tools (bound path is an echo bot)
- **Severity:** high · **Effort:** medium · **Impact:** reliability, maintainability
- **Locations:** `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:118`, `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:144`, `packages/laravel-capabilities-messaging/src/Telegram/TelegramAdapter.php:69`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:391`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:70`, `packages/laravel-capabilities-messaging/README.md:13`, `packages/laravel-capabilities/src/Contracts/CapabilityBus.php:11`
- **Current:** The container-bound TelegramAdapter has no ingress handler, so ConversationIngress::handle() returns the user's own text as the reply; ProcessTelegramUpdate is bound without a profile resolver, so the profile tool list is always [] and $agentRunner is dead state. A linked Telegram user gets their message echoed back, and a tapped approval button (callback_query.data) is echoed as text. README/user-guide claim messages reach the agent with the configured profile.
- **Recommended:** Default ingress fails closed (clear 'no agent bound' error) instead of echoing, and the provider binds a profile resolver that turns agent_profile into tool names through a published core contract. Hosts plug the actual agent turn (laravel/ai or the AI sibling) via one documented binding.
- **Why it matters:** Spec §6 pipeline is ConversationIngress -> laravel/ai agent (tools = profile) -> registry. D-007 forbids messaging from reaching into core internals, and core's only profile->tools API (CapabilityRegistry::aiTools) is not on a published contract, which is likely why the resolver was left unbound. Unit tests inject resolvers/handlers, so the suite is green while the shipped wiring is an echo bot.
- **How to fix:** 1) Core: expose profile tool names on a published contract (e.g. add `toolNames(string $profile): list<string>` to a small `ToolProfiles` contract bound to the registry). 2) MessagingServiceProvider: pass `profileResolver: fn ($p) => $app->make(ToolProfiles::class)->toolNames($p)`; bind an `ingressHandler` from a host-overridable abstract (e.g. `Rawphp\CapabilitiesMessaging\Contracts\AgentTurn`) and throw RuntimeException('No agent turn bound for messaging ingress') when unbound. 3) Delete the unused $agentRunner param. 4) Unit test: provider-built ProcessTelegramUpdate with no AgentTurn bound fails closed (no reply sent).

### X-001 — Enforce the 95% coverage floor in CI; all four packages are below it
- **Severity:** high · **Effort:** medium · **Impact:** reliability, maintainability
- **Locations:** `AGENTS.md:78`, `.github/workflows/tests.yml:29`, `docs/versioning.md:36`, `composer.json:58`
- **Current:** AGENTS.md calls 95% line coverage blocking, but CI installs PHP with `coverage: none`, no composer script measures it, and Go runs plain `go test`. Measured today (PHP 8.5 + pcov, `pest --coverage --min=95`): core 92.5%, messaging 91.4%, AI 89.1%. Go CLI: 88.5% total (`go tool cover -func`), with helpfmt at 78.4% and flagschema at 83.6%. The suites are green, but every package misses the floor.
- **Recommended:** CI runs PHP with pcov and `pest --coverage --min=95` for each package, and Go with a coverage-profile threshold. A PR that drops a package below the floor fails. If the floor is not realistic yet, lower it in AGENTS.md to the measured value and ratchet it up.
- **Why it matters:** The policy is enforced only by agent discipline, and it has drifted 3–6.5 points under the bar on every package. The prior audit's X-005 was deferred in UR-012 as polish. Measurement now shows the stated gate is false, not just unchecked, so severity stays high: docs and agents assert a guarantee the repo does not meet.
- **How to fix:** tests.yml php job: set `coverage: pcov`. Add composer scripts `test:core:coverage` = `pest --configuration=packages/laravel-capabilities/phpunit.xml --coverage --min=95` (and the same for messaging and AI), then call them from CI. Go job: `go test -coverprofile=c.out ./... && go tool cover -func=c.out | awk '/^total:/ {sub("%","",$3); if ($3+0 < 95) exit 1}'`. Then close the gap with real tests, or delete dead paths, before turning the gate on.

## Quick Wins (high impact / low effort)

### C-003 — Pass --base-url override into domain/verb catalog load
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability, security
- **Locations:** `packages/capabilities-cli/cmd/capabilities/cmd_domain.go:18`, `packages/capabilities-cli/cmd/capabilities/cmd_domain.go:19`, `packages/capabilities-cli/cmd/capabilities/cmd_domain.go:83`, `packages/capabilities-cli/cmd/capabilities/cmd_domain.go:65`
- **Current:** For `capabilities --base-url=B <domain> <verb>`, the synthesis index (domain/verb → canonical name) is built from the profile's stored base URL, not from B. The resolved canonical name is then invoked against B. Every other command (catalog, describe, run, approvals) respects the override.
- **Recommended:** loadSynthIndex takes `base` and passes it to clientFor, so resolution and invoke hit the same deployment.
- **Why it matters:** The CLI is built for one binary across many deployments (profiles plus --base-url). With the override, a domain/verb that exists only on B is reported unknown (exit 5). Worse, when the two deployments map the same domain/verb to different capabilities, the CLI POSTs a canonical name chosen from deployment A's catalog to deployment B, and sends the token to A as well.
- **How to fix:** Change the signature to `loadSynthIndex(env Env, profile, base string)` and use `clientFor(env, st, profile, base)`. Add a unit test with two httptest servers asserting the catalog GET goes to the override host.

### L-017 — Agent/MCP handle() invokes the full catalog when no profile is active
- **Severity:** medium · **Effort:** trivial · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Adapters/Ai/AiToolAdapterV1.php:183`, `packages/laravel-capabilities/src/Adapters/Mcp/McpToolAdapterV1.php:217`
- **Current:** If handle() is called before register(), or register failed and left activeProfile null, with no options['profile'], the adapters invoke any capability whose surfaces include agent/mcp by name. Tool-list generation refuses a full catalog (ProfileRequiredException), but execution does not apply the same rule.
- **Recommended:** When surfaces.<agent|mcp>.require_profile is true (the default), return failure('not_runnable', normalized_code 'profile_required') if no profile resolves, matching the multi-profile guard McpToolAdapterV1 already has.
- **Why it matters:** D-008: least privilege for model tool calls, and progressive disclosure is not an escape hatch. A model can name a capability that is not in its tool list, and the fail-open fallback executes it (subject only to authorize).
- **How to fix:** Inject requireProfile into both adapters from config (the provider already builds McpToolAdapterV1). Replace the final `return $this->registry->invoke(...)` with the failure when requireProfile. Unit tests: an unregistered adapter handle('x') gives profile_required and run() is not called; require_profile=false keeps the current behaviour.

### L-018 — Unauthenticated auth/token and device routes ship without throttling
- **Severity:** medium · **Effort:** trivial · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Http/RouteTable.php:105`, `packages/laravel-capabilities/config/capabilities.php:82`
- **Current:** The package strips auth:sanctum from POST /capabilities/auth/token, /auth/device and GET /auth/callback, which is correct because they issue credentials. It adds no rate limit. On Laravel 11+ the default 'api' group has no throttle unless the host opts in, so these credential-issuing endpoints accept unlimited attempts.
- **Recommended:** Attach a configurable throttle (e.g. surfaces.http.auth_middleware defaulting to ['api','throttle:6,1']) to the unauthenticated auth routes.
- **Why it matters:** Brute-forcing password grants and device codes against the host AuthTokenIssuer is the standard attack on exactly these endpoints. The package owns the route table, so it should own a safe default.
- **How to fix:** In RouteTable::definitions, for the three auth keys use `$httpConfig['auth_middleware'] ?? [...withoutAuthMiddleware($middleware), 'throttle:capabilities-auth']`, and register a named RateLimiter in the provider. Unit test on RouteTable::definitions shows the auth routes include a throttle entry and list/invoke keep auth:sanctum.

### M-004 — Sign callbacks with telegram.callback_secret, never a literal fallback key
- **Severity:** medium · **Effort:** trivial · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:113`, `packages/laravel-capabilities-messaging/src/Boot/MessagingBindings.php:153`, `packages/laravel-capabilities-messaging/src/Notifiers/TelegramApprovalNotifier.php:64`, `packages/laravel-capabilities-messaging/config/capabilities-messaging.php:12`
- **Current:** The container always injects a signer keyed on the webhook secret, so the documented TELEGRAM_CALLBACK_SECRET is dead config. When the webhook secret is unset (e.g. skip_boot_checks outside production) buttons are signed with the public constant 'deferred-unset'.
- **Recommended:** One signer built lazily from MessagingConfig::callbackSecret() (which already falls back to the webhook secret and throws when neither is set); no hard-coded key anywhere.
- **Why it matters:** A host that sets a distinct callback secret and builds CallbackHandler per the user guide verifies with a different key than the notifier signed with, so every button returns invalid_signature_or_expired. A known constant HMAC key is forgeable wherever it is used.
- **How to fix:** Replace both `webhookSecret() ?? 'deferred-unset'` with `$cfg->callbackSecret()` (the singleton closure resolves lazily, so D-021 boot is unaffected). Unit test: config with callback_secret='cb' and webhook_secret='wh' -> provider-bound signer verifies a payload signed with 'cb'; no secrets -> resolving the signer throws.

### X-002 — Add LICENSE (and SECURITY.md) to the split laravel-capabilities-ai package
- **Severity:** medium · **Effort:** trivial · **Impact:** security, maintainability
- **Locations:** `packages/laravel-capabilities-ai/composer.json:5`, `packages/laravel-capabilities-ai/`, `SECURITY.md:20`
- **Current:** split-packages.yml mirrors `packages/laravel-capabilities-ai` to the public rawphp/laravel-capabilities-ai repo, and v* tags are force-pushed there too. That tree has no LICENSE file and no SECURITY.md. The root SECURITY.md lists only the other three packages as report targets.
- **Recommended:** The AI package root carries the same MIT LICENSE and a package SECURITY.md as its siblings. Root SECURITY.md names all four packages.
- **Why it matters:** MIT requires the copyright and permission notice to ship with the software. Composer metadata alone does not satisfy that, and GitHub shows the public repo as unlicensed. The package also has no private vulnerability-report path, even though it holds the Anthropic LLM client and turn endpoints.
- **How to fix:** cp packages/laravel-capabilities-messaging/LICENSE packages/laravel-capabilities-ai/; adapt packages/laravel-capabilities-messaging/SECURITY.md into the AI package; add `laravel-capabilities-ai` to the list at root SECURITY.md:20. Optional: a unit test in the AI package's tests/Unit/Architecture that asserts is_file(LICENSE) && is_file(SECURITY.md), mirroring the existing DocsTest.

### L-008 — Per-store DB connection config is ignored under a real Laravel container
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability, scalability
- **Locations:** `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:373`, `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:384`, `packages/laravel-capabilities/config/capabilities.php:129`
- **Current:** boundConnectionOrNull() returns the container's ConnectionInterface before it looks at approval.connection or idempotency.connection. The Laravel framework aliases ConnectionInterface to 'db.connection', the default connection, so the named-connection branch at lines 392-410 is unreachable in real apps.
- **Recommended:** Check the store-specific connection name first and resolve it through the 'db' manager. Use a bound ConnectionInterface only when no name is configured.
- **Why it matters:** Hosts that point approvals or idempotency at a dedicated connection (primary vs read replica, separate schema) silently write to the default connection. The config comment and README both promise the named connection works.
- **How to fix:** Reorder: `$name = $storeKey-specific ?? database.connection; if ($name !== null) return $app->make('db')->connection($name);` then fall back to the bound ConnectionInterface. Unit test with a fake container where ConnectionInterface is bound AND db->connection('ledger') returns a different fake: expect the 'ledger' connection for store=approval.

### L-011 — Idempotency config (ttl_hours, header, enabled, warn) never reaches the guard
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability, dx
- **Locations:** `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:164`, `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:494`, `packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php:118`, `packages/laravel-capabilities/config/capabilities.php:155`
- **Current:** IdempotencyGuard is always built with IdempotencyConfig::defaults() and SystemClock. idempotency.enabled, ttl_hours, header and warn_missing_key from the published config are never applied. The HTTP controller reads an undocumented surfaces.http.idempotency_header instead of idempotency.header.
- **Recommended:** Build the guard from IdempotencyConfig::fromArray($config['idempotency']) and the registry clock in makeRegistry/withIdempotencyStore. Read the header name from the same config in CapabilityController.
- **Why it matters:** Operators who change the TTL or disable missing-key warnings see no effect. The header setting exists in two places and only the undocumented one works.
- **How to fix:** Add `withIdempotencyConfig(array)` on CapabilityRegistry that rebuilds the guard with the store, clock and IdempotencyConfig. Call it from ContainerBindings::makeRegistry. Pass idempotency.header into CapabilityController's constructor from the provider. Unit tests: ttl_hours=1 gives expires_at of now+1h, and header 'X-Idem' is honoured.

### M-011 — Read table_prefix from the app config, not the package file
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability, performance
- **Locations:** `packages/laravel-capabilities-ai/src/Models/TableNames.php:14`, `packages/laravel-capabilities-ai/config/capabilities-ai.php:29`
- **Current:** Every model getTable() and every migration re-requires the package's own config file and reads the env var via getenv. A host-published/overridden config('capabilities-ai.table_prefix') is ignored, and after `php artisan config:cache` Laravel no longer loads .env, so a prefix set only in .env silently falls back to capabilities_ai_ at runtime.
- **Recommended:** TableNames reads config('capabilities-ai.table_prefix') when a container with config is bound (cached config honored), falling back to the package file only in container-less unit boots.
- **Why it matters:** Migrations run before config:cache can create prefixed tables while cached-config workers query unprefixed ones: every conversation/turn query then fails. Re-requiring a PHP file on each query is also wasted work.
- **How to fix:** `if (function_exists('app') && app()->bound('config')) { $p = config('capabilities-ai.table_prefix'); if (is_string($p) && $p !== '') return $p; }` then existing fallback; memoize in a static. Unit test: bound config repository with prefix 'x_' -> TableNames::turns() === 'x_turns'.

### M-016 — Refuse new messages on closed conversations
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities-ai/src/Domain/ConversationService.php:57`, `packages/laravel-capabilities-ai/src/Domain/ConversationService.php:167`
- **Current:** DELETE /conversations/{ulid} marks the conversation closed, but createUserMessage() on that ulid still creates a message and dispatches a new LLM turn.
- **Recommended:** createUserMessage() rejects a closed conversation (409 conflict) before persisting anything.
- **Why it matters:** Destroy/close is the user's way to end a conversation; silently reopening it for new paid turns contradicts that and makes the status column meaningless.
- **How to fix:** After loading the owned conversation: `if ($conversation->status === 'closed') throw new RuntimeException("Conversation {$ulid} is closed");` and map RuntimeException to 409 in storeMessage(). Unit test: destroy then createUserMessage throws and dispatch spy not called.

### L-009 — Uncaught throwables leak messages and strand idempotency keys
- **Severity:** medium · **Effort:** small · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:201`, `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:596`, `packages/laravel-capabilities/src/Pipeline/InvokeResultFinalizer.php:40`
- **Current:** The outer catch in InvokePipeline::execute() returns $e->getMessage() on the wire and does not report the exception. That covers exceptions from authorize() callables, stores, the rate limiter, output validation and strict audit. A QueryException message includes SQL and bindings. The catch also skips finishFailure, so an idempotency row claimed in stageIdempotencyLookup stays 'processing' and every retry with that key gets 'busy' until the TTL (default 24h) expires.
- **Recommended:** Route the outer catch through the same sanitising as runFailure(): report to ExceptionHandler and return a generic 'Internal error.' message. Then call finishFailure (or mark the idempotency row failed) so a claimed key is released.
- **Why it matters:** D-005 requires stored outcomes. A permanently busy key breaks CLI and agent retries after a transient failure. Leaking SQL text to HTTP, MCP and agent callers is an information-disclosure issue, and a model-facing surface will repeat it verbatim.
- **How to fix:** In the catch: `$this->reportThrowable($e); $failure = CapabilityResult::failure('internal','Internal error.', meta: [...]); return $this->results()->finishFailure($state, $failure);` (guard against re-throw). Unit tests: an authorize closure that throws RuntimeException('SQLSTATE secret') returns message 'Internal error.'; a second invoke with the same key replays the stored failure instead of returning busy.

### M-007 — Resolve chat identities to the host user model, not a LinkedUser DTO
- **Severity:** medium · **Effort:** small · **Impact:** security, reliability
- **Locations:** `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:102`, `packages/laravel-capabilities-messaging/src/Identity/IdentityLinker.php:39`, `packages/laravel-capabilities-ai/src/Support/ResolveConversationActor.php:51`
- **Current:** The bound IdentityLinker has no user factory and there is no config for one, so every Telegram tool call reaches CapabilityBus::invoke with actor = LinkedUser (id/tenantId DTO). Capability authorize()/policies written against the host User model receive a different type; hosts must rebind the whole linker to fix it.
- **Recommended:** A `capabilities-messaging.user_model` key (fallback auth.providers.users.model) drives the default userFactory, which loads the model and fails closed when the id does not resolve, the same rule the AI sibling applies.
- **Why it matters:** Governance runs on the actor (D-002/D-003). Two siblings resolving the actor differently means the same capability is authorized against different principal types per surface, which is how policy checks silently deny (or, with loose policies keyed on ->id, allow) the wrong thing.
- **How to fix:** In MessagingServiceProvider pass `userFactory: fn (string $id, ?string $tenant) => $model::query()->find($id) ?? throw new RuntimeException(...)` built from config; keep LinkedUser only as the explicit test default. Unit test with a stub model class that the bound linker returns that class and fails closed on unknown id.

### M-013 — Rate-limit AI chat message creation per user
- **Severity:** medium · **Effort:** small · **Impact:** security, scalability
- **Locations:** `packages/laravel-capabilities-ai/config/capabilities-ai.php:34`, `packages/laravel-capabilities-ai/config/capabilities-ai.php:110`, `packages/laravel-capabilities-ai/src/Http/ChatController.php:65`
- **Current:** Each POST /messages creates a turn and dispatches an LLM job. There is no per-user limit, the global ceiling defaults to unlimited, and core's RateLimiter contract (D-013) is not used, although the messaging sibling applies it for turns_per_minute.
- **Recommended:** A `turns_per_minute` (per authenticated user) limit through core's RateLimiter, returning 429 rate_limited before anything is persisted, on by default with a sane value.
- **Why it matters:** Any authenticated user can spend unbounded provider tokens and fill the queue. D-013 names agent loop protection as a bus concern, and the two siblings should enforce it the same way.
- **How to fix:** Inject `?RateLimiter` into ConversationService (bound when core provides it); key `rl:ai:user:{userId}`, `tooManyAttempts($key, $max)` -> throw TurnCapacityExceededException-like RateLimited; `hit($key, 60)`. Unit test with an in-memory RateLimiter fake: N+1th create throws and persists nothing.

### C-002 — Honour server error.cli_exit; CLI remaps 10 server codes to exit 1
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/capabilities-cli/internal/api/errors.go:31`, `packages/capabilities-cli/internal/api/errors.go:88`, `packages/capabilities-cli/internal/api/errors.go:194`, `packages/capabilities-cli/internal/api/client.go:205`, `packages/laravel-capabilities/src/Support/ErrorCodeMap.php:13`, `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:881`, `packages/laravel-capabilities/src/Approval/ApprovalManager.php:405`
- **Current:** The core ErrorCodeMap has 20 codes, and every error envelope carries `cli_exit`. The CLI only knows the 10 codes from the original D-018 table, never decodes `error.cli_exit`, and maps every other code to exit 1 (internal). Examples: run after sunset (`gone`) exits 1 instead of 5, `approvals accept` on an expired approval exits 1 instead of 5, a profile refusal (`capability_not_in_profile`) exits 1 instead of 3, and login against an unbound issuer (`not_configured`) exits 1 instead of 5.
- **Recommended:** When the envelope's `cli_exit` is an integer from 1 to 6, the CLI uses it as the process exit code. The local table is only a fallback for non-envelope responses. codeFromHTTP maps 410 to a domain-class code, not internal.
- **Why it matters:** docs/agents.md tells agents to branch on exit codes 1–6, and exit 1 reads as 'internal error, retry later'. Agents will retry sunset capabilities, expired approvals and profile denials that can never succeed. The server already sends the right answer on the wire, and the client drops it. That is two exit tables drifting apart with no shared fixture.
- **How to fix:** Add `CLIExit *int `json:"cli_exit"`` to ErrorBody. In ParseErrorEnvelope, set ExitCode from *CLIExit when 1<=v<=6, otherwise ExitCode(code). Add `case 410: return CodeDomainError`-class handling in codeFromHTTP, or a dedicated code. Add a table-driven Go test that loads a JSON fixture of ErrorCodeMap::MAP (generate it once from PHP into testdata and assert it in a core unit test) so each side fails when the other adds a code.

### C-004 — Invalidate cached describe schema on server schema_version/etag change
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/capabilities-cli/internal/catalog/service.go:90`, `packages/capabilities-cli/internal/catalog/cache.go:62`, `packages/capabilities-cli/internal/run/runner.go:131`, `packages/capabilities-cli/cmd/capabilities/cmd_run.go:148`, `packages/capabilities-cli/internal/catalog/service.go:112`, `packages/laravel-capabilities/src/Schema/CatalogPresenter.php:77`
- **Current:** Describe cache entries have no TTL and are read with an empty version, so a cached input schema is used indefinitely. It is cleared only by `--no-cache`, `catalog --refresh`, re-login or token change. The server sends per-row schema_version and a list etag, and the domain/verb path fetches the live list on every call, but neither value is compared with the cache. The auth-login 'prefetch' only clears the cache.
- **Recommended:** Cached entries are checked against the server's current schema_version for that capability. The domain/verb path already has the live list rows, and `run` can use a cheap list or an ETag/If-None-Match describe. A mismatch refetches.
- **Why it matters:** Local validation and flag parsing fail closed before the network (exit 2). After a server schema change (field renamed, requirement dropped, new property exposed as a flag), a stale cache rejects valid input with exit 2 and 'unknown flag'. docs/agents.md tells agents that exit 2 means their input is wrong, so they fix input instead of refreshing the cache.
- **How to fix:** In invokeCapability, pass the matching summary.SchemaVersion (from loadSynthIndex) into Service.Describe via a new DescribeVersion(ctx, name, version) that calls Cache.Get(name, version). For `run` without a list, either fetch the compact list or have the core emit an ETag header on describe (HttpResponse headers) and send If-None-Match. As a cheap safety net, on server `validation_failed` after a local pass, invalidate that entry. Also stop calling Describe twice per run (cmd_run.go:148 and runner.go:118) by passing the entry through.

### C-005 — Verify --token against the API before persisting it over a working profile
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/capabilities-cli/internal/auth/session.go:22`, `packages/capabilities-cli/internal/auth/session.go:31`, `packages/capabilities-cli/cmd/capabilities/cmd_auth.go:75`, `packages/capabilities-cli/cmd/capabilities/cmd_auth.go:87`
- **Current:** LoginWithToken never contacts the server. Its comment says the token was 'accepted', but it writes the base URL and token straight away. The following catalog prefetch drops any 401, so a mistyped or revoked PAT (or the wrong --base-url) prints 'logged in', exits 0, reports logged_in=true in `auth status`, and overwrites the profile's previous working credentials.
- **Recommended:** Before writing anything, `--token` login makes one authenticated GET /capabilities against the normalised base URL. On a structured error it returns that error (unauthenticated gives exit 3) and leaves the profile untouched. This matches the no-clobber guarantee the device and OAuth paths already have.
- **Why it matters:** This is the only login path that works against a host with no AuthTokenIssuer (a bare Sanctum PAT). It is the path agents and CI use, and a silent success there turns into a confusing exit-3 failure on the first real call.
- **How to fix:** Give LoginWithToken a *api.Client (as LoginDeviceCode has). Normalise baseURL, set client.BaseURL/Token, call ListCapabilities, and if res.Err != nil return res.Err before SetBaseURL/SetToken. Update the comment. Extend session_clobber_test.go with a 401 case for the token path.

### L-010 — transactions.wrap_run=true does not wrap run() in a transaction
- **Severity:** medium · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:796`, `packages/laravel-capabilities/config/capabilities.php:106`
- **Current:** When wrap_run is true, the pipeline sets a flag that tests read (lastRunWasWrapped) but calls run() exactly as it does when the flag is off. No DB::transaction or ConnectionInterface::transaction is ever opened.
- **Recommended:** When wrap_run is true, execute run() (and a synchronous audit write, per spec) inside `$connection->transaction(...)` on an injected connection. Otherwise remove the option from config and spec.
- **Why it matters:** Spec D-010 table (docs/spec.md:2477) promises 'Registry wraps run() (+ optional sync audit) in one DB::transaction'. An app that opts in for atomicity gets none and partial writes commit, while tests pass because they only assert the flag.
- **How to fix:** Inject `?ConnectionInterface $transactionConnection` into InvokePipeline via makeRegistry. In stageRun: `$state->output = $this->wrapRun && $conn ? $conn->transaction(fn () => $this->executeRun(...)) : $this->executeRun(...)`. Unit test with a mock ConnectionInterface that expects transaction() to be called once, and zero times when wrap_run is false.

### L-013 — Atomic approval mode: reject ignores the execution lease; racing accept recurses
- **Severity:** medium · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/src/Approval/ApprovalManager.php:455`, `packages/laravel-capabilities/src/Approval/ApprovalManager.php:470`, `packages/laravel-capabilities/src/Approval/ApprovalManager.php:517`
- **Current:** With approval.execution=atomic, the row stays 'pending' with an execution lease while run() executes. A concurrent accept fails claimLease and calls accept() again, which sees pending again and loops until the lease clears (up to lease_seconds=120): a hot DB loop with unbounded recursion. A concurrent reject's compareAndUpdate ignores the lease, so it can flip an executing row to 'rejected' after run() has already side-effected. The executor's later pending→executed update then fails.
- **Recommended:** Before retrying, treat a pending row with a live lease as in progress (return conflict/in_progress) and do not recurse. Make reject lease-aware (use updateWhereLeaseFree), or refuse to reject while a lease is held.
- **Why it matters:** D-006 requires single execution plus a consistent terminal state. Atomic mode is a documented config value (approval.execution).
- **How to fix:** In accept(), check `$row['execution_lease_until'] > now` before claimLease and return failure('conflict', ..., ['in_progress'=>true]). Replace the recursive calls with a single re-read. For reject, use `$this->store->claimLease($id, STATUS_PENDING, $now, [...rejected])`. Unit tests with InMemoryApprovalStore + FixedClock: a held lease gives conflict for both accept and reject, and the executor is not called.

### L-014 — Approval crash-recovery sweep is never scheduled; resume.* config is inert
- **Severity:** medium · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/src/Approval/ResumeApprovedApprovals.php:11`, `packages/laravel-capabilities/config/capabilities.php:133`, `packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCommandTable.php:48`
- **Current:** The default execution=deferred flips a row to 'approved' and then runs it inline. If the process dies in between, only ResumeApprovedApprovals can finish it. Nothing schedules it or exposes a command, and README/user-guide never tell hosts to wire it. resume.enabled=true and every_seconds read as if automatic.
- **Recommended:** When resume.enabled is true, register an Artisan command (capabilities:approvals-resume) and schedule it every every_seconds via callAfterResolving(Schedule::class). Alternatively, fail or warn loudly in integration-health when the command is not scheduled.
- **Why it matters:** The spec (docs/spec.md:1966) makes Shape A plus a scheduled resume job the default package path. Without the scheduler, approved-but-not-executed rows sit in limbo, which is exactly what D-006/P2-004 exists to prevent.
- **How to fix:** Add Adapters/Artisan/ResumeApprovalsCommand calling ResumeApprovedApprovals::handle(), and add it to ArtisanCommandTable. In boot(): `$this->callAfterResolving(Schedule::class, fn ($s) => $s->command('capabilities:approvals-resume')->everyMinute()->withoutOverlapping())` when resume.enabled. Unit-test the pure registration table and the scheduler callback with a fake Schedule.

### L-016 — RunCapabilityJob is not a queueable job; dispatch() only builds an object
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/laravel-capabilities/src/Adapters/RunCapabilityJob.php:17`, `packages/laravel-capabilities/src/Adapters/RunCapabilityJob.php:83`, `packages/laravel-capabilities/src/Adapters/RunCapabilityJob.php:205`
- **Current:** The default-on job surface is a plain object. Its static dispatch() shadows Laravel's convention but enqueues nothing. If a host pushes it to the Bus, handle() gets $options=[], so every user actingAs throws unresolvableUser. There is no failed() hook and no retry or backoff policy.
- **Recommended:** Implement ShouldQueue with Dispatchable/Queueable/InteractsWithQueue. Resolve users through an injected resolver (the same default as OriginalActorAuthorizer, via the auth provider) instead of $options. Rename the pure helpers (make/runNow) so dispatch() really queues. Add failed() that records the job failure tags.
- **Why it matters:** D-002 and the surfaces table present 'job' as a surface. Hosts will reasonably expect `RunCapabilityJob::dispatch([...])` to queue, and it silently does nothing.
- **How to fix:** Add `implements ShouldQueue` plus the traits. Make handle(CapabilityRegistry $registry, ?UserResolver $users = null) use container method injection. Move the current pure dispatch() to `static make()`. Unit tests with Bus::fake()-equivalent fakes (Illuminate\Support\Testing\Fakes\BusFake needs no DB): dispatch pushes one job, handle resolves the user via the fake resolver, and failed() records tags.

### M-005 — Send messaging webhook/processing failures to the Laravel logger
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/laravel-capabilities-messaging/src/Telegram/TelegramWebhookController.php:114`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:448`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:85`
- **Current:** Both classes record errors (bad secret, queue failure, identity_unresolved, rate_limited, registry_forbidden, reply_send_fail) only into private arrays exposed for tests. Nothing is written to a PSR logger, and handle() swallows the exception, so in production a failing bot is silent.
- **Recommended:** Inject an optional Psr\Log\LoggerInterface (container-resolved) and write each entry through it with the D-019 tags; tests assert against a fake logger instead of the internal array.
- **Why it matters:** D-019 asks for failure observability with channel/chat/update tags; the tags are computed (failedJobTags) but never emitted. Combined with M-002 there is no failed job, no log line and no reply for any failure.
- **How to fix:** Add `?LoggerInterface $logger = null` to both constructors; provider passes `$app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null`. log() calls `$this->logger?->log($level, $message, $context)`. Unit test with a recording logger that an unlinked user produces one warning with tags.chat_id.

Plus 11 more findings — see findings.json: M-008 (Give code_link a chat-side bind step (default mode cannot link anyone)), M-012 (Set an HTTP timeout on AnthropicLlmClient that matches max_tokens), X-004 (Constrain sibling packages' core requirement instead of "*"), X-005 (Invert core → AI coupling in integration-health (class-string + config probes)), X-006 (Run Pint and PHPStan in CI; PHPStan is installed but never configured or run), X-007 (CI tests only Laravel 12 on PHP 8.2; add the declared 11/13 and 8.3+ cells), L-015 (Capability discovery rescans and tokenizes app/Capabilities on every boot), M-015 (Expire Redis progress keys and read from the cursor), M-014 (Use the D-018 error envelope for every ChatController failure), M-018 (Reconcile the AI package's SQLite-backed tests with the no-DB rule), X-003 (Split PHP package repos ship a test suite that cannot run outside the monorepo)

## Medium Priority

### M-006 — Move test scaffolding out of messaging production classes
- **Severity:** medium · **Effort:** medium · **Impact:** maintainability, reliability
- **Locations:** `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:108`, `packages/laravel-capabilities-messaging/src/Telegram/ProcessTelegramUpdate.php:189`, `packages/laravel-capabilities-messaging/src/Telegram/TelegramWebhookController.php:23`, `packages/laravel-capabilities-messaging/src/Notifiers/TelegramApprovalNotifier.php:29`, `packages/laravel-capabilities-messaging/src/Telegram/TelegramAdapter.php:19`, `packages/laravel-capabilities-messaging/src/Support/HttpTelegramBotClient.php:83`
- **Current:** Production classes carry fault-injection flags (fail_at, failNext, failIngress/failReply), a runPipeline() simulator that 'marks' verify_webhook_secret without verifying anything, guard counters that are constant zero (so tests asserting them are tautologies), and recorder arrays on container singletons that grow for the life of a queue worker. 142 test calls go through runPipeline() rather than the real controller -> UpdateQueue -> job path.
- **Recommended:** Fault injection lives in test fakes (FakeThreadStore, FakeTelegramAdapter / ingress handlers), constant counters and their assertions are deleted, recorders exist only on Fake* classes, and pipeline-order tests drive TelegramWebhookController + a FakeQueue + ProcessTelegramUpdate.
- **Why it matters:** The suite is 95%+ covered yet missed M-001..M-003 because it exercises a simulation of the pipeline, not the wired path. The unbounded recorder arrays are a memory leak in long-running workers (also holding user objects).
- **How to fix:** Step 1: delete registryInvokeCount/capabilityExecuteCount/domainServiceCalls/domainBypassAttempted and their tests. Step 2: remove $calls from HttpTelegramBotClient and $handled/$replies from TelegramAdapter (tests use FakeTelegramBotClient / an ingress handler spy). Step 3: replace runPipeline()+fail_at with tests that compose controller->FakeQueue->processor and inject failing fakes. Keep coverage >=95% by testing the real branches.

## Enhancements (optional)

### L-019 — HTTP describe exposes schemas of capabilities hidden from caller's surface
- **Severity:** low · **Effort:** trivial · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Schema/CatalogPresenter.php:89`, `packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php:79`
- **Current:** list() filters by caller surface and canDiscover, but describe() only checks canDiscover. Any authenticated HTTP user can therefore GET the full input/output JSON Schema of mcp-only or agent-only capabilities by name.
- **Recommended:** Pass the caller into describe() and return not_found when the capability is not in effectiveSurfaces for that caller, matching list().
- **Why it matters:** Discovery should be consistent (D-008/D-003): list and describe must not disagree about what a caller can see.
- **How to fix:** Change describe(string $name, mixed $actor = null, ?string $caller = null) to apply the same surface filter as list(). Pass $caller['caller'] from CapabilityController::describe. Unit test: an mcp-only capability described via caller http gives not_found.

### X-010 — Point Dependabot at the CLI's nested release workflow
- **Severity:** low · **Effort:** trivial · **Impact:** security
- **Locations:** `.github/dependabot.yml:16`, `packages/capabilities-cli/.github/workflows/release.yml`
- **Current:** The github-actions updater scans only /.github/workflows. The CLI's release workflow handles signing keys (CAPABILITIES_RELEASE_SIGNING_KEY, Apple and Windows certs) and lives under packages/capabilities-cli/.github/workflows, so it never gets update PRs in the monorepo. The split child repo has no dependabot.yml either.
- **Recommended:** dependabot.yml covers both workflow roots, so action bumps for the release pipeline arrive as monorepo PRs and flow to the child through the split.
- **Why it matters:** This is the only workflow that holds long-lived signing secrets. Stale action versions there carry the most supply-chain risk, and it is the one location the current config misses.
- **How to fix:** Change the github-actions entry to `directories: ["/", "/packages/capabilities-cli"]` (Dependabot supports `directories` for multi-root configs).

### C-006 — Emit D-018 error envelope on stdout for approvals and catalog failures
- **Severity:** low · **Effort:** trivial · **Impact:** dx, reliability
- **Locations:** `packages/capabilities-cli/cmd/capabilities/cmd_approvals.go:56`, `packages/capabilities-cli/cmd/capabilities/cmd_catalog.go:45`, `packages/capabilities-cli/cmd/capabilities/cmd_run.go:44`
- **Current:** `run`, `describe` and domain not-found write the server's error envelope to stdout. `approvals accept|reject` and `catalog` write only a stderr line on failure, so stdout is empty. The approval_id, violations and request_id from the server are lost.
- **Recommended:** Every command that received a structured error writes the envelope to stdout (se.Body when present, otherwise writeStructuredErrorStdout), as describe does.
- **Why it matters:** docs/agents.md:19 says 'Stdout is machine; stderr is human — parse stdout envelopes only'. Approval decisions are exactly where an agent needs the machine code (expired, conflict, forbidden).
- **How to fix:** Extract describe's error branch (cmd_run.go:44-52) into a helper `writeErrorEnvelope(env, se)` and call it from cmdApprovals and cmdCatalog. Add CLI tests asserting stdout JSON with ok:false on a 410 approval and a 401 catalog.

### C-008 — Reserve 'self-update' CLI domain on both sides; lists are hand-copied
- **Severity:** low · **Effort:** trivial · **Impact:** maintainability, reliability
- **Locations:** `packages/capabilities-cli/cmd/capabilities/cli.go:98`, `packages/capabilities-cli/internal/synth/synth.go:21`, `packages/laravel-capabilities/src/Registry/CapabilityDefinition.php:26`
- **Current:** `self-update` is a reserved meta-command in the CLI dispatcher but appears in neither reserved list. The core accepts cliDomain 'self-update' (it matches /^[a-z][a-z0-9-]*$/), and the CLI synth index maps it without a reserved_domain error, so the capability can never be reached by domain/verb. The dispatcher silently runs the self-updater instead.
- **Recommended:** Both lists include 'self-update', and a test on each side pins the list to one shared fixture so a new meta-command cannot be added to only one side.
- **Why it matters:** The two copies of the reserved list are what lets the catalog and CLI synthesis disagree. They have already drifted by one entry.
- **How to fix:** Add 'self-update' to RESERVED_CLI_DOMAINS and reservedDomains. Add a Go test asserting every `case` string in Execute's reserved switch that matches the token pattern is in reservedDomains. Add a small JSON fixture (e.g. docs/contracts/reserved-cli-domains.json) read by both a Pest unit and a Go test.

### L-020 — Singleton CapabilityController retains the last actor/options across requests
- **Severity:** low · **Effort:** trivial · **Impact:** reliability, maintainability
- **Locations:** `packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php:27`, `packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php:128`, `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:188`
- **Current:** The controller is a container singleton and stores the last invoke options, including the authenticated user object, purely for tests. Under Octane or other long-lived workers, the previous request's user stays referenced by the next request's controller.
- **Recommended:** Remove the mutable test hook from production code: assert invoke options through a fake CapabilityBus in unit tests, or bind the controller as scoped instead of singleton.
- **Why it matters:** 'Singleton service classes holding request state' is a Laravel anti-pattern. It is a cross-request state leak under Octane and keeps request-scoped objects alive.
- **How to fix:** Delete lastInvokeOptions/lastInvokeOptions(). Tests that read it should use a recording CapabilityBus fake. Use `$this->app->scoped(...)` for the three HTTP controllers.

### M-017 — Append the queued progress event before dispatching the turn
- **Severity:** low · **Effort:** trivial · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities-ai/src/Domain/ConversationService.php:84`
- **Current:** The queued status event is written after dispatch. With the sync queue driver (or a fast worker) the turn's running/terminal events land first and the stream ends with status=queued.
- **Recommended:** Append the queued event, then dispatch.
- **Why it matters:** Clients that read the last status event (rather than stopping at terminal) show a finished turn as queued.
- **How to fix:** Swap the two statements; unit test with a dispatch callable that appends 'running' synchronously and assert event order queued -> running.

### C-009 — Document or detect surfaces.http.prefix; CLI hardcodes /capabilities
- **Severity:** low · **Effort:** trivial · **Impact:** dx
- **Locations:** `packages/capabilities-cli/internal/api/client.go:24`, `packages/laravel-capabilities/src/Http/RouteTable.php:79`, `packages/laravel-capabilities/config/capabilities.php:81`
- **Current:** The core lets hosts rename the HTTP route prefix. The CLI always appends `/capabilities/...` to --base-url. A host with prefix `api/capabilities` works (base-url `https://host/api`), but any prefix whose last segment is not `capabilities` (e.g. `caps`, `v1/tools`) is unreachable from the product CLI. The user sees a generic 404 not_found (exit 5), and neither package documents the constraint.
- **Recommended:** Either the config comment and CLI docs state that the CLI needs a prefix ending in `capabilities` (with --base-url set to everything before it), or the CLI accepts an API root URL that already includes the prefix.
- **Why it matters:** This is a config knob that silently breaks the only first-party client. The cheapest fix is a documented constraint. Health's api_version cannot help, because the health route sits under the same prefix.
- **How to fix:** Minimal: add a comment on config 'prefix' and a line in packages/capabilities-cli/docs/authentication.md. Optional: when CheckAPIVersion/health returns a non-envelope 404, add a hint mentioning surfaces.http.prefix.

### X-009 — Fix CI/release/docs drift: 'core + messaging' labels, stale split notes
- **Severity:** low · **Effort:** trivial · **Impact:** dx
- **Locations:** `.github/workflows/tests.yml:18`, `README.md:156`, `scripts/release.sh:39`, `docs/versioning.md:57`, `docs/versioning.md:38`, `.github/workflows/split-packages.yml:13`, `.github/workflows/split-packages.yml:94`, `.gitignore:14`
- **Current:** `composer test` runs core, messaging and AI, but four places label it 'core + messaging'. versioning.md describes per-ref cancel behaviour, while the workflow uses one `split-packages` group with cancel-in-progress false for every ref. The split header says 'three' repos (the matrix has four). The rsync dist include rules and .gitignore whitelist refer to dist/README.md and .gitkeep, which were untracked in 39382de (v0.1.3). No test needs them.
- **Recommended:** Labels say 'core + messaging + AI'. versioning.md describes the single serialized group. The split header says four repos. The dead dist include rules and .gitignore negations are removed (the CLI's own .gitignore already ignores dist/).
- **Why it matters:** These are the files an operator reads while cutting a release. Wrong suite names and dead rsync rules make the next person doubt whether AI is gated or whether dist/ matters.
- **How to fix:** Edit tests.yml:18, README.md:156, scripts/release.sh:39,68,343,468,554, docs/versioning.md:38,57, split-packages.yml:13 and 94–104 (drop the three dist lines, keep `--exclude 'dist/'`), and .gitignore:13–15 (replace with nothing; packages/capabilities-cli/.gitignore covers dist/).

### C-007 — Send retry_after on pipeline rate_limited so CLI backoff hint works
- **Severity:** low · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `packages/laravel-capabilities/src/Pipeline/InvokePipeline.php:758`, `packages/laravel-capabilities/src/Contracts/RateLimiter.php:19`, `packages/capabilities-cli/internal/api/client.go:142`, `packages/capabilities-cli/internal/run/runner.go:190`
- **Current:** The CLI parses `Retry-After` and `error.retry_after` and exposes the wait on stdout (docs/agents.md:81). The core pipeline's own rate_limited envelope sends neither: HttpResponse sets only Content-Type, and the RateLimiter contract cannot report the remaining decay. The hint only reaches the CLI from Laravel's throttle middleware or a proxy.
- **Recommended:** Pipeline rate limits include `retry_after` (seconds) in the error envelope. HttpResponse::fromResult adds a `Retry-After` header on 429 when it is present.
- **Why it matters:** The CLI side of this contract exists and is tested, but the core's main rate-limit path never feeds it, so agents fall back to guessing a backoff.
- **How to fix:** Add `availableIn(string $key): int` to Contracts\RateLimiter (Illuminate adapter: RateLimiter::availableIn). Pass the max of the tripped keys into rateLimitedResult as extra['retry_after']. In HttpResponse::fromResult, when status==429 and error.retry_after>0, add header Retry-After. Unit-test both in core. This is a contract change, so note it in the changelog for host implementations.

### X-008 — Package CHANGELOGs lag the 20+ lockstep tags; core has two [Unreleased] blocks
- **Severity:** low · **Effort:** small · **Impact:** dx, maintainability
- **Locations:** `packages/laravel-capabilities/CHANGELOG.md:146`, `packages/laravel-capabilities/CHANGELOG.md:60`, `packages/laravel-capabilities-messaging/CHANGELOG.md:12`, `packages/capabilities-cli/CHANGELOG.md:12`, `packages/laravel-capabilities-ai/CHANGELOG.md:41`, `docs/versioning.md:311`
- **Current:** Tags v0.1.1–v0.5.3 are force-pushed to all four package remotes. Only core has dated sections (0.5.2, 0.5.3), and core also has a stale second `[Unreleased]` holding shipped work. Messaging and CLI have none. AI stops at 0.5.1. scripts/release.sh never touches CHANGELOGs, even though docs/versioning.md says to fill them per tag.
- **Recommended:** Each tag produces a dated section in every package CHANGELOG (or an explicit 'No changes' line), and there is exactly one `[Unreleased]` per file. release.sh refuses to tag while `[Unreleased]` is non-empty in any package, or it promotes those sections automatically.
- **Why it matters:** The package remotes are the consumer-facing products. For 0.x, where breaking changes land on minors, the CHANGELOG is the only upgrade guide. Tags without notes make X-004's pairing problem worse.
- **How to fix:** Add a release.sh step: for each packages/*/CHANGELOG.md, replace the first `## [Unreleased]` with `## [Unreleased]\n\n## [X.Y.Z] - $(date +%F)` and fail if a file has more than one `## [Unreleased]`. Fix the core file by merging lines 146+ into the correct released section.

### X-011 — Reconcile requirements inventory: stale CLI mcp rows, AI package absent
- **Severity:** low · **Effort:** small · **Impact:** maintainability
- **Locations:** `docs/requirements-inventory.md:5588`, `docs/requirements-inventory.md:5714`, `tools/generate_requirement_stubs.py:2955`, `tools/report_inventory_gaps.py:46`
- **Current:** `python3 tools/report_inventory_gaps.py` reports 2 unmatched rows. Both are `mcp` auth-guard scenarios for a CLI command that was hard-removed (help.go:164), yet the inventory marks them [x]. The only live test is `TestMcpwithoutauthfails`. The AI package (≈4.5k LOC, 311 tests) has no inventory rows, and the sync and gap tools don't scan it, even though AGENTS.md treats inventory→tests as the contract scaffold.
- **Recommended:** The generator catalog drops or renames the two mcp rows. The gap report runs clean and is cheap enough to run in CI. AGENTS.md either states that the AI package is governed by its own tests and README instead of the inventory, or the tools gain an `ai` package entry.
- **Why it matters:** A gap tool that always reports stale noise stops being read. Checked boxes for tests that don't exist undermine the 'tests are SOT' contract. The tools' own unit tests (31, green) are also not in CI.
- **How to fix:** Remove lines 1581 and 2955 from tools/generate_requirement_stubs.py (or map them to TestMcpwithoutauthfails), regenerate, and re-run sync. Add a tests.yml job: `python3 -m unittest discover -s tools/tests && python3 tools/report_inventory_gaps.py --fail-on-gaps` (add the flag). Add one AGENTS.md line on AI package inventory scope.

## Suggested Sequencing

1. **Stabilize** — work through Critical, then High. Start with L-001 → L-002 (they gate the approval items L-005, L-012, L-013, L-014), and M-001 → M-002 → M-003 for Telegram. Reason: prevents incidents, unblocks confidence.
2. **Sweep Quick Wins** — batch the small-effort items. Reason: high ROI, often a single PR can clear several (e.g. CI/tooling X-002/X-004/X-006/X-007; CLI wire C-002/C-003/C-005; core config L-008/L-011/L-018).
3. **Plan Medium + Enhancements** — schedule into next quarter; treat as tech-debt allocation.

## Appendix

- Auditors run: control-plane-auditor, cross-cutting-auditor, laravel-auditor, laravel-auditor-siblings
- Files scanned per auditor:

| File | Auditor | Files scanned | Findings |
|---|---|---|---|
| findings-control-plane.json | control-plane-auditor | 38 | 9 |
| findings-cross-cutting.json | cross-cutting-auditor | 64 | 11 |
| findings-laravel-core.json | laravel-auditor | 64 | 20 |
| findings-laravel-siblings.json | laravel-auditor-siblings | 64 | 18 |

- Findings dropped (malformed or unverified): 0
- Severity downgrades (no locations): 0 — every finding carries locations
- Dedupe merges (same category + shared file + ≥80% title-token Jaccard): none. Highest title overlap of any pair was C-006/M-014 at 0.29 (different categories, no shared file). Related but distinct, kept separate: L-001/L-002 (class authorize() vs definition-level approval), L-005/M-007 (actor type on approval execution vs chat identity), L-003/M-010 (caller derivation, HTTP vs AI turns), C-006/M-014 (D-018 envelope, CLI vs AI ChatController), L-018/M-013 (throttling, auth routes vs AI chat).
- Ranking tie-break inside a score: impact (security > reliability > performance > scalability > maintainability > dx), then id.
- Warnings:
  - findings-laravel-siblings.json uses non-standard prefix M- (auditor "laravel-auditor-siblings"); kept as-is by orchestrator instruction.
  - Mode not supplied by orchestrator; recorded as "audit".
  - Coverage numbers in X-001 were measured on PHP 8.5 + pcov locally; CI runs PHP 8.2 (auditor note).
