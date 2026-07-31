<?php

// REQ-023: Config-driven container bindings (UR-002). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemClock;
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
        ->and($registry)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($registry->clock())->toBeInstanceOf(SystemClock::class);
});

it('registry factory returns a shared empty map instance shape for discovery/fluent', function () {
    // Package defaults: approval.store=database, rate_limits.driver=cache.
    // Unit path: ArrayTableGateway + memory rate limiter (REQ-051 / L-008).
    $config = array_replace_recursive(CapabilitiesConfig::defaults(), [
        'rate_limits' => ['driver' => 'memory'],
    ]);
    $gw = new ArrayTableGateway;
    $a = ContainerBindings::makeRegistry($config, $gw);
    $b = ContainerBindings::makeRegistry($config, $gw);

    expect($a)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($b)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($a->all())->toBe([])
        ->and($a->clock())->toBeInstanceOf(SystemClock::class)
        ->and($b->clock())->toBeInstanceOf(SystemClock::class);
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

    $gw = new ArrayTableGateway;
    expect(ContainerBindings::makeIdempotencyStore($config, $gw))->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class)
        ->and(ContainerBindings::makeApprovalManager($config, $gw))->toBeInstanceOf(ApprovalManager::class)
        ->and(ContainerBindings::makeApprovalManager($config, $gw)->store())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class);
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

// REQ-047: makeRegistry full config wiring — fail when config is ignored.

it('makeRegistry applies globally enabled surfaces from surfaces.*.enabled', function () {
    $config = BootHelpers::config([
        'surfaces' => BootHelpers::surfaces([
            'http' => false,
            'artisan' => false,
            'messaging' => false,
            'agent' => true,
        ]),
    ]);

    $registry = ContainerBindings::makeRegistry($config, new ArrayTableGateway);
    $global = $registry->globallyEnabledSurfaces();

    expect($global['http'])->toBeFalse()
        ->and($global['artisan'])->toBeFalse()
        ->and($global['agent'])->toBeTrue()
        ->and($global)->toEqual(CapabilitiesConfig::globallyEnabledSurfaces($config));
});

it('makeRegistry injects approval store matching makeApprovalManager driver', function () {
    // Pair memory approval with memory idempotency — package default idempotency is now database (REQ-070).
    $memory = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    $database = BootHelpers::config(['approval' => ['store' => 'database']]);
    $gw = new ArrayTableGateway;

    $memReg = ContainerBindings::makeRegistry($memory);
    $dbReg = ContainerBindings::makeRegistry($database, $gw);

    expect($memReg->approvals()->store())->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class)
        ->and($memReg->approvalStore())->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class)
        ->and(ContainerBindings::makeApprovalManager($memory)->store())
        ->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class)
        ->and($dbReg->approvals()->store())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and($dbReg->approvalStore())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and(ContainerBindings::makeApprovalManager($database, $gw)->store())
        ->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class);
});

it('makeRegistry injects idempotency store matching makeIdempotencyStore driver', function () {
    $memory = BootHelpers::config(['idempotency' => ['driver' => 'memory'], 'approval' => ['store' => 'memory']]);
    $database = BootHelpers::config(['idempotency' => ['driver' => 'database'], 'approval' => ['store' => 'memory']]);
    $gw = new ArrayTableGateway;

    $memReg = ContainerBindings::makeRegistry($memory);
    $dbReg = ContainerBindings::makeRegistry($database, $gw);

    expect($memReg->idempotencyStore())->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and(ContainerBindings::makeIdempotencyStore($memory))->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($dbReg->idempotencyStore())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class)
        ->and(ContainerBindings::makeIdempotencyStore($database, $gw))
        ->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class);
});

it('makeRegistry applies audit mode/enabled/required/driver via registry APIs', function () {
    $config = BootHelpers::config([
        'audit' => [
            'enabled' => false,
            'mode' => 'strict',
            'required' => true,
            // boot resolve accepts memory|database; registry maps database → database
            'driver' => 'database',
        ],
    ]);

    $registry = ContainerBindings::makeRegistry($config, new ArrayTableGateway);

    expect($registry->auditEnabled())->toBeFalse()
        ->and($registry->auditMode())->toBe('strict')
        ->and($registry->auditRequired())->toBeTrue()
        ->and($registry->auditDriver())->toBe('database');
});

