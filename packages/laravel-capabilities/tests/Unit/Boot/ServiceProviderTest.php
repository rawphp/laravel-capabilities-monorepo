<?php

// REQ-014: Service provider registration plan (BOOT-001 / SURF-003). Unit-only, no database.
// REQ-048: Registry / ApprovalManager / IdempotencyStore singleton store parity.

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapter;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\SurfaceNames;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\Capabilities\Observability\InvokeTelemetry;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

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
            // Like Laravel: binding an instance replaces an alias of the same name.
            unset($this->aliases[$abstract]);
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

        public function bound(string $abstract): bool
        {
            return $this->offsetExists($abstract);
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

it('happy: ApprovalGateway resolves to the same ApprovalManager singleton [BOOT-001]', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $viaClass = $app->make(ApprovalManager::class);
    $viaGateway = $app->make(ApprovalGateway::class);
    $viaString = $app->make('ApprovalGateway');

    expect($viaGateway)->toBe($viaClass)
        ->and($viaString)->toBe($viaClass)
        ->and($viaGateway)->toBeInstanceOf(ApprovalGateway::class);
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

// --- D-006: container ApprovalManager (HTTP approve route, messaging gateway) runs the capability ---

it('D-006: container ApprovalManager accept runs the capability through the registry', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    // Accept re-authorizes the original requester, so the default guard must rehydrate them.
    $app->instance('auth', oaaProviderAuth(oaaRehydratingGuard()));

    $registry = $app->make(CapabilityRegistry::class);
    $runs = 0;
    Capability::define('ship-order')
        ->description('ship an order')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(function () use (&$runs) {
            $runs++;

            return new CreateInvoiceResult(invoice_id: 5);
        })
        ->register($registry);

    $pending = $registry->invoke('ship-order', PipelineHelpers::validInput(), PipelineHelpers::options('http', [
        'needs_approval' => true,
    ]));
    $approver = PipelineHelpers::userActor(7);
    $approver->tenant_id = 't-1';
    $result = $app->make(ApprovalManager::class)->accept((string) $pending->approvalId(), $approver);

    expect($result->isOk())->toBeTrue()
        ->and($runs)->toBe(1)
        ->and($app->make(ApprovalManager::class)->store()->find((string) $pending->approvalId())['result_status'])->toBe('ok');
});

// --- Re-validation on accept: provider wires the original-actor re-check ---

/**
 * @return array{0: object, 1: string}
 */
function oaaProviderPendingApproval(?object $auth = null): array
{
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    if ($auth !== null) {
        $app->instance('auth', $auth);
    }

    $registry = $app->make(CapabilityRegistry::class);
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (mixed $input, $ctx): bool => $ctx->actor()->id === '7')
        ->run(static fn () => 'never')
        ->register($registry);

    $row = $app->make(ApprovalManager::class)->request([
        'capability_name' => 'create-invoice',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
        'input_json' => ['customer_id' => 1, 'amount_cents' => 500, 'currency' => 'AUD'],
    ]);

    return [$app, (string) $row['id']];
}

function oaaProviderAuth(?object $guard): object
{
    return new class($guard)
    {
        public function __construct(private ?object $guard) {}

        public function guard(): ?object
        {
            return $this->guard;
        }
    };
}

function oaaRehydratingGuard(): object
{
    return new class
    {
        public function getProvider(): object
        {
            return new class
            {
                public function retrieveById(mixed $id): object
                {
                    $user = new stdClass;
                    $user->id = (string) $id;

                    return $user;
                }
            };
        }
    };
}

function oaaProviderApprover(): object
{
    $user = new stdClass;
    $user->id = '7';

    return $user;
}

it('fail: provider-wired accept denies when the original requester cannot be rehydrated', function () {
    [$app, $id] = oaaProviderPendingApproval();

    $result = $app->make(ApprovalManager::class)->accept($id, oaaProviderApprover());

    expect($result->isOk())->toBeFalse()
        ->and($result->toArray()['error']['code'])->toBe('forbidden');
});

it('fail: provider-wired accept denies when the default guard exposes no user provider', function () {
    [$app, $id] = oaaProviderPendingApproval(oaaProviderAuth(new stdClass));

    $result = $app->make(ApprovalManager::class)->accept($id, oaaProviderApprover());

    expect($result->toArray()['error']['code'] ?? null)->toBe('forbidden');
});

