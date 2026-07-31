# Architecture Audit — laravel-capabilities-monrepo

**Generated:** 2026-07-31T08:42:28Z
**Stack:** Laravel package monorepo (illuminate 11–13, PHP 8.2+) + Go CLI; Pest unit tests; GitHub Actions package-split; no frontend
**Mode:** both

## Summary

- **30** total findings
- **2** critical, **10** high, **14** medium, **4** low
- Estimated total effort: 13 trivial, 10 small, 7 medium, 0 large
- **12** quick wins

## Critical (fix now)

### L-001 — HTTP controllers lack Illuminate Request/Response bridge
- **Severity:** critical · **Effort:** medium · **Impact:** reliability, security, dx
- **Locations:** `packages/laravel-capabilities/src/Adapters/Http/CapabilityController.php:44`, `packages/laravel-capabilities/src/Http/HttpRequestContext.php:8`, `packages/laravel-capabilities/src/CapabilitiesServiceProvider.php:300`, `packages/laravel-capabilities/src/Http/HttpResponse.php:11`
- **Current:** Routes are registered as CapabilityController@list (etc.) onto the Illuminate router, but actions accept only HttpRequestContext and return package HttpResponse DTOs. There is no fromRequest/toResponse adapter, no Responsable implementation, and no factory that copies Sanctum user, headers, JSON body, or credential abilities into HttpRequestContext.
- **Recommended:** Ship an edge adapter (e.g. HttpRequestContext::fromIlluminate(Request) + HttpResponse implementing Responsable or toIlluminate()) and either invokable controllers that take Request or a thin Laravel controller that maps in/out. Ensure credential/user/authKind come from middleware-resolved auth, never client claims.
- **Why it matters:** Without a bridge, container resolution builds a default unauthenticated HttpRequestContext (all constructor defaults), so catalog/invoke either always 401 or never see the real actor. Returning a non-Response DTO also breaks the HTTP kernel. This is a concrete prod-break for the single HTTP/CLI API (D-009) despite extensive unit coverage of pure controllers.
- **How to fix:** Add packages/laravel-capabilities/src/Http/IlluminateHttpBridge.php; bind controllers that accept Request, map to HttpRequestContext (user, token abilities → credential, headers, jsonBody, authKind), call existing methods, return response()->json($http->body, $http->status)->withHeaders($http->headers). Unit-test the mapper with array fixtures (still no Feature suite).

### L-004 — Messaging provider never binds services; webhook uses FakeQueue
- **Severity:** critical · **Effort:** medium · **Impact:** reliability, scalability
- **Locations:** `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:20`, `packages/laravel-capabilities-messaging/src/Telegram/TelegramWebhookController.php:26`, `packages/laravel-capabilities-messaging/src/Boot/MessagingBindings.php:46`, `packages/laravel-capabilities-messaging/routes/messaging.php:11`
- **Current:** MessagingServiceProvider only merges config and optionally loads routes. It never registers MessagingBindings singletons. TelegramWebhookController defaults queue to FakeQueue (in-memory list). MessagingBindings::build always constructs FakeTelegramBotClient + FakeQueue even as the pure 'production plan' path.
- **Recommended:** register() should bind MessagingConfig from config repository, a real LaravelQueue adapter implementing UpdateQueue, real TelegramBotClient (HTTP), durable IdentityLinker/ThreadStore, and ProcessTelegramUpdate. Webhook must not default to FakeQueue outside testing.
- **Why it matters:** With telegram enabled (config default env CAPABILITIES_TELEGRAM true), routes accept webhooks, verify secrets, and 'queue' updates into a process-local FakeQueue that no worker drains. Outbound bot calls would also be fakes if bindings were used as-is. This is a production-break for the messaging surface.
- **How to fix:** Add Support/LaravelUpdateQueue using Bus::dispatch(ProcessTelegramUpdateJob); bind in MessagingServiceProvider::register from MessagingBindings::production($app); inject Fake* only when app.env is testing or via config driver=fake.

## High Priority (this sprint)

