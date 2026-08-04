<?php

// REQ-014: Service provider registration plan (BOOT-001 / SURF-003). Unit-only, no database.
// REQ-048: Registry / ApprovalManager / IdempotencyStore singleton store parity.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('happy: registers config merge [BOOT-001]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan();
    expect($plan['config_merged'])->toBeTrue()
        ->and(CapabilitiesConfig::defaults())->toHaveKeys(CapabilitiesConfig::TOP_LEVEL_KEYS);
});

it('happy: registers registry singleton [BOOT-001]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan();
    expect($plan['registry_singleton'])->toBeTrue()
        ->and($plan['bindings'])->toContain('CapabilityRegistry');
});

it('edge: registers routes when http enabled [BOOT-001]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['http' => true]),
    ]));
    expect($plan['routes'])->not->toBeEmpty()->and($plan['routes'])->toContain('invoke');
});

it('edge: registers commands when artisan enabled [BOOT-001]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['artisan' => true]),
    ]));
    expect($plan['commands'])->not->toBeEmpty();
});

it('fail: does not register AI tools when agent disabled [SURF-003]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['agent' => false]),
    ]), BootHelpers::probe());
    expect($plan['ai_tools'])->toBeEmpty()
        ->and($plan['surfaces'][SurfaceNames::AGENT])->toBeEmpty();
});

it('fail: does not register MCP tools when mcp disabled [SURF-003]', function () {
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['mcp' => false]),
    ]), BootHelpers::probe());
    expect($plan['mcp_tools'])->toBeEmpty()
        ->and($plan['surfaces'][SurfaceNames::MCP])->toBeEmpty();
});

// --- REQ-048: store singleton parity (invoke vs accept paths) ---

/**
 * Minimal ArrayAccess app that caches singleton factories like Laravel.
 *
 * @param  array<string, mixed>  $capabilitiesConfig
 * @return object{make: callable, singleton: callable, singletons: array}
 */
function req048FakeApp(array $capabilitiesConfig = []): object
{
    $configStore = new class
    {
        /** @var array<string, mixed> */
        public array $items = [];

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

        public function set(string $key, mixed $value): void
        {
            $this->items[$key] = $value;
        }
    };

    if ($capabilitiesConfig !== []) {
        $configStore->set('capabilities', $capabilitiesConfig);
    }

    $app = new class($configStore) implements ArrayAccess
    {
        public array $singletons = [];

        public array $resolved = [];

        public array $aliases = [];

        public function __construct(public object $config) {}

        public function singleton(string $abstract, mixed $concrete = null): void
        {
            $this->singletons[$abstract] = $concrete;
            unset($this->resolved[$abstract]);
        }

        public function instance(string $abstract, mixed $instance): void
        {
            $this->singletons[$abstract] = $instance;
            $this->resolved[$abstract] = $instance;
        }

        public function alias(string $abstract, string $alias): void
        {
            $this->aliases[$alias] = $abstract;
        }

        public function make(string $abstract): mixed
        {
            if ($abstract === 'config') {
                return $this->config;
            }

            // Follow alias chain like Laravel (aliases[$alias] = $abstract).
            $seen = [];
            while (isset($this->aliases[$abstract]) && ! isset($seen[$abstract])) {
                $seen[$abstract] = true;
                $abstract = $this->aliases[$abstract];
            }

            if (array_key_exists($abstract, $this->resolved)) {
                return $this->resolved[$abstract];
            }
            $entry = $this->singletons[$abstract] ?? null;
            if (is_callable($entry)) {
                $this->resolved[$abstract] = $entry($this);

                return $this->resolved[$abstract];
            }

            return $entry;
        }

        public function offsetGet(mixed $key): mixed
        {
            return $this->make((string) $key);
        }

        public function offsetExists(mixed $key): bool
        {
            if ($key === 'config' || isset($this->singletons[$key]) || isset($this->resolved[$key])) {
                return true;
            }
            $seen = [];
            $abstract = (string) $key;
            while (isset($this->aliases[$abstract]) && ! isset($seen[$abstract])) {
                $seen[$abstract] = true;
                $abstract = $this->aliases[$abstract];
                if (isset($this->singletons[$abstract]) || isset($this->resolved[$abstract])) {
                    return true;
                }
            }

            return false;
        }

        public function offsetSet(mixed $key, mixed $value): void
        {
            $this->singletons[(string) $key] = $value;
        }

        public function offsetUnset(mixed $key): void
        {
            unset($this->singletons[$key], $this->resolved[$key]);
        }

        public function runningInConsole(): bool
        {
            return false;
        }

        public function configurationIsCached(): bool
        {
            return false;
        }
    };

    $provider = new class($app) extends CapabilitiesServiceProvider
    {
        public function __construct(public object $fakeApp)
        {
            $ref = new ReflectionClass(CapabilitiesServiceProvider::class);
            $sp = $ref->getParentClass();
            $prop = $sp->getProperty('app');
            $prop->setValue($this, $fakeApp);
        }

        protected function publishes(array $paths, $group = null): void {}

        protected function mergeConfigFrom($path, $key): void
        {
            $config = $this->fakeApp->make('config');
            $existing = $config->get($key, []);
            $config->set($key, array_replace_recursive(require $path, is_array($existing) ? $existing : []));
        }
    };

    $provider->register();

    return $app;
}