it('happy: provider-wired accept re-authorizes the requester via the default auth user provider', function () {
    $looked = [];
    $provider = new class($looked)
    {
        public function __construct(public array &$looked) {}

        public function retrieveById(mixed $id): ?object
        {
            $this->looked[] = $id;
            $user = new stdClass;
            $user->id = (string) $id;

            return $user;
        }
    };
    $guard = new class($provider)
    {
        public function __construct(private object $provider) {}

        public function getProvider(): object
        {
            return $this->provider;
        }
    };
    [$app, $id] = oaaProviderPendingApproval(oaaProviderAuth($guard));

    $result = $app->make(ApprovalManager::class)->accept($id, oaaProviderApprover());

    // Looked up twice: once for the accept re-check, once to run as the real requester (L-005).
    expect($result->isOk())->toBeTrue()
        ->and($looked)->toBe(['7', '7']);
});

it('happy: provider-wired approved execution runs as the rehydrated user, not a stub [L-005]', function () {
    $provider = new class
    {
        public function retrieveById(mixed $id): ?object
        {
            return new class((string) $id)
            {
                public function __construct(public string $id) {}

                public function can(string $ability): bool
                {
                    return true;
                }
            };
        }
    };
    $guard = new class($provider)
    {
        public function __construct(private object $provider) {}

        public function getProvider(): object
        {
            return $this->provider;
        }
    };
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    $app->instance('auth', oaaProviderAuth($guard));
    $registry = $app->make(CapabilityRegistry::class);
    $runActors = [];
    $registry->define('create-invoice')
        ->input(CreateInvoiceInput::class)
        ->authorize(static fn (mixed $input, $ctx): bool => $ctx->actor()->can('create'))
        ->run(function (mixed $input, $ctx) use (&$runActors) {
            $runActors[] = $ctx->actor();

            return ['ok' => true];
        })
        ->register($registry);
    $row = $app->make(ApprovalManager::class)->request([
        'capability_name' => 'create-invoice',
        'requester_actor_type' => 'user',
        'requester_actor_id' => '7',
        'original_caller' => 'http',
        'input_json' => ['customer_id' => 1, 'amount_cents' => 500, 'currency' => 'AUD'],
    ]);

    $result = $app->make(ApprovalManager::class)->accept((string) $row['id'], oaaProviderApprover());

    expect($result->isOk())->toBeTrue()
        ->and($runActors)->toHaveCount(1)
        ->and($runActors[0])->not->toBeInstanceOf(stdClass::class)
        ->and($runActors[0]->id)->toBe('7');
});

it('M-301: the host-bound ScopeResolver stamps the row and places the approver, so an in-tenant accept succeeds', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    $app->instance('auth', oaaProviderAuth(oaaRehydratingGuard()));
    // Host tenancy: user 7 belongs to acme, user 8 to globex. No tenant attributes on the principals.
    $app->instance(ScopeResolver::class, new DefaultScopeResolver(['user_tenants' => ['7' => 'acme', '8' => 'globex']]));

    $registry = $app->make(CapabilityRegistry::class);
    $runs = 0;
    Capability::define('ship-order')
        ->description('ship an order')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->approvalPolicy('requester')
        ->run(function () use (&$runs) {
            $runs++;

            return new CreateInvoiceResult(invoice_id: 5);
        })
        ->register($registry);

    $pending = $registry->invoke('ship-order', PipelineHelpers::validInput(), [
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(7),
        'needs_approval' => true,
    ]);
    $id = (string) $pending->approvalId();
    $approvals = $app->make(ApprovalManager::class);

    $otherTenant = $approvals->accept($id, PipelineHelpers::userActor(8));
    $sameTenant = $approvals->accept($id, PipelineHelpers::userActor(7));

    expect($approvals->find($id)['tenant_id'])->toBe('acme')
        ->and($otherTenant->errorCode())->toBe('forbidden')
        ->and($sameTenant->isOk())->toBeTrue()
        ->and($runs)->toBe(1);
});

// --- D-019: provider-built CapabilityController counts unauthenticated denials on the bound Metrics ---