### X-003 — Run go test before CLI GoReleaser release
- **Severity:** high · **Effort:** trivial · **Impact:** reliability
- **Locations:** `packages/capabilities-cli/.github/workflows/release.yml:82`, `packages/capabilities-cli/.github/workflows/release.yml:29`
- **Current:** After monorepo tag mirror, the package-owned release workflow checks out, sets up Go, optionally notes signing secrets, and immediately runs GoReleaser. It never runs `go test ./...`.
- **Recommended:** Add a step `go test ./...` (with race optional) before GoReleaser; fail the job if tests fail so no broken multi-arch binaries are published.
- **Why it matters:** CLI GitHub Releases are the documented install path for end users. Releasing without unit tests undoes the monorepo’s strong unit-test policy at the only binary ship boundary.
- **How to fix:** In release.yml after setup-go: `working-directory` root, `run: go test ./...`. Prefer `go-version-file: go.mod` for alignment with module Go 1.22.

### X-004 — Add MIT LICENSE files to monorepo and split packages
- **Severity:** high · **Effort:** trivial · **Impact:** maintainability, dx
- **Locations:** `composer.json:5`, `packages/laravel-capabilities/composer.json:5`, `packages/laravel-capabilities-messaging/composer.json:5`, `packages/capabilities-cli/go.mod:1`
- **Current:** All PHP composer.json files declare `"license": "MIT"`, but no `LICENSE` / `LICENSE.md` file exists at monorepo root or under any package tree (`git ls-files` has none). After split, public package repos will also lack a LICENSE file. Go module has no SPDX file either.
- **Recommended:** Add a standard MIT LICENSE file at monorepo root and copy/symlink the same into each of `packages/laravel-capabilities`, `packages/laravel-capabilities-messaging`, and `packages/capabilities-cli` so split remotes ship a clear license grant.
- **Why it matters:** Packagist, GitHub license detection, and many corporate OSS policies require a LICENSE file, not only a composer.json field. Missing file blocks or confuses adoption of intended public packages.
- **How to fix:** Use the same MIT text with copyright holder `rawphp` (or org legal name) in four paths; ensure split rsync includes LICENSE. Reference it from each package README footer.

### L-005 — DatabaseApprovalStore generates sequential process-local IDs
- **Severity:** high · **Effort:** trivial · **Impact:** reliability, security
- **Locations:** `packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php:129`, `packages/laravel-capabilities/src/Persistence/QueryTableGateway.php:336`
- **Current:** When put() omits id, DatabaseApprovalStore assigns approval-{n} from an in-memory sequence counter. QueryTableGateway already generates cryptographically random hex IDs when id is absent at insert time, but the store always pre-assigns sequential ids first.
- **Recommended:** Use random/UUID ids for durable approvals (delegate to gateway or bin2hex(random_bytes(16)) / Str::ulid()). Keep sequential ids only in InMemoryApprovalStore for unit readability.
- **Why it matters:** Multi-worker PHP-FPM/queue processes each restart sequence at 1 → primary-key collisions or overwrites. Predictable approval IDs also weaken URL/callback unguessability if accept routes leak.
- **How to fix:** In DatabaseApprovalStore::put, omit id and let QueryTableGateway::resolveId mint random ids, or call random_bytes like IdempotencyKey. Add unit test for uniqueness across two store instances.

### X-001 — Add monorepo CI that runs package unit tests
- **Severity:** high · **Effort:** small · **Impact:** reliability, dx, maintainability
- **Locations:** `.github/workflows/split-packages.yml:17`, `composer.json:45`, `AGENTS.md:75`
- **Current:** The only monorepo GitHub Actions workflow is package split/mirror. There is no workflow that runs `composer test`, `composer test:cli`, tools Python tests, PHPStan, or coverage. AGENTS.md and root README document local unit suites as the contract, but CI does not execute them.
- **Recommended:** Add a `tests.yml` (or matrix job) on PR + push to main that installs PHP deps, runs Pest for core and messaging, runs Go tests for the CLI, and fails the job on non-zero exit. Optionally run `python3 -m unittest` under `tools/tests/`.
- **Why it matters:** Hundreds of unit tests exist in-package (222 core, 32 messaging, 41 Go) but nothing stops a green local machine or a bad push from becoming the source of truth. For a monorepo that auto-mirrors to public package remotes, missing test CI is a primary reliability gap.
- **How to fix:** Create `.github/workflows/tests.yml` with jobs: (1) php: setup-php 8.2 + pcov, `composer install`, `composer test:core`, `composer test:messaging`; (2) go: setup-go from go.mod, `go test ./...` in packages/capabilities-cli; gate split on `needs: [php, go]` or require branch protection requiring the tests check.

