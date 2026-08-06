# Host Integration Re-architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the minimal package seams from the build-ready host-integration proposal so hosts configure (not rebind) AI dispatch, get live idempotency readiness, gate proposals, reap stale turns, diagnose product readiness via Artisan, and fail closed on MCP register errors.

**Architecture:** Keep the four-package product split. Core owns MCP allowlist validation, `on_register_error`, and `capabilities:integration-health`. AI owns queue-on-default-dispatch, reaper, live `IdempotencyReadiness`, `proposals.enabled` gating (routes + TurnRunner fence), and Phase-3 unsafe-driver guards. Hosts keep product HTTP routes and `extend(ProgressStore::class)`; no decorator DSL, no HTTP controller map, no fifth package.

**Tech Stack:** PHP ^8.2, Laravel Illuminate (container/console/queue/DB), Pest unit tests only (no feature/DB-required app boot beyond in-memory SQLite Capsule used by existing AI unit suites), monorepo path packages under `packages/*`.

**Spec source:** `docs/proposals/2026-08-06-host-integration-rearchitecture.md` (rev 4). Record **D-024** in `docs/spec.md` when build starts (Task 0).

**Out of monorepo scope:** Phase 4 MesoPrep host cleanup (UR-032–045). Package half only; docs include residual kill-list template.

## Global Constraints

- **Unit tests only** — no `tests/Feature`, no real MySQL/Postgres, mock IO boundaries; AI suites may use existing in-memory SQLite Capsule pattern.
- **≥95% coverage** on touched package `src/` after each task (package suites must stay green).
- **No second mutation path** — surfaces stay adapters; bus remains the choke point.
- **Prefer zero new extension mechanisms** — config knobs + Artisan only; host `extend` / host routes for product UX.
- **AI-chat (health only)** = `capabilities-ai.routes.enabled === true` **OR** non-empty `capabilities-ai.queue.name`.
- **Profiles** = `name => list<string>` capability names only (D-008); no nested MCP profile DSL in this plan.
- **AlwaysReadyIdempotency** = unit tests only after Task 2; never package production default.
- **Empty MCP plan** stays soft-fail (ORI-801); non-empty plan + register failure → throw when `on_register_error=throw` (default).
- **Do not** add `progress.decorators`, `surfaces.http.controllers`, `dispatch_binding`, `product_chat`, or `integration.mode`.
- Run package tests with: `composer test:ai` · `composer test:core` (from monorepo root).
- Commits: no co-author trailers; conventional `feat:` / `fix:` / `docs:` / `test:` prefixes.

## File structure (create / modify map)

| Path | Responsibility |
|------|----------------|
| `docs/spec.md` | Add **D-024 host integration contract** summary + link to proposal |
| `packages/laravel-capabilities-ai/config/capabilities-ai.php` | `queue`, `proposals`, `reaper` (+ Phase 3 allow-unsafe env) |
| `packages/laravel-capabilities-ai/src/Jobs/RunTurnJob.php` | Public `$queue` / `$connection` for Laravel dispatcher |
| `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` | Queue-aware dispatch; live readiness; proposals gates; reaper command; prod guards |
| `packages/laravel-capabilities-ai/src/Support/ContainerBindings.php` | Pass `proposalsEnabled` into TurnRunner; readiness factory helper if needed |
| `packages/laravel-capabilities-ai/src/Support/StoreBoundIdempotencyReadiness.php` | **Create** — live readiness (store bound + ping, else not ready) |
| `packages/laravel-capabilities-ai/src/Support/AlwaysReadyIdempotency.php` | Comment: tests only |
| `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php` | Skip fence/proposal create when proposals disabled |
| `packages/laravel-capabilities-ai/src/Domain/StaleTurnReaper.php` | **Create** — pure threshold logic over Turn query |
| `packages/laravel-capabilities-ai/src/Console/ReapStaleTurnsCommand.php` | **Create** — `capabilities-ai:reap-stale-turns` |
| `packages/laravel-capabilities-ai/routes/capabilities-ai.php` | Proposal routes only when enabled (or load from SP) |
| `packages/laravel-capabilities/config/capabilities.php` | `surfaces.mcp.on_register_error` |
| `packages/laravel-capabilities/src/Adapters/Mcp/McpServerRegistrar.php` | Allowlist validation; `on_register_error` wrap |
| `packages/laravel-capabilities/src/Adapters/Mcp/McpProfileValidator.php` | **Create** — validate profile capability names + MCP surface |
| `packages/laravel-capabilities/src/Support/IntegrationHealthReport.php` | **Create** — result DTO |
| `packages/laravel-capabilities/src/Support/IntegrationHealthChecker.php` | **Create** — pure checks |
| `packages/laravel-capabilities/src/Adapters/Artisan/IntegrationHealthCommand.php` | **Create** — Artisan wrapper |
| `packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCommandTable.php` | Register health command |
| Docs: package READMEs, user-guides, CHANGELOGs, monorepo getting-started / concepts as needed |

---

### Task 0: Record D-024 in the design oracle

**Files:**
- Modify: `docs/spec.md` (append after D-023 section)
- Modify: `docs/proposals/2026-08-06-host-integration-rearchitecture.md` status line only if still “when build starts” — mark build started

**Interfaces:**
- Consumes: proposal §3–§4 locked decisions
- Produces: normative **D-024** text implementers may cite

- [ ] **Step 1: Append D-024 to `docs/spec.md`**

Find the end of the D-023 section in `docs/spec.md` and append:

```markdown
### D-024 — Host integration contract (package seams)

**Status:** normative for 0.x package work (build-ready proposal 2026-08-06 rev 4).

Hosts own domain capabilities, authorizer policy, product HTTP UX, and optional `extend(ProgressStore)`. Packages own runtime defaults and fail-closed product surfaces.

| Seam | Owner | Rule |
|------|--------|------|
| Queue name/connection on default AI dispatch | AI package | Config `capabilities-ai.queue.{name,connection}` applied to `RunTurnJob` before dispatch |
| Progress side-effects | Host | `app()->extend(ProgressStore::class, …)` after package bind — never full singleton rebind of redis/array store |
| Product HTTP UX | Host routes | No package HTTP controller action map |
| Idempotency readiness default | AI package | Live probe of core `IdempotencyStore` when bound; else `isReady()=false`. `AlwaysReadyIdempotency` is unit-test only |
| Proposals | AI package | Single `proposals.enabled`; gates accept/reject routes, TurnRunner fence extract, and proposal history |
| Stale turns | AI package | `capabilities-ai:reap-stale-turns` + thresholds; host schedules the command |
| Integration diagnostics | Core package | `php artisan capabilities:integration-health` (≠ HTTP `…/capabilities/health`) |
| AI-chat mode (health only) | Core health | `capabilities-ai.routes.enabled` **OR** non-empty `capabilities-ai.queue.name` |
| MCP profiles | Core | `name => list<string>` capability names; validate existence + MCP surface at register; `on_register_error` (default `throw`) for mid-mount failures; empty plan soft-fail (ORI-801) |

Deliberately **not** package APIs: progress decorator config, HTTP controller maps, `dispatch_binding`, nested MCP profile DSL, `product_chat` / `integration.mode`, package reaper auto-schedule.

Full checklist and phased delivery: `docs/proposals/2026-08-06-host-integration-rearchitecture.md`.
```