it('D-019: container CapabilityController records unauthenticated denials on the Metrics singleton', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));

    $controller = $app->make(CapabilityController::class);
    $controller->list(HttpHelpers::guestRequest());

    expect($app->make(Metrics::class)->get(
        InvokeTelemetry::METRIC_UNAUTHENTICATED,
        ['route' => 'list', 'auth' => 'none'],
    ))->toBe(1);
});

// --- L-011: idempotency.header is the one header setting ---

it('happy: the container CapabilityController reads the key from idempotency.header [L-011]', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory', 'header' => 'X-Idem'],
    ]));
    $registry = $app->make(CapabilityRegistry::class);
    Capability::define('idem-header')
        ->description('header wiring')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);
    $bus = new FakeCapabilityBus(backing: $registry);
    $app->instance(CapabilityBus::class, $bus);

    $app->make(CapabilityController::class)->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => PipelineHelpers::validInput(),
        'headers' => ['x-idem' => str_repeat('k', 16)],
    ]), 'idem-header');

    expect($bus->invocations[0]['options']['idempotency_key'] ?? null)->toBe(str_repeat('k', 16));
});

// --- L-017: tool adapters take require_profile from config ---

it('happy: the container adapters take require_profile from surfaces.agent / surfaces.mcp [L-017]', function () {
    $strict = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'surfaces' => [
            'agent' => ['enabled' => true, 'on_incompatible' => 'disable'],
            'mcp' => ['enabled' => true, 'on_incompatible' => 'disable', 'auth' => ['user_pat' => true]],
        ],
    ]));
    $relaxed = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'surfaces' => [
            'agent' => ['enabled' => true, 'on_incompatible' => 'disable', 'require_profile' => false],
            'mcp' => ['enabled' => true, 'on_incompatible' => 'disable', 'require_profile' => false, 'auth' => ['user_pat' => true]],
        ],
    ]));

    foreach ([$strict, $relaxed] as $app) {
        expect($app->make(AiToolAdapter::class))->toBeInstanceOf(AiToolAdapterV1::class)
            ->and($app->make(McpToolAdapter::class))->toBeInstanceOf(McpToolAdapterV1::class);
    }

    $user = AdapterHelpers::user();
    expect($strict->make(AiToolAdapter::class)->handle('missing-cap', [], $user)->error['normalized_code'] ?? null)->toBe('profile_required')
        ->and($strict->make(McpToolAdapter::class)->handle('missing-cap', [], McpCredential::userPat($user))->error['normalized_code'] ?? null)->toBe('profile_required')
        ->and($relaxed->make(AiToolAdapter::class)->handle('missing-cap', [], $user)->errorCode())->toBe('not_found')
        ->and($relaxed->make(McpToolAdapter::class)->handle('missing-cap', [], McpCredential::userPat($user))->errorCode())->toBe('not_found');
});

it('happy: register merges the config defaults and binds resolvable Metrics and Tracer factories', function () {
    $app = FakeProviderApp::registered();

    expect($app->singletons)->not->toBeEmpty()
        ->and($app->config->get('capabilities'))->toBeArray()
        ->and($app->singletons[Metrics::class])->toBeCallable()
        ->and($app->make(Metrics::class))->toBeInstanceOf(Metrics::class)
        ->and($app->singletons[Tracer::class])->toBeCallable()
        ->and($app->make(Tracer::class))->toBeInstanceOf(Tracer::class);
});

it('happy: boot publishes the config and migrations when running in console', function () {
    $app = new class extends Container
    {
        public function runningInConsole(): bool
        {
            return true;
        }
    };
    $app->instance('config', new class(BootHelpers::config([]))
    {
        /** @param  array<string, mixed>  $config */
        public function __construct(private array $config) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'capabilities' ? $this->config : $default;
        }
    });

    if (! function_exists('config_path')) {
        eval('function config_path($path = "") { return "/tmp/config/".$path; }');
    }
    if (! function_exists('database_path')) {
        eval('function database_path($path = "") { return "/tmp/database/".$path; }');
    }

    $provider = new class($app) extends CapabilitiesServiceProvider
    {
        /** @var list<array{paths: array<string, string>, group: string}> */
        public array $publishCalls = [];

        /**
         * @param  array<string, string>  $paths
         */
        protected function publishes(array $paths, $group = null): void
        {
            $this->publishCalls[] = ['paths' => $paths, 'group' => (string) $group];
        }
    };
    $provider->boot();

    expect(array_column($provider->publishCalls, 'group'))->toBe(['capabilities-config', 'capabilities-migrations']);
});