it('makeRegistry injects DefaultScopeResolver', function () {
    $config = array_replace_recursive(CapabilitiesConfig::defaults(), [
        'rate_limits' => ['driver' => 'memory'],
    ]);
    $registry = ContainerBindings::makeRegistry(
        $config,
        new ArrayTableGateway,
    );

    expect($registry->scopeResolver())->toBeInstanceOf(\Rawphp\Capabilities\Support\DefaultScopeResolver::class);
});

it('makeRegistry applies rate limit, validation, transactions, events, and tool surface config', function () {
    $config = BootHelpers::config([
        'rate_limits' => [
            'enabled' => false,
            'defaults' => ['per_minute' => 12, 'per_capability_per_minute' => 3],
            'agent_turn' => ['max_tool_calls' => 4],
        ],
        'validation' => ['validate_output' => false],
        'transactions' => ['wrap_run' => true],
        'events' => ['enabled' => false],
        'surfaces' => [
            'agent' => [
                'enabled' => true,
                'profiles' => ['ops' => ['create-invoice']],
                'require_profile' => false,
                'max_tools_warn' => 8,
                'max_tools_hard' => 16,
                'max_tool_calls_per_turn' => 2,
            ],
            'mcp' => [
                'enabled' => true,
                'profiles' => ['read' => ['list-invoices']],
                'require_profile' => true,
                'max_tools_warn' => 10,
                'max_tools_hard' => 20,
            ],
        ],
    ]);

    $registry = ContainerBindings::makeRegistry($config, new ArrayTableGateway);

    expect($registry->rateLimitConfig()['enabled'])->toBeFalse()
        ->and($registry->rateLimitConfig()['defaults']['per_minute'])->toBe(12)
        ->and($registry->rateLimitConfig()['agent_turn']['max_tool_calls'])->toBe(4)
        ->and($registry->validateOutputEnabled())->toBeFalse()
        ->and($registry->transactionsWrapRun())->toBeTrue()
        ->and($registry->eventsEnabled())->toBeFalse()
        ->and($registry->toolSurfaceConfig()['agent']['profiles']['ops'] ?? null)->toBe(['create-invoice'])
        ->and($registry->toolSurfaceConfig()['agent']['require_profile'] ?? null)->toBeFalse()
        ->and($registry->toolSurfaceConfig()['agent']['max_tools_warn'] ?? null)->toBe(8)
        ->and($registry->toolSurfaceConfig()['mcp']['profiles']['read'] ?? null)->toBe(['list-invoices'])
        ->and($registry->toolSurfaceConfig()['mcp']['max_tools_hard'] ?? null)->toBe(20);
});

it('makeRegistry clock remains SystemClock by default', function () {
    $registry = ContainerBindings::makeRegistry(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    expect($registry->clock())->toBeInstanceOf(SystemClock::class);
});

it('challenger: mixed approval.database + idempotency.memory drivers without inventing a second store type', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory', 'mode' => 'best_effort'],
    ]);
    $gw = new ArrayTableGateway;

    $registry = ContainerBindings::makeRegistry($config, $gw);
    $approval = ContainerBindings::makeApprovalManager($config, $gw);
    $idempotency = ContainerBindings::makeIdempotencyStore($config);

    expect($registry->approvalStore())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and($registry->approvals()->store())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and($approval->store())->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseApprovalStore::class)
        ->and($registry->idempotencyStore())->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($idempotency)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($registry->idempotencyStore())->not->toBeInstanceOf(\Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore::class)
        ->and($registry->approvalStore())->not->toBeInstanceOf(\Rawphp\Capabilities\Support\InMemoryApprovalStore::class);
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

    // Unit path: force memory drivers so SP does not require DB/cache (REQ-051 / L-008).
    $merged = $app->make('config')->get('capabilities', []);
    $merged['approval']['store'] = 'memory';
    $merged['idempotency']['driver'] = 'memory';
    $merged['rate_limits']['driver'] = 'memory';
    $app->make('config')->set('capabilities', $merged);

    $registry = $app->make(CapabilityRegistry::class);
    $idempotency = $app->make(IdempotencyStore::class);
    $approval = $app->make(ApprovalManager::class);

    expect($registry)->toBeInstanceOf(CapabilityRegistry::class)
        ->and($idempotency)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($approval)->toBeInstanceOf(ApprovalManager::class)
        ->and($app->make(CapabilityRegistry::class))->toBe($registry);
});