- [ ] **Step 2: Commit**

```bash
git add docs/spec.md
git commit -m "docs: record D-024 host integration contract"
```

---

### Task 1: Queue name/connection on default AI dispatch

**Files:**
- Modify: `packages/laravel-capabilities-ai/config/capabilities-ai.php`
- Modify: `packages/laravel-capabilities-ai/src/Jobs/RunTurnJob.php`
- Modify: `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` (`makeDispatchCallable`)
- Test: `packages/laravel-capabilities-ai/tests/Unit/Boot/DispatchQueueConfigTest.php` (**create**)
- Test: update `packages/laravel-capabilities-ai/tests/Unit/Domain/CheapCreateTest.php` only if job property defaults break assertions (should not)

**Interfaces:**
- Consumes: existing `ConversationService` dispatch callable; `RunTurnJob`
- Produces:
  - Config keys: `capabilities-ai.queue.connection`, `capabilities-ai.queue.name` (nullable strings)
  - `RunTurnJob::$queue` (`?string`), `RunTurnJob::$connection` (`?string`) — Laravel bus reads these public props
  - Default dispatch callable sets props when config non-empty **before** `dispatch`

- [ ] **Step 1: Write the failing test**

Create `packages/laravel-capabilities-ai/tests/Unit/Boot/DispatchQueueConfigTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\CapabilitiesAi\CapabilitiesAiServiceProvider;
use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Jobs\RunTurnJob;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;

/**
 * Minimal config repo (same shape as ServiceProviderBindingsTest).
 *
 * @param  array<string, mixed>  $items
 */
function dqConfigRepo(array $items): object
{
    return new class($items)
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            $parts = explode('.', $key);
            $cur = $this->items;
            foreach ($parts as $p) {
                if (! is_array($cur) || ! array_key_exists($p, $cur)) {
                    return $default;
                }
                $cur = $cur[$p];
            }

            return $cur;
        }
    };
}

/**
 * @param  array<string, mixed>  $aiOverrides
 * @return array{0: Container, 1: object}
 */
function bootDispatchQueueContainer(array $aiOverrides = []): array
{
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };

    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $config = array_replace_recursive($base, $aiOverrides);
    $app->instance('config', dqConfigRepo(['capabilities-ai' => $config]));

    $bag = new class
    {
        /** @var list<object> */
        public array $jobs = [];
    };

    $app->instance('Illuminate\Contracts\Bus\Dispatcher', new class($bag)
    {
        public function __construct(private object $bag) {}

        public function dispatch(object $job): mixed
        {
            $this->bag->jobs[] = $job;

            return null;
        }
    });

    (new CapabilitiesAiServiceProvider($app))->register();

    // Replace ProgressStore with array so ConversationService can be constructed without redis.
    $app->instance(\Rawphp\CapabilitiesAi\Contracts\ProgressStore::class, new ArrayProgressStore);

    // Rebuild ConversationService with SP-made dispatch (resolve after progress override).
    // Force re-resolve: forget if needed.
    if (method_exists($app, 'forgetInstance')) {
        $app->forgetInstance(ConversationService::class);
    }

    return [$app, $bag];
}

it('default dispatch sets RunTurnJob queue and connection from config', function () {
    [$app, $bag] = bootDispatchQueueContainer([
        'queue' => [
            'name' => 'capabilities-ai',
            'connection' => 'redis',
        ],
    ]);

    // Resolve ConversationService — create path needs Eloquent; unit-test dispatch only via reflection/callable.
    // Call the SP dispatch path by resolving service is heavy; instead re-register and invoke dispatch through a thin extract.
    // Prefer: resolve ConversationService and spy job via Dispatcher already installed — requires SQLite for createUserMessage.
    // Lightweight: reflect makeDispatchCallable by dispatching through a fresh RunTurnJob via bound Dispatcher after
    // constructing ConversationService manually is wrong. Use createUserMessage with sqlite like CheapCreateTest.

    // Boot sqlite (copy helper pattern from CheapCreateTest)
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app->instance('db', $capsule->getDatabaseManager());
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    \Illuminate\Support\Facades\Schema::swap($capsule->getConnection()->getSchemaBuilder());
    $dir = dirname(__DIR__, 3).'/database/migrations';
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        (require $file)->up();
    }

    $svc = $app->make(ConversationService::class);
    $svc->createUserMessage('queued');

    expect($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0])->toBeInstanceOf(RunTurnJob::class)
        ->and($bag->jobs[0]->queue ?? null)->toBe('capabilities-ai')
        ->and($bag->jobs[0]->connection ?? null)->toBe('redis');
});

it('default dispatch leaves queue/connection null when config empty', function () {
    [$app, $bag] = bootDispatchQueueContainer([
        'queue' => [
            'name' => null,
            'connection' => null,
        ],
    ]);

    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher(new Container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $app->instance('db', $capsule->getDatabaseManager());
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    \Illuminate\Support\Facades\Schema::swap($capsule->getConnection()->getSchemaBuilder());
    $dir = dirname(__DIR__, 3).'/database/migrations';
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        (require $file)->up();
    }

    // ConversationService was built at first resolve before db — forget and re-make after SP already registered.
    if (method_exists($app, 'forgetInstance')) {
        $app->forgetInstance(ConversationService::class);
    }
    // Re-bind ProgressStore again after forget cascade if needed
    $app->instance(\Rawphp\CapabilitiesAi\Contracts\ProgressStore::class, new ArrayProgressStore);

    // SP singleton for ConversationService already closed over dispatch — still fine.
    $svc = $app->make(ConversationService::class);
    $svc->createUserMessage('no queue');

    expect($bag->jobs)->toHaveCount(1)
        ->and($bag->jobs[0]->queue ?? null)->toBeNull()
        ->and($bag->jobs[0]->connection ?? null)->toBeNull();
});
```