### X-002 — Gate package split/publish on green tests
- **Severity:** high · **Effort:** small · **Impact:** reliability, security
- **Locations:** `.github/workflows/split-packages.yml:19`, `.github/workflows/split-packages.yml:32`, `docs/versioning.md:19`
- **Current:** `split-packages.yml` runs on every push to `main` and every `v*` tag and force-pushes package trees (and tags) to rawphp/laravel-capabilities, messaging, and capabilities-cli with no prior test job. Broken or untested code can land on consumer-facing remotes and Packagist-bound tags immediately.
- **Recommended:** Either make split a dependent job that `needs:` a tests workflow, or use a single workflow with `test` then `split`, and require the tests status check on `main` via branch protection. Tags that fail tests must not mirror.
- **Why it matters:** Ship model is monorepo → public package remotes on push. Without a quality gate, the split is a deploy path, not just a mirror. Severity is high because consumers install from those remotes (VCS/dev-main today).
- **How to fix:** Refactor into `jobs: test: …` then `split: needs: [test]`; for tags, fail closed if tests fail so `--force` tag push never runs. Document in docs/versioning.md Packagist checklist.

### L-003 — Registry defaults to allow-all when capability has no authorize()
- **Severity:** high · **Effort:** small · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:245`, `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:1534`, `packages/laravel-capabilities/src/Boot/ContainerBindings.php:235`
- **Current:** stageAuthorize uses the capability's authorize callable when present; otherwise it calls the injected Authorizer. Construction defaults to StubAuthorizer::allow(). makeRegistry / ServiceProvider never bind a production Authorizer or force per-capability authorize.
- **Recommended:** Fail closed: default StubAuthorizer::deny() (or a DenyUnlessAuthorizeCallable authorizer) for mutating capabilities without authorize; require explicit withAuthorizer / host GateAuthorizer in ContainerBindings plan; document that authorize() is mandatory for non-readOnly definitions.
- **Why it matters:** Governance is part of every surface (AGENTS.md). A host that registers capabilities with only run() gets silent allow for any authenticated actor on HTTP/agent/MCP/job. That is fail-open relative to package security claims.
- **How to fix:** Change default to StubAuthorizer::deny(); in CapabilityDefinitionBuilder::register, reject mutating defs without authorize; add unit test that registry from makeRegistry denies when authorize missing.

### L-008 — Rate limiting is process-local InMemoryRateLimiter only
- **Severity:** high · **Effort:** small · **Impact:** scalability, security
- **Locations:** `packages/laravel-capabilities/src/Registry/CapabilityRegistry.php:246`, `packages/laravel-capabilities/src/Support/InMemoryRateLimiter.php:8`, `packages/laravel-capabilities/src/Boot/ContainerBindings.php:256`
- **Current:** Config enables rate_limits (60/min defaults) but makeRegistry only applies config numbers; the limiter concrete remains InMemoryRateLimiter. Contracts/RateLimiter.php notes production may wrap Laravel's RateLimiter, but no binding exists.
- **Recommended:** Bind a LaravelRateLimiter adapter (Cache/Redis) from config when rate_limits.enabled; keep InMemory only for unit tests. Document that multi-worker hosts must use shared store.
- **Why it matters:** Agent turn budgets and per-capability limits are security-relevant for runaway tool loops (D-008 related). Process-local counters give a false sense of protection under FPM/queue scale-out.
- **How to fix:** Implement Support/LaravelCacheRateLimiter using Illuminate\Cache\RateLimiter; bind in ServiceProvider from rate_limits.driver; unit-test via fake Cache store interface.

### X-005 — Enforce ≥95% coverage in CI (policy is unenforced)
- **Severity:** high · **Effort:** medium · **Impact:** maintainability, reliability, dx
- **Locations:** `AGENTS.md:79`, `packages/laravel-capabilities/phpunit.xml:13`, `packages/laravel-capabilities-messaging/phpunit.xml:13`, `composer.json:45`
- **Current:** AGENTS.md makes ≥95% line coverage blocking. phpunit.xml includes source dirs for coverage but has no coverage report config, composer scripts do not pass `--coverage-text` / min thresholds, no pcov/xdebug is required in require-dev, and no CI job measures coverage. Coverage is only approached via ad-hoc CoverageBoost tests.
- **Recommended:** CI runs Pest with PCOV and fails if coverage for each package `src/` is below 95%. Go uses `go test -coverprofile` with a minimum check. Document exact commands next to AGENTS.md policy.
- **Why it matters:** Policy without automation will drift: new code can ship under 95% while still greening `composer test`. Monorepo explicitly treats coverage floor as non-negotiable.
- **How to fix:** e.g. `pest --configuration=packages/laravel-capabilities/phpunit.xml --coverage --min=95` with php-actions/setup-php coverage: pcov; for Go: `go test ./... -coverprofile=c.out` + `go tool cover -func` threshold script.

### L-002 — AuthController issues placeholder tokens from client body
- **Severity:** high · **Effort:** medium · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Adapters/Http/AuthController.php:68`, `packages/laravel-capabilities/src/Adapters/Http/AuthController.php:77`, `packages/laravel-capabilities/src/Http/RouteTable.php:85`
- **Current:** AuthController is a contract stub: token() echoes client-supplied access_token or 'issued-by-host', device() returns fixed placeholders, oauthCallback() echoes query code. These routes are registered in RouteTable when HTTP is enabled (default middleware includes auth:sanctum).
- **Recommended:** Either do not register auth routes until a real host AuthIssuer is bound, or implement host-delegated issuance behind explicit interfaces (Sanctum/Passport/OAuth device flow) and never accept client-supplied access_token. Fail closed with 501/not_configured when unbound.
- **Why it matters:** Registering stub token endpoints on the product capability API creates a footgun: if middleware is relaxed for CLI bootstrap, clients can obtain a fake bearer string that may confuse downstream gateways. Even with Sanctum, oauth/device routes are not a safe chicken-egg design for CLI login.
- **How to fix:** Introduce AuthTokenIssuer interface; AuthController returns not_found unless bound. Split middleware: auth flows without auth:sanctum, catalog/invoke with it. Mirror shape used by CLI internal/auth package expectations.