it('REQ-048 memory: registry and ApprovalManager share the same approval store instance', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);

    expect($registry->approvalStore())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($approval->store())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($registry->approvalStore())->toBe($approval->store())
        ->and($registry->approvals()->store())->toBe($approval->store());
});

it('REQ-048 memory: registry and IdempotencyStore share the same store instance', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $registry = $app->make(CapabilityRegistry::class);
    $idempotency = $app->make(IdempotencyStore::class);

    expect($registry->idempotencyStore())->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($idempotency)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($registry->idempotencyStore())->toBe($idempotency);
});

it('REQ-048 database: registry and ApprovalManager share the same approval store instance', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]));

    $app->instance(TableGateway::class, new ArrayTableGateway);
    unset($app->resolved[CapabilityRegistry::class], $app->resolved[ApprovalManager::class], $app->resolved[IdempotencyStore::class]);

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);

    expect($registry->approvalStore())->toBeInstanceOf(DatabaseApprovalStore::class)
        ->and($approval->store())->toBeInstanceOf(DatabaseApprovalStore::class)
        ->and($registry->approvalStore())->toBe($approval->store());
});

it('REQ-048 database: registry and IdempotencyStore share the same store instance', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]));

    $app->instance(TableGateway::class, new ArrayTableGateway);
    unset($app->resolved[CapabilityRegistry::class], $app->resolved[ApprovalManager::class], $app->resolved[IdempotencyStore::class]);

    $registry = $app->make(CapabilityRegistry::class);
    $idempotency = $app->make(IdempotencyStore::class);

    expect($registry->idempotencyStore())->toBeInstanceOf(DatabaseIdempotencyStore::class)
        ->and($idempotency)->toBeInstanceOf(DatabaseIdempotencyStore::class)
        ->and($registry->idempotencyStore())->toBe($idempotency);
});

it('REQ-048: injected TableGateway is used by registry and ApprovalManager database stores', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]));

    $gateway = new ArrayTableGateway;
    $app->instance(TableGateway::class, $gateway);

    // Re-register store factories so they pick up the injected gateway on next resolve.
    // After SP register, resolve stores; if SP wires gateway first, instance() before make is enough
    // only when gateways are resolved lazily from container.
    unset($app->resolved[CapabilityRegistry::class], $app->resolved[ApprovalManager::class], $app->resolved[IdempotencyStore::class]);

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);

    $regStore = $registry->approvalStore();
    $mgrStore = $approval->store();
    expect($regStore)->toBe($mgrStore)
        ->and($regStore)->toBeInstanceOf(DatabaseApprovalStore::class);

    $tableProp = (new ReflectionClass(DatabaseApprovalStore::class))->getProperty('table');
    expect($tableProp->getValue($regStore))->toBe($gateway)
        ->and($tableProp->getValue($mgrStore))->toBe($gateway);
});

it('REQ-048: no silent re-create of in-memory stores across repeated singleton resolves', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $r1 = $app->make(CapabilityRegistry::class);
    $a1 = $app->make(ApprovalManager::class);
    $i1 = $app->make(IdempotencyStore::class);

    $r2 = $app->make(CapabilityRegistry::class);
    $a2 = $app->make(ApprovalManager::class);
    $i2 = $app->make(IdempotencyStore::class);

    expect($r2)->toBe($r1)
        ->and($a2)->toBe($a1)
        ->and($i2)->toBe($i1)
        ->and($r2->approvalStore())->toBe($a2->store())
        ->and($r2->idempotencyStore())->toBe($i2);
});

// --- REQ-057: CapabilityBus resolves to same singleton as CapabilityRegistry ---

it('REQ-057: CapabilityBus resolves to the same singleton instance as CapabilityRegistry', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $registry = $app->make(CapabilityRegistry::class);
    $bus = $app->make(CapabilityBus::class);
    $busAgain = $app->make(CapabilityBus::class);
    $stringAlias = $app->make('CapabilityBus');

    expect($bus)->toBe($registry)
        ->and($busAgain)->toBe($registry)
        ->and($stringAlias)->toBe($registry)
        ->and($bus)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($bus)->toBeInstanceOf(CapabilityBus::class)
        ->and($app->aliases[CapabilityBus::class] ?? null)->toBe(CapabilityRegistry::class)
        ->and($app->aliases['CapabilityBus'] ?? null)->toBe(CapabilityRegistry::class);
});