> Implementer note: if the SP resolves `ConversationService` before ProgressStore override, restructure the test to set ProgressStore **before** `register()` (host-prebound pattern) so the singleton factory is not needed. Preferred cleaner harness:

```php
$app->instance(ProgressStore::class, new ArrayProgressStore);
(new CapabilitiesAiServiceProvider($app))->register();
```

because SP uses `if (! $app->bound(ProgressStore::class))`.

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
composer test:ai -- --filter=DispatchQueueConfig
```

Expected: FAIL — config key `queue` missing and/or `RunTurnJob` has no `$queue` / dispatch does not set it.

- [ ] **Step 3: Add config keys**

In `packages/laravel-capabilities-ai/config/capabilities-ai.php`, after `claim_ttl`:

```php
    /**
     * Default RunTurnJob queue routing (happy path — no ConversationService rebind).
     * Empty/null → Laravel default queue/connection.
     */
    'queue' => [
        'connection' => $env('CAPABILITIES_AI_QUEUE_CONNECTION'),
        'name' => $env('CAPABILITIES_AI_QUEUE_NAME'),
    ],
```

- [ ] **Step 4: Add public queue props on `RunTurnJob`**

```php
final class RunTurnJob implements ShouldQueue
{
    /** Finite attempts; claim_ttl is the worker heartbeat window. */
    public int $tries = 1;

    /** Seconds; default from Package::DEFAULT_CLAIM_TTL; cheap-create may override from config. */
    public int $timeout = Package::DEFAULT_CLAIM_TTL;

    /** Laravel bus / queue worker read these public props (no Queueable trait required). */
    public ?string $queue = null;

    public ?string $connection = null;

    public function __construct(
        public readonly string $turnUlid,
    ) {}

    public function handle(TurnRunner $runner): void
    {
        $runner->run($this->turnUlid);
    }
}
```

- [ ] **Step 5: Apply queue config in `makeDispatchCallable`**

Replace `makeDispatchCallable` in `CapabilitiesAiServiceProvider` with:

```php
/**
 * @return callable(object): mixed
 */
private static function makeDispatchCallable(Container $app): callable
{
    $config = self::configFromApp($app);
    $queueName = $config['queue']['name'] ?? null;
    $queueConnection = $config['queue']['connection'] ?? null;
    $queueName = is_string($queueName) && $queueName !== '' ? $queueName : null;
    $queueConnection = is_string($queueConnection) && $queueConnection !== '' ? $queueConnection : null;

    $applyQueue = static function (object $job) use ($queueName, $queueConnection): void {
        if ($queueName !== null && property_exists($job, 'queue')) {
            $job->queue = $queueName;
        }
        if ($queueConnection !== null && property_exists($job, 'connection')) {
            $job->connection = $queueConnection;
        }
    };

    if ($app->bound('Illuminate\Contracts\Bus\Dispatcher')) {
        $bus = $app->make('Illuminate\Contracts\Bus\Dispatcher');

        return static function (object $job) use ($bus, $applyQueue): mixed {
            $applyQueue($job);

            return $bus->dispatch($job);
        };
    }

    if (function_exists('dispatch')) {
        return static function (object $job) use ($applyQueue): mixed {
            $applyQueue($job);

            return dispatch($job);
        };
    }

    return static function (object $job): void {
        throw new RuntimeException(
            'No bus dispatcher available; bind Illuminate\\Contracts\\Bus\\Dispatcher or rebind ConversationService'
        );
    };
}
```

- [ ] **Step 6: Run tests**

```bash
composer test:ai -- --filter=DispatchQueueConfig
composer test:ai
```

Expected: PASS (full AI suite green).

- [ ] **Step 7: Commit**

```bash
git add packages/laravel-capabilities-ai/config/capabilities-ai.php \
  packages/laravel-capabilities-ai/src/Jobs/RunTurnJob.php \
  packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php \
  packages/laravel-capabilities-ai/tests/Unit/Boot/DispatchQueueConfigTest.php
git commit -m "feat(ai): apply queue name/connection on default RunTurnJob dispatch"
```

---

### Task 2: Live `IdempotencyReadiness` default (AlwaysReady tests-only)

**Files:**
- Create: `packages/laravel-capabilities-ai/src/Support/StoreBoundIdempotencyReadiness.php`
- Modify: `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` (readiness binding)
- Modify: `packages/laravel-capabilities-ai/src/Support/AlwaysReadyIdempotency.php` (docblock)
- Test: `packages/laravel-capabilities-ai/tests/Unit/Support/StoreBoundIdempotencyReadinessTest.php` (**create**)
- Modify: `packages/laravel-capabilities-ai/tests/Unit/Boot/ServiceProviderBindingsTest.php` (default is **not** AlwaysReady)

**Interfaces:**
- Consumes: `Rawphp\Capabilities\Contracts\IdempotencyStore` (core), `IdempotencyReadiness`
- Produces:
  - `StoreBoundIdempotencyReadiness` implementing `IdempotencyReadiness`
  - Algorithm (locked): if store bound → resolve + `find(...)` ping → ready on success; if unbound or ping throws → `isReady() === false`
  - SP default: `StoreBoundIdempotencyReadiness` (not AlwaysReady)

- [ ] **Step 1: Write failing unit tests**

Create `packages/laravel-capabilities-ai/tests/Unit/Support/StoreBoundIdempotencyReadinessTest.php`:

```php
<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness;

it('is not ready when store unbound', function () {
    $r = StoreBoundIdempotencyReadiness::unbound();
    expect($r->isReady())->toBeFalse();
});

it('is ready when store find ping succeeds', function () {
    $store = new class implements IdempotencyStore
    {
        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            return null;
        }

        public function put(array $record): array
        {
            return $record;
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return null;
        }
    };

    $r = StoreBoundIdempotencyReadiness::forStore($store);
    expect($r->isReady())->toBeTrue();
});

it('is not ready when store find ping throws', function () {
    $store = new class implements IdempotencyStore
    {
        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            throw new RuntimeException('down');
        }

        public function put(array $record): array
        {
            return $record;
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return null;
        }
    };

    expect(StoreBoundIdempotencyReadiness::forStore($store)->isReady())->toBeFalse();
});
```

Update `ServiceProviderBindingsTest.php` test `defaults IdempotencyReadiness to AlwaysReadyIdempotency` →:

```php
it('defaults IdempotencyReadiness to fail-closed StoreBound when store unbound', function () {
    $app = bootAiProviderContainer();

    $ready = $app->make(IdempotencyReadiness::class);
    expect($ready)->toBeInstanceOf(\Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness::class)
        ->and($ready->isReady())->toBeFalse();
});