### L-006 — Messaging identity and threads are memory-only with empty migrations
- **Severity:** high · **Effort:** medium · **Impact:** reliability, scalability
- **Locations:** `packages/laravel-capabilities-messaging/src/Identity/IdentityLinker.php:17`, `packages/laravel-capabilities-messaging/src/Threads/ThreadStore.php:8`, `packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php:33`
- **Current:** IdentityLinker and ThreadStore hold links/codes/history in private arrays. database/migrations is published but empty. No production store implementation is registered.
- **Recommended:** Add durable drivers (DB tables for identity links, link codes, threads) behind the same interfaces; publish real migrations; bind database drivers when not testing. Keep in-memory implementations for unit tests only.
- **Why it matters:** Multi-process webhooks lose link state between requests; link codes issued on one worker are invisible to another. Conversation continuity and identity (MSG pipeline resolve_identity) cannot work under normal Laravel deploy topology.
- **How to fix:** Migrations for messaging_identity_links / messaging_link_codes / messaging_threads; DatabaseIdentityLinker implementing ConversationIdentity; unit-test against ArrayTableGateway pattern from core package.

## Quick Wins (high impact / low effort)

### X-006 — Stop gitignoring monorepo composer.lock
- **Severity:** medium · **Effort:** trivial · **Impact:** dx, reliability
- **Locations:** `.gitignore:2`, `composer.json:3`
- **Current:** Root `.gitignore` excludes `/composer.lock`. The lockfile exists locally (composer.lock present) but is not tracked. Package libraries correctly omit their own locks; the monorepo root is a `type: project` workspace used for shared Pest/PHPStan and path packages.
- **Recommended:** Commit root `composer.lock` (remove ignore line) so contributor and CI installs resolve the same illuminate/pest/phpstan graph. Keep package trees lock-free for library publish.
- **Why it matters:** Ignoring the monorepo lock produces non-reproducible CI and 'works on my machine' drift across Illuminate 11/12 minors while developing path packages.
- **How to fix:** Delete `/composer.lock` from `.gitignore`, `composer update` once on PHP 8.2, commit lock. CI should `composer install --prefer-dist` not `update`.

### X-009 — Add Dependabot or Renovate for Composer and Go modules
- **Severity:** medium · **Effort:** trivial · **Impact:** security, maintainability
- **Locations:** `.github/workflows/split-packages.yml:1`, `packages/capabilities-cli/go.mod:5`
- **Current:** No `.github/dependabot.yml` or Renovate config. PHP and Go dependency bumps are fully manual. Actions used (`actions/checkout@v4`, `setup-go@v5`, `goreleaser-action@v6`) also have no automated update path.
- **Recommended:** Enable Dependabot for composer (root + packages if published separately), gomod for capabilities-cli, and github-actions ecosystem on the monorepo (and after split, on package remotes if they diverge).
- **Why it matters:** Public packages and a token-holding split workflow need timely Action and library updates; manual-only hygiene will lag.
- **How to fix:** Add `.github/dependabot.yml` with weekly composer, gomod (`/packages/capabilities-cli`), and github-actions entries; group minor/patch if noise is high.

