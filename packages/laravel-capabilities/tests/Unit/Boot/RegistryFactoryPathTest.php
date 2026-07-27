<?php

// REQ-046: Config-wired registry path closure. Unit-only.
// Children: REQ-047 (makeRegistry wiring) + REQ-048 (store singleton parity).
// Filter: RegistryFactory

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemClock;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

/**
 * Minimal ArrayAccess app that caches singleton factories like Laravel (path SP boot).
 *
 * @param  array<string, mixed>  $capabilitiesConfig
 * @return object{make: callable, singleton: callable, instance: callable}
 */
function req046FakeApp(array $capabilitiesConfig = []): object
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
            return $key === 'config' || isset($this->singletons[$key]) || isset($this->resolved[$key]);
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

it('RegistryFactory path: dual independent construction diverges (anti-pattern risk)', function () {
    // Challenger residual: calling makeRegistry and makeApprovalManager / makeIdempotencyStore
    // independently each allocates new store instances — accept vs invoke would not share durability.
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);

    $standaloneRegistry = ContainerBindings::makeRegistry($config);
    $standaloneApproval = ContainerBindings::makeApprovalManager($config);
    $standaloneIdem = ContainerBindings::makeIdempotencyStore($config);

    expect($standaloneRegistry->approvalStore())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($standaloneApproval->store())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($standaloneRegistry->approvalStore())->not->toBe($standaloneApproval->store())
        ->and($standaloneRegistry->idempotencyStore())->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($standaloneIdem)->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($standaloneRegistry->idempotencyStore())->not->toBe($standaloneIdem);
});

it('RegistryFactory path: container-resolved registry shares stores and applies listed config domains', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => [
            'enabled' => true,
            'mode' => 'strict',
            'required' => false,
            'driver' => 'database',
        ],
        'rate_limits' => [
            'enabled' => false,
            'defaults' => ['per_minute' => 9],
        ],
        'validation' => ['validate_output' => false],
        'transactions' => ['wrap_run' => true],
        'events' => ['enabled' => false],
        'surfaces' => array_replace_recursive(
            BootHelpers::surfaces([
                'http' => false,
                'cli' => true,
                'agent' => true,
                'artisan' => false,
            ]),
            [
                'agent' => [
                    'profiles' => ['ops' => ['create-invoice']],
                    'require_profile' => true,
                    'max_tools_warn' => 7,
                ],
            ],
        ),
    ]);

    $app = req046FakeApp($config);

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);
    $idempotency = $app->make(IdempotencyStore::class);

    // Shared store identity (REQ-048) — invoke and accept cannot diverge.
    expect($registry->approvalStore())->toBe($approval->store())
        ->and($registry->approvals()->store())->toBe($approval->store())
        ->and($registry->idempotencyStore())->toBe($idempotency);

    // Listed config domains applied (REQ-047).
    $global = $registry->globallyEnabledSurfaces();
    expect($global['http'])->toBeFalse()
        ->and($global['cli'])->toBeTrue()
        ->and($global['agent'])->toBeTrue()
        ->and($global['artisan'])->toBeFalse()
        ->and($global)->toEqual(CapabilitiesConfig::globallyEnabledSurfaces($config))
        ->and($registry->approvalStore())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($registry->idempotencyStore())->toBeInstanceOf(InMemoryIdempotencyStore::class)
        ->and($registry->scopeResolver())->toBeInstanceOf(DefaultScopeResolver::class)
        ->and($registry->auditMode())->toBe('strict')
        ->and($registry->auditEnabled())->toBeTrue()
        ->and($registry->auditRequired())->toBeFalse()
        ->and($registry->auditDriver())->toBe('database')
        ->and($registry->rateLimitConfig()['enabled'])->toBeFalse()
        ->and($registry->rateLimitConfig()['defaults']['per_minute'])->toBe(9)
        ->and($registry->validateOutputEnabled())->toBeFalse()
        ->and($registry->transactionsWrapRun())->toBeTrue()
        ->and($registry->eventsEnabled())->toBeFalse()
        ->and($registry->toolSurfaceConfig()['agent']['profiles']['ops'] ?? null)->toBe(['create-invoice'])
        ->and($registry->toolSurfaceConfig()['agent']['require_profile'] ?? null)->toBeTrue()
        ->and($registry->toolSurfaceConfig()['agent']['max_tools_warn'] ?? null)->toBe(7)
        ->and($registry->clock())->toBeInstanceOf(SystemClock::class);
});

it('RegistryFactory path: database drivers share gateway-backed stores via container', function () {
    $config = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
        'audit' => ['mode' => 'best_effort', 'driver' => 'memory'],
    ]);

    $app = req046FakeApp($config);
    $gateway = new ArrayTableGateway;
    $app->instance(TableGateway::class, $gateway);
    unset(
        $app->resolved[CapabilityRegistry::class],
        $app->resolved[ApprovalManager::class],
        $app->resolved[IdempotencyStore::class],
    );

    $registry = $app->make(CapabilityRegistry::class);
    $approval = $app->make(ApprovalManager::class);
    $idempotency = $app->make(IdempotencyStore::class);

    expect($registry->approvalStore())->toBe($approval->store())
        ->and($registry->idempotencyStore())->toBe($idempotency)
        ->and($registry->approvalStore())->toBeInstanceOf(DatabaseApprovalStore::class)
        ->and($registry->idempotencyStore())->toBeInstanceOf(DatabaseIdempotencyStore::class);

    $tableProp = (new ReflectionClass(DatabaseApprovalStore::class))->getProperty('table');
    expect($tableProp->getValue($registry->approvalStore()))->toBe($gateway)
        ->and($tableProp->getValue($approval->store()))->toBe($gateway);

    $idemTable = (new ReflectionClass(DatabaseIdempotencyStore::class))->getProperty('table');
    expect($idemTable->getValue($registry->idempotencyStore()))->toBe($gateway)
        ->and($idemTable->getValue($idempotency))->toBe($gateway);
});

it('RegistryFactory path: prebuilt store inject closes dual-manager divergence', function () {
    // Failing anti-pattern (above) → passing inject path used by CapabilitiesServiceProvider.
    $config = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    $approval = ContainerBindings::makeApprovalManager($config);
    $idem = ContainerBindings::makeIdempotencyStore($config);
    $registry = ContainerBindings::makeRegistry(
        $config,
        approvalStore: $approval->store(),
        idempotencyStore: $idem,
    );

    expect($registry->approvalStore())->toBe($approval->store())
        ->and($registry->idempotencyStore())->toBe($idem)
        ->and($registry->approvals()->store())->toBe($approval->store());
});
