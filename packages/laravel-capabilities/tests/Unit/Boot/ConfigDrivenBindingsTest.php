<?php

// REQ-023: Config-driven container bindings (UR-002). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('resolve is a pure function of config drivers and modes', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'audit' => ['driver' => 'memory', 'mode' => 'strict'],
        'idempotency' => ['driver' => 'memory'],
    ]);

    $resolved = ContainerBindings::resolve($config);

    expect($resolved)->toHaveKeys(['bindings', 'drivers', 'clients', 'surfaces', 'registry'])
        ->and($resolved['drivers']['approval_store']['requested'])->toBe('memory')
        ->and($resolved['drivers']['approval_store']['resolved'])->toBe('memory')
        ->and($resolved['drivers']['audit']['mode'])->toBe('strict')
        ->and($resolved['drivers']['idempotency']['resolved'])->toBe('memory')
        ->and($resolved['bindings'])->toHaveKey(CapabilityRegistry::class)
        ->and($resolved['registry'])->toBe(CapabilityRegistry::class);
});

it('plan(config) still lists abstracts and is driven by the same resolve path', function () {
    $config = CapabilitiesConfig::defaults();
    $plan = ContainerBindings::plan($config);
    $resolved = ContainerBindings::resolve($config);

    expect($plan)->toBeArray()
        ->and(array_keys($plan))->toEqual(array_keys($resolved['bindings']))
        ->and(ContainerBindings::abstracts($config))->toContain('CapabilityRegistry');
});

it('memory drivers construct in-memory stores and approval manager', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory', 'ttl_hours' => 12],
        'idempotency' => ['driver' => 'memory', 'ttl_hours' => 6],
        'audit' => ['driver' => 'memory', 'mode' => 'best_effort'],
    ]);

    $idempotency = ContainerBindings::makeIdempotencyStore($config);
    $approval = ContainerBindings::makeApprovalManager($config);
    $audit = ContainerBindings::makeAuditLogger($config);
    $registry = ContainerBindings::makeRegistry($config);

    expect($idempotency)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($idempotency)->toBeInstanceOf(IdempotencyStore::class)
        ->and($approval)->toBeInstanceOf(ApprovalManager::class)
        ->and($audit)->toBeInstanceOf(AuditLogger::class)
        ->and($registry)->toBeInstanceOf(CapabilityRegistry::class);
});

it('registry factory returns a shared empty map instance shape for discovery/fluent', function () {
    $config = CapabilitiesConfig::defaults();
    $a = ContainerBindings::makeRegistry($config);
    $b = ContainerBindings::makeRegistry($config);

    expect($a)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($b)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($a->all())->toBe([]);
});

it('database drivers resolve to Database store concretes without package_default fallback', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'audit' => ['driver' => 'database', 'mode' => 'best_effort'],
        'idempotency' => ['driver' => 'database'],
    ]);

    $resolved = ContainerBindings::resolve($config);

    expect($resolved['drivers']['approval_store']['requested'])->toBe('database')
        ->and($resolved['drivers']['approval_store']['resolved'])->toBe('database')
        ->and($resolved['drivers']['approval_store']['package_default'])->toBeFalse()
        ->and($resolved['drivers']['approval_store']['concrete'])->toBe(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and($resolved['drivers']['idempotency']['resolved'])->toBe('database')
        ->and($resolved['drivers']['idempotency']['package_default'])->toBeFalse()
        ->and($resolved['drivers']['idempotency']['concrete'])->toBe(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class);

    expect(ContainerBindings::makeIdempotencyStore($config))->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class)
        ->and(ContainerBindings::makeApprovalManager($config))->toBeInstanceOf(ApprovalManager::class)
        ->and(ContainerBindings::makeApprovalManager($config)->store())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class);
});

it('memory drivers still construct in-memory stores', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    $resolved = ContainerBindings::resolve($config);
    expect($resolved['drivers']['idempotency']['resolved'])->toBe('memory')
        ->and(ContainerBindings::makeIdempotencyStore($config))->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and(ContainerBindings::makeApprovalManager($config)->store())->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class);
});

it('unknown store driver fails closed with BootException', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'redis-unknown'],
    ]);

    expect(fn () => ContainerBindings::resolve($config))
        ->toThrow(BootException::class);
});

it('unknown audit mode fails closed', function () {
    $config = BootHelpers::config([
        'audit' => ['driver' => 'memory', 'mode' => 'yolo'],
    ]);

    expect(fn () => ContainerBindings::resolve($config))
        ->toThrow(BootException::class);
});

it('clients and surfaces from config appear on the resolved plan', function () {
    $config = BootHelpers::config([
        'clients' => [
            'token_abilities' => ['capabilities:cli' => 'cli', 'extra:ability' => 'agent'],
        ],
        'surfaces' => BootHelpers::surfaces(['http' => true, 'artisan' => false]),
    ]);

    $resolved = ContainerBindings::resolve($config);

    expect($resolved['clients']['token_abilities'])->toHaveKey('capabilities:cli')
        ->and($resolved['clients']['token_abilities']['extra:ability'])->toBe('agent')
        ->and($resolved['surfaces']['artisan']['enabled'] ?? true)->toBeFalse()
        ->and($resolved['surfaces']['http']['enabled'] ?? false)->toBeTrue();
});

it('provider register applies config-driven factories against a fake app', function () {
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

        public function alias(string $abstract, string $alias): void
        {
            $this->aliases[$alias] = $abstract;
        }

        public function make(string $abstract): mixed
        {
            if ($abstract === 'config') {
                return $this->config;
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
            return $key === 'config' || isset($this->singletons[$key]);
        }

        public function offsetSet(mixed $key, mixed $value): void
        {
            $this->singletons[(string) $key] = $value;
        }

        public function offsetUnset(mixed $key): void
        {
            unset($this->singletons[$key]);
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
            $prop->setAccessible(true);
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

    $registry = $app->make(CapabilityRegistry::class);
    $idempotency = $app->make(IdempotencyStore::class);
    $approval = $app->make(ApprovalManager::class);

    expect($registry)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($idempotency)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($approval)->toBeInstanceOf(ApprovalManager::class)
        ->and($app->make(CapabilityRegistry::class))->toBe($registry);
});