### X-010 — Add SECURITY.md for public package vulnerability reporting
- **Severity:** medium · **Effort:** trivial · **Impact:** security, dx
- **Locations:** `README.md:15`, `packages/laravel-capabilities/README.md:1`
- **Current:** No SECURITY.md (or security contact) in monorepo or any package. Packages are intended for public remotes and handle authz, approvals, Telegram secrets, CLI tokens — high-value security surface once published.
- **Recommended:** Add SECURITY.md at monorepo root and in each package tree (ships via split) with private reporting channel and supported versions (0.x policy).
- **Why it matters:** GitHub advisory UI and security-conscious consumers expect a documented disclosure path before Packagist listing.
- **How to fix:** Standard GitHub SECURITY.md template; private email or GitHub Security Advisories for rawphp org; mirror into packages/* so split remotes inherit it.

### X-012 — Pin Go version in CLI release workflow to go.mod
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability
- **Locations:** `packages/capabilities-cli/.github/workflows/release.yml:40`, `packages/capabilities-cli/go.mod:3`
- **Current:** Release builds with `go-version: stable` (floating latest stable toolchain) while the module declares `go 1.22`. Binary artifacts can silently change language/runtime behavior across releases without a go.mod bump.
- **Recommended:** Use `go-version-file: go.mod` (or an explicit `1.22.x`) so releases match the module’s supported toolchain.
- **Why it matters:** Reproducible multi-arch releases and fewer 'works on CI stable, fails on older go' surprises for source builders.
- **How to fix:** In setup-go step: `go-version-file: go.mod` and drop `go-version: stable`.

### X-013 — Avoid cancel-in-progress for tag split concurrency
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability
- **Locations:** `.github/workflows/split-packages.yml:25`, `.github/workflows/split-packages.yml:112`
- **Current:** Concurrency groups by ref with `cancel-in-progress: true`. A rapid re-push of the same tag or overlapping workflow_dispatch can cancel an in-flight rsync + force-tag push mid-matrix, leaving package remotes partially updated across the three packages (fail-fast is false but cancel aborts jobs).
- **Recommended:** Keep cancel-in-progress for branch `main` if desired, but set `cancel-in-progress: false` for tags (or separate concurrency groups) so release mirrors complete atomically per package matrix.
- **Why it matters:** Tag mirror is the Packagist/version boundary; partial three-way sync is worse than queued sequential runs.
- **How to fix:** `cancel-in-progress: ${{ github.ref_type != 'tag' }}` or two workflows.

### L-009 — Idempotency defaults to memory while approvals default database
- **Severity:** medium · **Effort:** trivial · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/config/capabilities.php:110`, `packages/laravel-capabilities/config/capabilities.php:135`
- **Current:** Published defaults: approval.store=database (needs connection + migrations) and idempotency.driver=memory. Comments acknowledge host must rebind, but mismatch means multi-worker mutating invokes re-run despite Idempotency-Key while approvals may persist.
- **Recommended:** Align production defaults: both database when package is used beyond unit tests, or both memory with loud docs/health warning when memory is active under non-local env. Surface driver status in catalog health.
- **Why it matters:** D-005 requires durable outcomes for mutating invokes. Default memory store silently fails that guarantee in any multi-process deploy while approval durability suggests production orientation.
- **How to fix:** Default CAPABILITIES_IDEMPOTENCY_DRIVER to database when approval store is database; or BootGuard warning when APP_ENV=production and driver=memory.

### L-013 — ApprovalPolicy any_staff treats every non-system user as staff
- **Severity:** medium · **Effort:** trivial · **Impact:** security
- **Locations:** `packages/laravel-capabilities/src/Approval/ApprovalPolicy.php:124`
- **Current:** When policy is any_staff and no staffChecker is injected, every non-SystemActor is treated as staff (tenant match still applies). custom without checker also falls through to actorIsStaff.
- **Recommended:** Default deny when staffChecker missing; require host to bind staffChecker or use is_staff/roles. Keep permissive behaviour only behind explicit testing helper.
- **Why it matters:** Hosts selecting any_staff from config without wiring staffChecker get silent open approval within tenant — contrary to multi-tenant safe defaults celebrated elsewhere in the same class.
- **How to fix:** return false when no checker and no is_staff; unit test any_staff without checker denies ordinary user.

### X-007 — Align Illuminate version constraints across packages
- **Severity:** medium · **Effort:** small · **Impact:** maintainability, dx
- **Locations:** `packages/laravel-capabilities/composer.json:20`, `packages/laravel-capabilities-messaging/composer.json:21`, `composer.json:8`
- **Current:** Core advertises Laravel 11–13 illuminate components; messaging and monorepo root only allow 11–12. Messaging can refuse a host that core accepts, and monorepo dev cannot exercise the core ^13 constraint.
- **Recommended:** Single documented support matrix (e.g. ^11|^12 or ^11|^12|^13) applied identically to core, messaging, and monorepo root require lines.
- **Why it matters:** Sibling packages that always ship together must not claim divergent host support; consumers will hit confusing Composer solver failures on Laravel 13.
- **How to fix:** Either drop `|^13.0` from core until tested, or add `|^13.0` to messaging + root and note matrix in package READMEs / docs/versioning.md.

### X-011 — Add .gitattributes export-ignore for Composer package trees
- **Severity:** medium · **Effort:** small · **Impact:** dx, maintainability
- **Locations:** `.gitignore:1`, `packages/laravel-capabilities/composer.json:2`
- **Current:** No `.gitattributes` anywhere. When packages are installed from source/dist archives, tests, coverage boosters, phpunit.xml, and possibly .github can bloat installs unless export-ignore is set on each package remote.
- **Recommended:** Per-package `.gitattributes` with `export-ignore` for `/tests`, `/phpunit.xml`, `/.github` (except when release workflow must live in repo — keep workflows, ignore test noise), `/.phpunit.cache`, etc.
- **Why it matters:** Library packaging hygiene; reduces consumer install size and accidental reliance on test-only files. Split remotes need the file inside each package path.
- **How to fix:** Place `packages/laravel-capabilities/.gitattributes` (and messaging) with lines like `/tests export-ignore`. CLI may export-ignore `*_test.go` only if desired — often Go keeps tests in module.

### L-007 — Approval claimLease is check-then-act without lease condition
- **Severity:** medium · **Effort:** small · **Impact:** reliability
- **Locations:** `packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php:89`, `packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php:118`
- **Current:** claimLease reads the row, evaluates execution_lease_until in PHP, then updateWhere only on id+status. Two concurrent accept/resume workers can both pass the lease check before either writes.
- **Recommended:** Atomic claim: single UPDATE ... WHERE id=? AND status=? AND (execution_lease_until IS NULL OR execution_lease_until < ?) and require affected rows = 1. Extend TableGateway if needed for SQL-side predicates.
- **Why it matters:** D-006 requires exactly-once execution and crash recovery. compareAndUpdate helps status races, but lease claiming remains TOCTOU under concurrency — dual run() of the same approved capability is possible.
- **How to fix:** Add TableGateway::updateWhereExpr or claimLease SQL on QueryTableGateway; unit-test with two sequential claims where second must fail while lease held (ArrayTableGateway can simulate with locking flag).

### L-010 — CI only splits packages; no package test/coverage gate
- **Severity:** medium · **Effort:** small · **Impact:** reliability, dx
- **Locations:** `.github/workflows/split-packages.yml:17`, `composer.json:45`, `packages/laravel-capabilities/phpunit.xml:13`
- **Current:** Only split-packages.yml exists under .github/workflows. Local composer scripts run Pest unit suites; phpunit.xml has no coverage threshold despite AGENTS.md ≥95% blocking policy. phpstan is require-dev without neon or CI job.
- **Recommended:** Add a unit-test workflow (composer test + go test, coverage report with fail under 95%) on PR/main, before or blocking split. Add phpstan.neon.dist and a static-analysis job. Keep unit-only (no Feature).
- **Why it matters:** Package repos are mirrored on every main push; without CI gates, broken unit contracts can ship to public package remotes. Project policy already declares coverage as blocking — tooling does not enforce it.
- **How to fix:** Workflow: setup-php with pcov, composer test:core/messaging, pest --coverage --min=95; go test -cover ./... in capabilities-cli.

### L-012 — Auth HTTP routes share sanctum middleware with token issuance
- **Severity:** medium · **Effort:** small · **Impact:** security, dx
- **Locations:** `packages/laravel-capabilities/src/Http/RouteTable.php:77`, `packages/laravel-capabilities/config/capabilities.php:64`, `packages/laravel-capabilities/src/Http/HttpAuthGate.php:27`
- **Current:** All capability routes share one middleware list from surfaces.http.middleware. Auth token/device/oauth callback routes are also listed in HttpAuthGate::PROTECTED requiring authenticated presentation.
- **Recommended:** Per-route middleware: public or guest middleware for token/device/oauth callback (with CSRF/state where needed); auth:sanctum for list/describe/invoke/approvals. Gate should not treat auth issuance as already-authenticated.
- **Why it matters:** CLI/device OAuth cannot bootstrap if issuance endpoints require the token they issue. Homogeneous middleware is a structural block for D-009 CLI client flows.
- **How to fix:** Extend RouteTable route defs with middleware_override; HttpRouteRegistrar merges group + per-route middleware.

## Medium Priority

### X-008 — Wire PHPStan (config + CI); cover messaging package
- **Severity:** medium · **Effort:** medium · **Impact:** maintainability, dx
- **Locations:** `composer.json:14`, `packages/laravel-capabilities/composer.json:31`, `packages/laravel-capabilities-messaging/composer.json:27`, `AGENTS.md:162`
- **Current:** phpstan/phpstan is in monorepo and core require-dev, but there is no `phpstan.neon` / `phpstan.neon.dist` anywhere outside vendor, no composer script, and no CI job. Messaging has no phpstan dep. AGENTS.md defers 'PHPStan max … when CI lands' — residual still open.
- **Recommended:** Add package-level (or monorepo paths) phpstan.neon at max level for both PHP packages, composer scripts `analyse` / `analyse:core` / `analyse:messaging`, and a CI job. Messaging should require-dev phpstan like core.
- **Why it matters:** Static analysis is declared as a quality bar for package code but is inert today; install cost without enforcement.
- **How to fix:** phpstan.neon with paths packages/*/src, level max; baseline file if needed; `"analyse": "phpstan analyse"` in root composer.json; add to tests workflow.