it('defaults IdempotencyReadiness ready when core IdempotencyStore is bound', function () {
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };
    $base = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    $app->instance('config', aiConfigRepo(['capabilities-ai' => $base]));
    $app->instance(CapabilityBus::class, aiFakeBus());
    $app->instance(\Rawphp\Capabilities\Contracts\IdempotencyStore::class, new class implements \Rawphp\Capabilities\Contracts\IdempotencyStore
    {
        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            return null;
        }

        public function put(array $record): array
        {
            return $record;
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return null;
        }
    });

    (new CapabilitiesAiServiceProvider($app))->register();

    expect($app->make(IdempotencyReadiness::class)->isReady())->toBeTrue();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
composer test:ai -- --filter='StoreBoundIdempotency|IdempotencyReadiness'
```

- [ ] **Step 3: Implement `StoreBoundIdempotencyReadiness`**

```php
<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Throwable;

/**
 * Live accept-time readiness: core IdempotencyStore bound + ping succeeds.
 * Unbound store → not ready (fail closed). AlwaysReadyIdempotency is tests-only.
 */
final class StoreBoundIdempotencyReadiness implements IdempotencyReadiness
{
    private function __construct(
        private readonly ?IdempotencyStore $store,
    ) {}

    public static function unbound(): self
    {
        return new self(null);
    }

    public static function forStore(IdempotencyStore $store): self
    {
        return new self($store);
    }