// REQ-528: a container-bound Authorizer is host-bound (a gate); an unbound one is not (L-003).
function req528GateCapability(CapabilityRegistry $registry, object $counter): void
{
    Capability::define('gate-cap')
        ->description('gate test')
        ->surfaces(['http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->allowSystemCallers(true)
        ->authorize(function () use ($counter): bool {
            $counter->own++;

            return true;
        })
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);
}

it('REQ-528: a container-bound Authorizer becomes a host gate on the registry', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    $host = new class implements Authorizer
    {
        public int $calls = 0;

        public function authorize(string $capability, mixed $input, mixed $context): bool
        {
            $this->calls++;

            return false;
        }
    };
    $app->instance(Authorizer::class, $host);
    $counter = (object) ['own' => 0];

    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);
    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('forbidden')
        ->and($host->calls)->toBe(1)
        ->and($counter->own)->toBe(0);
});

it('REQ-528: with no container-bound Authorizer the capability own rule alone decides', function () {
    $app = req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
    $counter = (object) ['own' => 0];

    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);
    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($counter->own)->toBe(1);
});

function req528Denier(): Authorizer
{
    return new class implements Authorizer
    {
        public int $calls = 0;

        public function authorize(string $capability, mixed $input, mixed $context): bool
        {
            $this->calls++;

            return false;
        }
    };
}

function req528LateApp(): object
{
    return req048FakeApp(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]));
}

it('REQ-528: an Authorizer bound after the registry was built still gates the first invoke', function () {
    $app = req528LateApp();
    $counter = (object) ['own' => 0];
    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);

    $host = req528Denier();
    $app->instance(Authorizer::class, $host);

    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('forbidden')
        ->and($host->calls)->toBe(1)
        ->and($counter->own)->toBe(0)
        ->and($registry->hostAuthorizerApplies())->toBeTrue();
});

it('REQ-528: an explicit withAuthorizer() beats the container binding', function () {
    $app = req528LateApp();
    $counter = (object) ['own' => 0];
    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);
    $bound = req528Denier();
    $app->instance(Authorizer::class, $bound);
    $registry->withAuthorizer(StubAuthorizer::allow());

    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($bound->calls)->toBe(0)
        ->and($counter->own)->toBe(1);
});

it('REQ-528: no binding at all means no host gate, and hostAuthorizerApplies() is false', function () {
    $registry = req528LateApp()->make(CapabilityRegistry::class);

    expect($registry->hostAuthorizerApplies())->toBeFalse();
});

it('REQ-528: a container binding that throws fails closed; the capability never runs', function () {
    $app = req528LateApp();
    $counter = (object) ['own' => 0];
    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);
    $app->singleton(Authorizer::class, static function (): never {
        throw new RuntimeException('authorizer cannot be built');
    });
    $context = new CapabilityContext(caller: 'http', actor: PipelineHelpers::userActor(), scope: new CapabilityScope(tenantId: 't-1'));

    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeFalse()
        ->and($counter->own)->toBe(0)
        ->and($registry->authorizes('gate-cap', PipelineHelpers::validInput(), $context))->toBeFalse()
        ->and(fn () => $registry->hostAuthorizerApplies())->toThrow(RuntimeException::class, 'authorizer cannot be built');
});

it('REQ-528: a binding that resolves to a non-Authorizer fails closed with a clear message', function () {
    $app = req528LateApp();
    $counter = (object) ['own' => 0];
    $registry = $app->make(CapabilityRegistry::class);
    req528GateCapability($registry, $counter);
    $app->instance(Authorizer::class, new stdClass);

    $result = $registry->invoke('gate-cap', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeFalse()
        ->and($counter->own)->toBe(0)
        ->and(fn () => $registry->hostAuthorizerApplies())->toThrow(UnexpectedValueException::class, 'stdClass');
});