### L-011 — CoverageGreen boost suites risk gaming the 95% floor
- **Severity:** medium · **Effort:** medium · **Impact:** maintainability, dx
- **Locations:** `packages/laravel-capabilities/tests/Unit/CoverageGreen/CoverageBoostTest.php:6`, `packages/laravel-capabilities/tests/Unit/CoverageGreen/CoverageBoost2Test.php:1`
- **Current:** Four large CoverageBoost* files (~2.2k lines) exist specifically to raise line coverage. AGENTS.md forbids gaming coverage with weak asserts or shipping dead code for %.
- **Recommended:** Fold high-value asserts into domain-named suites (pipeline, HTTP, approval); delete pure line-chasing cases; prefer mutation-resistant asserts. Keep coverage ≥95% via real scenarios from requirements-inventory.
- **Why it matters:** Line coverage with bulk boost files can mask untested behaviour while satisfying the numeric gate, undermining the monorepo's 'tests define the product' contract.
- **How to fix:** Run pest --coverage and inventory which boost cases only touch getters; migrate residual paths into existing Unit/* matrices with behavioural expects.

## Enhancements (optional)

### X-014 — Document messaging env vars in a package .env.example
- **Severity:** low · **Effort:** trivial · **Impact:** dx, security
- **Locations:** `.gitignore:20`, `packages/laravel-capabilities-messaging/config/capabilities-messaging.php:10`
- **Current:** `.env` is correctly gitignored and `!.env.example` is allowed, but no `.env.example` exists anywhere. Telegram tokens/webhook secrets and host capability env keys are only discoverable by reading config PHP or user-guide prose.
- **Recommended:** Add `packages/laravel-capabilities-messaging/.env.example` (and optionally core) listing keys with empty/placeholder values, no real secrets.
- **Why it matters:** Host integrators need a checklist of required secrets; empty examples reduce misconfiguration of webhook security. Not critical because this is a library, not a deployable app.
- **How to fix:** TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
CAPABILITIES_MESSAGING_AGENT_PROFILE=support
# never set CAPABILITIES_SKIP_BOOT_CHECKS in production

### X-015 — Add CONTRIBUTING.md pointing at monorepo test commands
- **Severity:** low · **Effort:** trivial · **Impact:** dx
- **Locations:** `README.md:81`, `AGENTS.md:1`
- **Current:** Strong README, docs/, and AGENTS.md exist, but there is no CONTRIBUTING.md for human contributors covering PR expectations, unit-only policy, package split implications, and required local checks.
- **Recommended:** Short CONTRIBUTING.md at monorepo root (and a one-liner in package READMEs linking to monorepo CONTRIBUTING for source development).
- **Why it matters:** Open-source multi-package intent benefits from a human entrypoint that is not agent-policy prose.
- **How to fix:** Cover: fork/clone, composer install, go test, unit-only rule, no feature/DB tests, do not commit secrets, PR must keep package docs self-contained after split.

### X-017 — Fail closed early when SPLIT_GITHUB_TOKEN is missing
- **Severity:** low · **Effort:** trivial · **Impact:** dx, reliability
- **Locations:** `.github/workflows/split-packages.yml:54`, `.github/workflows/split-packages.yml:70`
- **Current:** Workflow documents the secret in comments but does not assert non-empty `SPLIT_TOKEN` before clone/push. Empty secret yields opaque git auth failures mid-job after rsync work.
- **Recommended:** First run step: if `SPLIT_TOKEN` empty, echo clear setup instructions and `exit 1`.
- **Why it matters:** Operator DX for monorepo setup residual; reduces time-to-diagnose misconfigured forks/mirrors.
- **How to fix:** After `set -euo pipefail`: `if [[ -z "${SPLIT_TOKEN:-}" ]]; then echo 'Set repo secret SPLIT_GITHUB_TOKEN'; exit 1; fi`

### X-016 — Add Laravel Pint (or document deliberate absence)
- **Severity:** low · **Effort:** small · **Impact:** dx, maintainability
- **Locations:** `composer.json:12`
- **Current:** No first-party Pint config or script. laravel/pint appears only transitively in the local lock via other packages, not as a project tool. No formatter CI.
- **Recommended:** Require-dev `laravel/pint`, add `pint.json` if needed, composer script `format`, and optional CI `--test` mode — or explicitly document in AGENTS.md that formatting is editor-only.
- **Why it matters:** Laravel package ecosystem norm; reduces style noise in large core package (151 PHP src files). Low severity pre-Packagist.
- **How to fix:** `composer require --dev laravel/pint` at monorepo; `"format": "pint"`, `"format:check": "pint --test"` on packages/*/src.