    public function isReady(): bool
    {
        if ($this->store === null) {
            return false;
        }

        try {
            // Ping only — null record is success; throw → not ready.
            $this->store->find(
                null,
                'system',
                'capabilities-ai-readiness',
                '__capabilities_ai_readiness__',
                '__ping__',
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
```

- [ ] **Step 4: Wire SP default**

Replace AlwaysReady default block:

```php
if (! $this->app->bound(IdempotencyReadiness::class)) {
    $this->app->singleton(IdempotencyReadiness::class, function (Container $app) {
        if ($app->bound(\Rawphp\Capabilities\Contracts\IdempotencyStore::class)) {
            return StoreBoundIdempotencyReadiness::forStore(
                $app->make(\Rawphp\Capabilities\Contracts\IdempotencyStore::class)
            );
        }

        return StoreBoundIdempotencyReadiness::unbound();
    });
}
```

Update `AlwaysReadyIdempotency` docblock:

```php
/**
 * Always-ready probe for **unit tests only**.
 * Production SP default is {@see StoreBoundIdempotencyReadiness} (fail closed when store unbound).
 */
```

- [ ] **Step 5: Run AI suite**

```bash
composer test:ai
```

Expected: PASS. Fix any leftover AlwaysReady default assertions.

- [ ] **Step 6: Commit**

```bash
git add packages/laravel-capabilities-ai/src/Support/StoreBoundIdempotencyReadiness.php \
  packages/laravel-capabilities-ai/src/Support/AlwaysReadyIdempotency.php \
  packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php \
  packages/laravel-capabilities-ai/tests/Unit/Support/StoreBoundIdempotencyReadinessTest.php \
  packages/laravel-capabilities-ai/tests/Unit/Boot/ServiceProviderBindingsTest.php
git commit -m "feat(ai): live IdempotencyReadiness default; AlwaysReady tests-only"
```

---

### Task 3: `proposals.enabled` — routes + TurnRunner fence + history empty

**Files:**
- Modify: `packages/laravel-capabilities-ai/config/capabilities-ai.php`
- Modify: `packages/laravel-capabilities-ai/src/Domain/TurnRunner.php`
- Modify: `packages/laravel-capabilities-ai/src/Support/ContainerBindings.php` (`makeTurnRunner`)
- Modify: `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` (`bootRoutes` + TurnRunner wiring)
- Modify: `packages/laravel-capabilities-ai/src/Domain/ConversationService.php` (`history` proposals collection)
- Modify: `packages/laravel-capabilities-ai/routes/capabilities-ai.php` (remove always-on proposal routes; SP loads conditionally) **or** keep file and load proposal routes in SP only
- Test: `packages/laravel-capabilities-ai/tests/Unit/Domain/ProposalsEnabledGateTest.php` (**create**)
- Test: update TurnRunner tests that create fences only when enabled (default true Phase 1 BC)

**Interfaces:**
- Consumes: config `proposals.enabled` (Phase 1 default **`true`** for BC; greenfield docs say set `false`)
- Produces:
  - `TurnRunner` ctor flag `bool $proposalsEnabled = true`
  - When `false`: `maybeCreateProposalsFromFence` no-ops; accept/reject routes **not** registered; `history()` includes `'proposals' => []`

- [ ] **Step 1: Write failing tests**

Create `packages/laravel-capabilities-ai/tests/Unit/Domain/ProposalsEnabledGateTest.php`:

```php
<?php

declare(strict_types=1);

// Reuse bootTurnSqlite / enqueue helpers from TurnRunnerTest.php — if those helpers are file-local,
// copy the minimal sqlite + enqueue setup used there into this file (do not invent Feature tests).

use Rawphp\CapabilitiesAi\Domain\ConversationService;
use Rawphp\CapabilitiesAi\Domain\TurnClaim;
use Rawphp\CapabilitiesAi\Domain\TurnRunner;
use Rawphp\CapabilitiesAi\Models\Proposal;
use Rawphp\CapabilitiesAi\Support\ArrayProgressStore;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
// ... same ConversationContextProvider / ToolCatalog fakes as TurnRunnerTest for text-only completion

it('skips proposal fence extract when proposalsEnabled=false', function () {
    // boot sqlite + enqueue turn with user message
    // FakeLlmClient returns content containing ```proposal ... ```
    $runner = new TurnRunner(
        claim: new TurnClaim,
        llm: new FakeLlmClient([['content' => "ok\n```proposal\n{\"type\":\"action\",\"target_capability\":\"x.y\",\"payload\":{}}\n```"]]),
        progress: new ArrayProgressStore,
        context: /* fake returning user messages */,
        tools: /* empty tools catalog */,
        bus: null,
        proposalsEnabled: false,
    );
    $runner->run($turnUlid);
    expect(Proposal::query()->count())->toBe(0);
});

it('creates proposal from fence when proposalsEnabled=true', function () {
    // same setup with proposalsEnabled: true (default)
    // expect Proposal::query()->count() === 1
});

it('history returns empty proposals when service constructed with proposals disabled', function () {
    $svc = new ConversationService(
        static fn ($j) => null,
        new ArrayProgressStore,
        claimTtl: 120,
        proposalsEnabled: false,
    );
    // create conversation via createUserMessage then history
    $ids = $svc->createUserMessage('hi');
    $h = $svc->history($ids['conversation_ulid']);
    expect($h)->toHaveKey('proposals')
        ->and($h['proposals'])->toBe([]);
});
```

Also add a pure unit test for route gating without full HTTP:

```php
it('bootRoutes does not register proposal routes when proposals.enabled=false', function () {
    // If Route facade is hard to fake, unit-test a new pure helper instead:
    // CapabilitiesAiServiceProvider::proposalRoutesEnabled(array $config): bool
});
```

Add on SP (or small helper class) for testability:

```php
public static function proposalsEnabled(array $config): bool
{
    return (bool) ($config['proposals']['enabled'] ?? true);
}
```

- [ ] **Step 2: Run — expect FAIL**

```bash
composer test:ai -- --filter=ProposalsEnabled
```

- [ ] **Step 3: Config**

```php
    'proposals' => [
        // Phase 1 BC default true; greenfield docs: CAPABILITIES_AI_PROPOSALS_ENABLED=false
        'enabled' => (bool) $env('CAPABILITIES_AI_PROPOSALS_ENABLED', true),
    ],
```

- [ ] **Step 4: TurnRunner gate**

Add ctor property after existing deps:

```php
private readonly bool $proposalsEnabled = true,
```

Change `maybeCreateProposalsFromFence`:

```php
private function maybeCreateProposalsFromFence(int $conversationId, int $turnId, string $content): void
{
    if (! $this->proposalsEnabled) {
        return;
    }

    $data = $this->proposalExtractor->extract($content);
    if ($data === null) {
        return;
    }

    Proposal::query()->create([
        // existing fields unchanged
    ]);
}
```

Update `ContainerBindings::makeTurnRunner` to pass:

```php
proposalsEnabled: (bool) ($config['proposals']['enabled'] ?? true),
```

- [ ] **Step 5: ConversationService history**

```php
public function __construct(
    private readonly mixed $dispatch,
    private readonly ProgressStore $progress,
    private readonly int $claimTtl = Package::DEFAULT_CLAIM_TTL,
    private readonly bool $proposalsEnabled = true,
) { /* existing validation */ }

public function history(string $conversationUlid): array
{
    // existing message load...
    $payload = [
        'conversation_ulid' => $conversation->ulid,
        'messages' => $messages,
        'proposals' => [],
    ];

    if ($this->proposalsEnabled) {
        $payload['proposals'] = Proposal::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (Proposal $p): array => [
                'ulid' => $p->ulid,
                'status' => (string) $p->status,
                'type' => (string) $p->type,
                'target_capability' => $p->target_capability,
            ])
            ->all();
    }

    return $payload;
}
```

Update `makeConversationService` + SP to pass `proposalsEnabled` from config.

- [ ] **Step 6: Conditional proposal routes**

In `bootRoutes()`:

```php
private function bootRoutes(): void
{
    $config = $this->app->make('config')->get('capabilities-ai', []);
    $routes = $config['routes'] ?? [];
    if (! ($routes['enabled'] ?? false)) {
        return;
    }

    $prefix = (string) ($routes['prefix'] ?? 'capabilities-ai/chat');
    $middleware = $routes['middleware'] ?? ['api', 'auth:sanctum'];
    $proposalsOn = self::proposalsEnabled(is_array($config) ? $config : []);

    Route::middleware($middleware)
        ->prefix($prefix)
        ->group(function () use ($proposalsOn): void {
            require __DIR__.'/../routes/capabilities-ai.php';
            if ($proposalsOn) {
                require __DIR__.'/../routes/capabilities-ai-proposals.php';
            }
        });
}

public static function proposalsEnabled(array $config): bool
{
    return (bool) ($config['proposals']['enabled'] ?? true);
}
```

Move accept/reject routes from `routes/capabilities-ai.php` into new `routes/capabilities-ai-proposals.php`.

- [ ] **Step 7: Run suite + commit**

```bash
composer test:ai
git add packages/laravel-capabilities-ai
git commit -m "feat(ai): gate proposals via proposals.enabled (routes, fence, history)"
```

---

### Task 4: Stale-turn reaper

**Files:**
- Modify: `packages/laravel-capabilities-ai/config/capabilities-ai.php` (`reaper` keys)
- Create: `packages/laravel-capabilities-ai/src/Domain/StaleTurnReaper.php`
- Create: `packages/laravel-capabilities-ai/src/Console/ReapStaleTurnsCommand.php`
- Modify: `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` (`$this->commands([...])` in boot)
- Test: `packages/laravel-capabilities-ai/tests/Unit/Domain/StaleTurnReaperTest.php`

**Interfaces:**
- Consumes: `Turn` model statuses; config thresholds; `claim_ttl`
- Produces:
  - `StaleTurnReaper::reap(int $staleQueuedMinutes, int $claimTtlSeconds, int $runningGraceSeconds, ?\DateTimeInterface $now = null): array{queued: int, running: int}`
  - Rules:
    - **queued**: `status=queued` AND `created_at` older than `staleQueuedMinutes`
    - **running**: `status=running` AND `claimed_at` older than `max(claimTtl, runningGraceSeconds)` seconds
  - Marks matched rows `failed` with a clear `error` string; sets `finished_at`
  - Artisan: `capabilities-ai:reap-stale-turns` → prints counts; exit 0

- [ ] **Step 1: Failing tests** (sqlite Capsule like other domain tests)

```php
it('fails queued turns older than threshold', function () {
    // create turn status=queued with created_at = now - 31 minutes
    $reaper = new StaleTurnReaper;
    $counts = $reaper->reap(staleQueuedMinutes: 30, claimTtlSeconds: 120, runningGraceSeconds: 60, now: $frozenNow);
    expect($counts['queued'])->toBe(1)
        ->and(Turn::query()->where('ulid', $ulid)->value('status'))->toBe(Turn::STATUS_FAILED);
});

it('fails running turns past max(claim_ttl, grace)', function () { /* claimed_at old */ });

it('leaves fresh queued and running turns alone', function () { /* counts zero */ });
```

- [ ] **Step 2: Run — FAIL**

```bash
composer test:ai -- --filter=StaleTurnReaper
```

- [ ] **Step 3: Implement reaper**

```php
<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Domain;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Rawphp\CapabilitiesAi\Models\Turn;

final class StaleTurnReaper
{
    /**
     * @return array{queued: int, running: int}
     */
    public function reap(
        int $staleQueuedMinutes,
        int $claimTtlSeconds,
        int $runningGraceSeconds,
        ?DateTimeInterface $now = null,
    ): array {
        $now = Carbon::instance($now ?? Carbon::now());
        $queuedCutoff = $now->copy()->subMinutes(max(0, $staleQueuedMinutes));
        $runningSeconds = max($claimTtlSeconds, $runningGraceSeconds);
        $runningCutoff = $now->copy()->subSeconds(max(0, $runningSeconds));

        $queued = Turn::query()
            ->where('status', Turn::STATUS_QUEUED)
            ->where('created_at', '<', $queuedCutoff)
            ->update([
                'status' => Turn::STATUS_FAILED,
                'error' => 'reaped: stale queued',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        $running = Turn::query()
            ->where('status', Turn::STATUS_RUNNING)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', $runningCutoff)
            ->update([
                'status' => Turn::STATUS_FAILED,
                'error' => 'reaped: stale running claim',
                'finished_at' => $now,
                'updated_at' => $now,
            ]);

        return ['queued' => (int) $queued, 'running' => (int) $running];
    }
}
```

Command:

```php
<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Console;

use Illuminate\Console\Command;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;

final class ReapStaleTurnsCommand extends Command
{
    protected $signature = 'capabilities-ai:reap-stale-turns';

    protected $description = 'Fail stale queued/running capabilities_ai turns (host schedules this).';

    public function handle(StaleTurnReaper $reaper): int
    {
        $config = (array) config('capabilities-ai', []);
        $claimTtl = ContainerBindings::claimTtlFromConfig($config);
        $staleQueued = (int) ($config['reaper']['stale_queued_minutes'] ?? 30);
        $grace = (int) ($config['reaper']['stale_running_grace_seconds'] ?? 60);

        $counts = $reaper->reap($staleQueued, $claimTtl, $grace);
        $this->info("reaped queued={$counts['queued']} running={$counts['running']}");

        return self::SUCCESS;
    }
}
```

Config:

```php
    'reaper' => [
        'stale_queued_minutes' => (int) $env('CAPABILITIES_AI_REAPER_STALE_QUEUED', 30),
        'stale_running_grace_seconds' => (int) $env('CAPABILITIES_AI_REAPER_RUNNING_GRACE', 60),
    ],
```

SP register + boot:

```php
// register()
$this->app->singleton(StaleTurnReaper::class, static fn () => new StaleTurnReaper);

// boot() console
$this->commands([ReapStaleTurnsCommand::class]);
```

- [ ] **Step 4: Green + commit**

```bash
composer test:ai
git add packages/laravel-capabilities-ai
git commit -m "feat(ai): stale turn reaper command and thresholds"
```

---

### Task 5: `capabilities:integration-health` (core)

**Files:**
- Create: `packages/laravel-capabilities/src/Support/IntegrationHealthReport.php`
- Create: `packages/laravel-capabilities/src/Support/IntegrationHealthChecker.php`
- Create: `packages/laravel-capabilities/src/Adapters/Artisan/IntegrationHealthCommand.php`
- Modify: `packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCommandTable.php`
- Test: `packages/laravel-capabilities/tests/Unit/Support/IntegrationHealthCheckerTest.php`

**Interfaces:**
- Consumes: core `config('capabilities')`, optional AI config array, container `bound()` callbacks, optional MCP tools count callback
- Produces pure report (no HTTP):

```php
final class IntegrationHealthReport
{
    /**
     * @param  list<array{level: 'fail'|'warn'|'ok'|'skip', code: string, message: string}>  $checks
     */
    public function __construct(
        public readonly string $mode, // bus-only | ai-chat | (mcp noted in checks)
        public readonly array $checks,
    ) {}

    public function failed(): bool { /* any level=fail */ }
    public function exitCode(): int { return $this->failed() ? 1 : 0; }
}
```

Checker API:

```php
final class IntegrationHealthChecker
{
    /**
     * @param  array<string, mixed>  $capabilitiesConfig
     * @param  array<string, mixed>|null  $aiConfig  null when AI package config absent
     * @param  callable(class-string): bool  $bound
     * @param  callable(): int|null  $mcpToolCount  null to skip live tool count
     * @param  callable(): string|null  $idempotencyReadinessClass  resolved class or null
     */
    public function check(
        array $capabilitiesConfig,
        ?array $aiConfig,
        callable $bound,
        ?callable $mcpToolCount = null,
        ?callable $idempotencyReadinessClass = null,
    ): IntegrationHealthReport {}
}
```

**Rules (proposal §4.1 D + §3.5):**

| Check code | Level | When |
|------------|-------|------|
| `authorizer_bound` | fail if unbound | any of http/mcp/agent/job/cli **enabled** in config; else skip |
| `ai_context_bound` | fail if unbound | AI-chat |
| `ai_tool_catalog_bound` | fail if unbound | AI-chat |
| `ai_claim_ttl` | fail if ≤0 | AI-chat |
| `ai_always_ready` | fail if readiness class is AlwaysReady | **only when** `proposals.enabled`; else skip |
| `ai_progress_array` | **warn** (Phase 1) | AI-chat and progress.driver=array |
| `ai_queue_name_empty` | **warn** (Phase 1) | AI-chat entered via **routes only** and queue.name empty |
| `mcp_tools` | fail if tools===0 | MCP enabled + non-empty plan (profiles or servers non-empty) |

AI-chat detection:

```php
private function isAiChat(?array $ai): bool
{
    if ($ai === null) {
        return false;
    }
    $routesOn = (bool) ($ai['routes']['enabled'] ?? false);
    $queueName = $ai['queue']['name'] ?? null;
    $queueOn = is_string($queueName) && $queueName !== '';

    return $routesOn || $queueOn;
}
```

- [ ] **Step 1: Write pure unit tests** (no Artisan kernel)

Cover:
1. Bus-only (AI config null or routes off + empty queue) → mode `bus-only`; skips AI checks
2. AI-chat via `queue.name` only → requires Context/ToolCatalog bound
3. AI-chat via routes only + empty queue → warn `ai_queue_name_empty`
4. AlwaysReady + proposals on → fail; proposals off → skip
5. Authorizer skip when all invoke surfaces disabled
6. MCP non-empty plan + zero tools → fail

- [ ] **Step 2: Implement checker + report + command**

Command signature: `capabilities:integration-health`  
Description: product integration readiness (≠ HTTP capabilities health).

Register in `ArtisanCommandTable::commands()`:

```php
[
    'key' => 'integration-health',
    'signature' => 'capabilities:integration-health',
    'description' => 'Diagnose host product readiness (bindings, AI-chat, MCP tools).',
    'caller' => self::CALLER,
    'role' => self::ROLE,
    'class' => IntegrationHealthCommand::class,
],
```

Command `handle()` resolves config, builds `$bound = fn (string $a) => $this->laravel->bound($a)`, optionally counts MCP tools via existing registrar if adapter bound — if hard, inject `mcpToolCount: null` and document that hosts with MCP should still see allowlist validation from Task 6; health can call `McpServerRegistrar::register` only when registry+adapter resolvable.

Keep MCP tool count best-effort:

```php
$mcpToolCount = function () use ($app): int {
    if (! $app->bound(CapabilityRegistry::class) || ! $app->bound(McpToolAdapter::class)) {
        return 0;
    }
    $mcp = (array) config('capabilities.surfaces.mcp', []);
    $servers = McpServerRegistrar::register($mcp, $app->make(McpToolAdapter::class));
    $n = 0;
    foreach ($servers as $s) {
        $n += count($s['tools'] ?? []);
    }
    return $n;
};
```

Catch throwables → fail check `mcp_register` with message.

- [ ] **Step 3: Green core suite + commit**

```bash
composer test:core
git add packages/laravel-capabilities
git commit -m "feat(core): capabilities:integration-health Artisan diagnostic"
```

---

### Task 6: MCP allowlist validation + `on_register_error`

**Files:**
- Modify: `packages/laravel-capabilities/config/capabilities.php` (`surfaces.mcp.on_register_error`)
- Create: `packages/laravel-capabilities/src/Adapters/Mcp/McpProfileValidator.php`
- Modify: `packages/laravel-capabilities/src/Adapters/Mcp/McpServerRegistrar.php`
- Modify: `packages/laravel-capabilities/tests/Unit/Adapters/McpServerRegistrarTest.php` (+ new cases)
- Test: `packages/laravel-capabilities/tests/Unit/Adapters/McpProfileValidatorTest.php`

**Interfaces:**
- Consumes: `CapabilityRegistry::has`, `CapabilityRegistry::get` → definition `surfaces` includes `mcp`
- Produces:
  - Config: `'on_register_error' => $env('CAPABILITIES_MCP_ON_REGISTER_ERROR', 'throw')` // `throw` | `disable`
  - `McpProfileValidator::assertAllowlist(CapabilityRegistry $registry, string $profileName, array $capabilityNames): void` throws `InvalidArgumentException` on unknown name or surface not allowing mcp
  - `McpServerRegistrar::register` before adapter register: load profile allowlist from config profiles map; validate each name
  - Wrap adapter register in try/catch: on Throwable, if plan was non-empty and `on_register_error !== 'disable'`, rethrow; if `disable`, return [] and do not half-register (adapter should not keep partial state — call only after validation; on adapter throw, rethrow or soft-empty per mode)

Validation is **not** a second resolver — still uses existing `mcpTools($profile)` expansion after names are known-good.

- [ ] **Step 1: Failing tests**

```php
it('throws when profile allowlist names a capability missing from registry', function () {
    $registry = /* harness with create-invoice only */;
    expect(fn () => McpProfileValidator::assertAllowlist($registry, 'lab', ['missing.cap']))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when capability exists but surfaces exclude mcp', function () { /* ... */ });

it('register throws on allowlist miss before soft-empty tools', function () {
    // McpServerRegistrar::register with real McpToolAdapterV1 + registry missing a listed name
});

it('on_register_error=throw rethrows adapter register failures for non-empty plan', function () {
    $adapter = mock that throws mid-register;
    expect(fn () => McpServerRegistrar::register($cfg, $adapter, $probe))->toThrow(...);
});

it('empty plan still soft-fails without peer (ORI-801 unchanged)', function () {
    // existing test remains green
});
```

- [ ] **Step 2: Implement validator**

```php
final class McpProfileValidator
{
    /**
     * @param  list<string>  $capabilityNames
     */
    public static function assertAllowlist(CapabilityRegistry $registry, string $profileName, array $capabilityNames): void
    {
        foreach ($capabilityNames as $name) {
            $name = (string) $name;
            if ($name === '') {
                throw new InvalidArgumentException("MCP profile [{$profileName}] contains an empty capability name");
            }
            if (! $registry->has($name)) {
                throw new InvalidArgumentException(
                    "MCP profile [{$profileName}] lists unknown capability [{$name}]"
                );
            }
            $def = $registry->get($name);
            if (! in_array('mcp', $def->surfaces, true)) {
                throw new InvalidArgumentException(
                    "MCP profile [{$profileName}] lists [{$name}] which does not enable the mcp surface"
                );
            }
        }
    }
}
```

Wire into `register()` after plan rows built, before `$adapter->register`:

```php
// Inside register(), foreach row:
$names = self::allowlistForProfile($mcpConfig, $row['profile']);
if ($registry !== null) { // pass registry into register OR validate via adapter's registry
}
```

**Important:** current `McpServerRegistrar::register(array $mcpConfig, McpToolAdapter $adapter, ?PeerVersionProbe $probe)` has no registry. Options:

1. Add optional `?CapabilityRegistry $registry = null` — when null, skip validation (BC for pure adapter fakes); when non-null, validate.
2. Or validate only from production `CapabilitiesServiceProvider::bootMcpServers` which has registry.

Prefer **(1)** optional registry parameter so unit tests pass registry; production boot passes registry.

```php
public static function register(
    array $mcpConfig,
    McpToolAdapter $adapter,
    ?PeerVersionProbe $probe = null,
    ?CapabilityRegistry $registry = null,
): array
```

Update all call sites in core package (SP + tests).

For `on_register_error`:

```php
try {
    $tools = $adapter->register($row['profile']);
} catch (Throwable $e) {
    if (($mcpConfig['on_register_error'] ?? 'throw') === 'disable') {
        return []; // fail closed empty — no partial multi-profile output
    }
    throw $e;
}
```

If multi-profile and second fails after first succeeded, **throw** (default) so hosts do not get silent partial last-wins state; document that multi-profile sequential register is last-wins on success.

- [ ] **Step 3: Green + commit**

```bash
composer test:core
git add packages/laravel-capabilities
git commit -m "feat(core): MCP allowlist validation and on_register_error"
```

---

### Task 7: Phase 3 production guards (+ health warn→fail)

**Files:**
- Modify: `packages/laravel-capabilities-ai/src/Support/ContainerBindings.php` (`makeLlmClient` / `makeProgressStore`)
- Modify: `packages/laravel-capabilities-ai/src/CapabilitiesAiServiceProvider.php` (pass env/app environment)
- Modify: `packages/laravel-capabilities/src/Support/IntegrationHealthChecker.php` (array progress + empty queue → **fail** when AI-chat)
- Config: document `CAPABILITIES_AI_ALLOW_UNSAFE=1`
- Test: `packages/laravel-capabilities-ai/tests/Unit/Boot/UnsafeDriverGuardTest.php`
- Test: update IntegrationHealthChecker tests for fail levels

**Interfaces:**
- Outside testing env, `progress.driver=array` or `llm.driver=fake` **throws** `RuntimeException` unless `CAPABILITIES_AI_ALLOW_UNSAFE` truthy.
- Detect testing: `app()->environment('testing')` when available; else `$_ENV['APP_ENV'] === 'testing'`.
- Health: upgrade `ai_progress_array` and routes-only empty queue from **warn → fail**.

- [ ] **Step 1: Failing tests** for guards + health level change

- [ ] **Step 2: Implement guard helper**

```php
// ContainerBindings.php
public static function assertSafeDrivers(array $config, bool $isTesting, bool $allowUnsafe): void
{
    if ($isTesting || $allowUnsafe) {
        return;
    }
    $progress = strtolower((string) (($config['progress']['driver'] ?? null) ?: 'array'));
    $llm = strtolower((string) (($config['llm']['driver'] ?? null) ?: 'fake'));
    if ($progress === 'array') {
        throw new RuntimeException(
            'progress.driver=array is not allowed outside testing; set CAPABILITIES_AI_PROGRESS_DRIVER=redis or CAPABILITIES_AI_ALLOW_UNSAFE=1'
        );
    }
    if ($llm === 'fake') {
        throw new RuntimeException(
            'llm.driver=fake is not allowed outside testing; bind a real LlmClient or set CAPABILITIES_AI_ALLOW_UNSAFE=1'
        );
    }
}
```

Call from `makeLlmClient` / `makeProgressStore` **or** once from SP before make* — prefer once in SP factories so host-prebound clients skip.

Host-prebound `LlmClient` / `ProgressStore` **skip** guards (already bound).

- [ ] **Step 3: Green both suites + commit**

```bash
composer test:ai && composer test:core
git commit -m "feat: fail closed on unsafe AI drivers and health array/queue checks"
```

---

### Task 8: Docs (greenfield checklist, extend order, kill list, changelogs)

**Files:**
- Modify: `packages/laravel-capabilities-ai/docs/user-guide.md`
- Modify: `packages/laravel-capabilities-ai/README.md`
- Modify: `packages/laravel-capabilities-ai/CHANGELOG.md`
- Modify: `packages/laravel-capabilities/docs/user-guide.md`
- Modify: `packages/laravel-capabilities/README.md`
- Modify: `packages/laravel-capabilities/CHANGELOG.md`
- Modify: `docs/getting-started.md` (greenfield AI-chat checklist §6)
- Modify: `docs/troubleshooting.md` (integration-health, AlwaysReady, queue rebind)

**Content requirements (no package API invention):**

1. Greenfield AI-chat checklist from proposal §6 (host routes + `CAPABILITIES_AI_QUEUE_NAME`, proposals false, redis progress, `extend` in **boot**, `capabilities:integration-health`).
2. Forbidden: full `ConversationService` rebind for queue; `singleton(ProgressStore)` replace; AlwaysReady in prod; package route surgery.
3. Preferred ProgressStore side-effects snippet (`extend` in boot).
4. Host residual kill-list template (dual chat tables, host reapers on wrong tables, route hijacks).
5. claim_ttl default **120**.
6. Package docs self-contained (no relative monorepo-only links from package README).

- [ ] **Step 1: Write docs edits**
- [ ] **Step 2: Commit**

```bash
git add docs packages/laravel-capabilities/docs packages/laravel-capabilities/README.md \
  packages/laravel-capabilities/CHANGELOG.md \
  packages/laravel-capabilities-ai/docs packages/laravel-capabilities-ai/README.md \
  packages/laravel-capabilities-ai/CHANGELOG.md
git commit -m "docs: host integration greenfield checklist and D-024 seams"
```

---

### Task 9: Full monorepo verification gate

**Files:** none (verify only)

- [ ] **Step 1: Run all PHP package tests**

```bash
composer test
```

Expected: all green.

- [ ] **Step 2: Format**

```bash
composer format
```

- [ ] **Step 3: Manual checklist vs proposal**

Confirm each §4 gap has code + unit tests:

| Gap | Task |
|-----|------|
| Queue on default dispatch | 1 |
| Live readiness | 2 |
| proposals.enabled full gate | 3 |
| Reaper | 4 |
| integration-health | 5 |
| MCP validation + on_register_error | 6 |
| Prod guards | 7 |
| Docs | 8 |
| D-024 | 0 |

- [ ] **Step 4: Final commit only if format changed files**

```bash
git status
# if pint changed files:
git add -u && git commit -m "style: pint after host-integration rearchitecture"
```

---

## Self-review (author checklist)

**1. Spec coverage**

| Proposal section | Tasks |
|------------------|-------|
| §3.5 mode detection | 5 |
| §4.1 A HTTP bridge docs | 8 |
| §4.1 B–C MCP | 6 |
| §4.1 D health | 5, 7 |
| §4.2 A queue | 1 |
| §4.2 B extend docs | 8 |
| §4.2 C readiness | 2 |
| §4.2 D proposals | 3 |
| §4.2 E reaper | 4 |
| §4.2 F prod guards | 7 |
| §4.3 docs | 8 |
| §4.4 testing | all task tests |
| §6 checklist | 8 |
| §7 Phase 0 | 0 |
| §7 Phase 4 host | out of monorepo (docs kill list only) |
| Deliberately not doing | enforced by absence of tasks |

**2. Placeholder scan:** no TBD/TODO implement-later steps; commands and code blocks included.

**3. Type consistency:** `StoreBoundIdempotencyReadiness`, `StaleTurnReaper::reap(...)`, `IntegrationHealthChecker::check(...)`, `proposalsEnabled` bool, queue public props on `RunTurnJob` used consistently across tasks.

**4. Scope:** single plan (shared health + AI config coupling). Phase 4 host work intentionally excluded.

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-08-07-host-integration-rearchitecture.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — execute tasks in this session with executing-plans checkpoints  

Which approach?