## Suggested Sequencing

A 3-step roadmap derived from the buckets:

1. **Stabilize** — work through Critical, then High. Reason: prevents incidents, unblocks confidence.
   - First: HTTP Illuminate bridge (L-001) and messaging production bindings (L-004) — both are concrete prod-breaks for enabled surfaces.
   - Then: approval ID randomness (L-005), deny-by-default authorizer (L-003), shared rate limiter (L-008), auth stub footguns (L-002/L-012), messaging durable identity (L-006).
   - Parallel track: CI that runs tests + gates split (X-001/X-002), LICENSE (X-004), CLI release tests (X-003), coverage enforcement (X-005).
2. **Sweep Quick Wins** — batch the small-effort items. Reason: high ROI, often a single PR can clear several.
   - Security defaults: ApprovalPolicy staff fail-closed (L-013), idempotency default alignment (L-009).
   - Hygiene: commit composer.lock (X-006), Dependabot (X-009), SECURITY.md (X-010), pin Go version / cancel-in-progress for tags (X-012/X-013).
   - Reliability: atomic claimLease (L-007), Illuminate constraint alignment (X-007), .gitattributes export-ignore (X-011).
3. **Plan Medium + Enhancements** — schedule into next quarter; treat as tech-debt allocation.
   - Refactor CoverageBoost suites into behavioural tests (L-011); PHPStan + messaging analyse (X-008).
   - Optional: .env.example, CONTRIBUTING.md, Pint, SPLIT_TOKEN early fail (X-014–X-017).

## Appendix

- Auditors run: cross-cutting-auditor, laravel-auditor
- Files scanned per auditor:

| Auditor | Scanned files |
|---|---|
| cross-cutting-auditor | 52 |
| laravel-auditor | 184 |

- Findings dropped (malformed or unverified): 0
- Warnings: none
- Auditor notes (verbatim excerpts):
  - laravel-auditor: Monorepo of Laravel packages + Go CLI; unit-only policy respected; CallerDeriver / ApprovalController / Telegram secret / QueryTableGateway positives noted; CoverageGreen not behavioural completeness.
  - cross-cutting-auditor: No real secrets in tree; messaging migrations empty; docs quality strong; observability deferred to host apps.
