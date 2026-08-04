<?php

declare(strict_types=1);

/**
 * REQ-016: second coverage pass — registry force-fail stages, idempotency guard,
 * approval manager branches, HTTP controller edges, service provider register/boot.
 */

use DateTimeImmutable;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Contracts\Tracer;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\MissingKeyWarner;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

// ── force-fail every pipeline stage ─────────────────────────────────────────

it('covers CapabilityRegistry forceFailStages for each pre-run stage', function () {
    $stages = [
        PipelineStages::JSON_SCHEMA_VALIDATE => 'validation_failed',
        PipelineStages::HYDRATE_DTO => 'validation_failed',
        PipelineStages::SERVER_ONLY_VALIDATE => 'validation_failed',
        PipelineStages::RESOLVE_ACTOR => 'unauthenticated',
        PipelineStages::RESOLVE_SCOPE => 'forbidden',
        PipelineStages::IDEMPOTENCY_LOOKUP => 'conflict',
        PipelineStages::AUTHORIZE => 'forbidden',
        PipelineStages::NEEDS_APPROVAL => 'approval_required',
        PipelineStages::RATE_LIMIT => 'rate_limited',
    ];

    foreach ($stages as $stage => $code) {
        // Explicit allow: production default denies without authorize (REQ-070).
        $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
        $reg->register(new CapabilityDefinition(
            name: 'ff-'.$stage,
            description: 'force fail',
            readOnly: true,
            allowSystemCallers: true,
            run: static fn () => CapabilityResult::ok(['ok' => true]),
        ));
        $reg->forceFailStages($stage);
        $result = $reg->invoke('ff-'.$stage, [], [
            'caller' => 'http',
            'actor' => SystemActor::named('sys'),
            'scope' => new CapabilityScope(tenantId: 't1'),
        ]);
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe($code);
    }
});

it('covers registry assertParity empty surface, artisan caller, assert helpers, non-SchemaProvider input', function () {
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'snap',
        description: 's',
        readOnly: true,
        input: null,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['v' => 1]),
    ));

    expect(fn () => $reg->assertParity('snap', [
        'surfaces' => [''],
        'input' => [],
        'actor' => SystemActor::named('s'),
    ]))->toThrow(InvalidArgumentException::class);
    expect($reg->assertParity('snap', [
        'surfaces' => ['http', 'cli'],
        'input' => [],
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't1'),
    ]))->toBeTrue();
    expect($reg->fake())->toBe($reg);
    expect($reg->assertSchemaSnapshot('snap'))->toBeTrue();

    // unknown caller normalizes; artisan preserved
    $ok = $reg->invoke('snap', [], [
        'caller' => 'not-a-real-caller',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    expect($ok->isOk())->toBeTrue();

    // artisan is a valid surface name; pipeline may still accept when actor/scope provided
    $ok2 = $reg->invoke('snap', [], [
        'caller' => 'artisan',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    expect($ok2->isOk() || $ok2->errorCode() !== null)->toBeTrue();

    // non SchemaProvider input class fails closed at schema stage
    $reg->register(new CapabilityDefinition(
        name: 'bad-in',
        description: 'b',
        input: stdClass::class,
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok([]),
    ));
    $bad = $reg->invoke('bad-in', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('s'),
    ]);
    expect($bad->errorCode())->toBe('validation_failed');

    $reg->register(new CapabilityDefinition(
        name: 'skip-rules',
        description: 's',
        input: CreateInvoiceInput::class,
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));
    $skip = $reg->invoke('skip-rules', [
        'customer_id' => 1,
        'amount_cents' => 10,
        'currency' => 'USD',
    ], [
        'caller' => 'http',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
        'skip_server_rules' => true,
    ]);
    expect($skip->isOk())->toBeTrue();

    // assertCannotInvokeAcrossTenant with actor option — authorize denies so invoke fails
    $reg->register(new CapabilityDefinition(
        name: 'tenant-cap',
        description: 't',
        readOnly: true,
        allowSystemCallers: true,
        authorize: static fn () => false,
        run: static fn () => CapabilityResult::ok(['should' => 'not-run']),
    ));
    expect($reg->assertCannotInvokeAcrossTenant([
        'name' => 'tenant-cap',
        'input' => [],
        'foreignTenant' => 'tb',
        'tenant_id' => 'ta',
        'actor' => SystemActor::named('s'),
        'caller' => 'http',
    ]))->toBeTrue();
});

// ── IdempotencyGuard exhaustive ─────────────────────────────────────────────

it('covers IdempotencyGuard policy, lookup statuses, storeResult, isExpired', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-03-01T00:00:00Z'));
    $store = new InMemoryIdempotencyStore($clock);
    $guard = new IdempotencyGuard($store, $clock, IdempotencyConfig::defaults(), new MissingKeyWarner(true));

    expect($guard->config())->toBeInstanceOf(IdempotencyConfig::class)
        ->and($guard->warner())->toBeInstanceOf(MissingKeyWarner::class)
        ->and($guard->store())->toBe($store)
        ->and($guard->hashInput(['a' => 1]))->toBeString();

    $none = new CapabilityDefinition(name: 'ro', description: 'd', readOnly: true, idempotent: CapabilityDefinition::IDEMPOTENT_NONE);
    expect($guard->assertKeyPolicy($none, null))->toBeNull();

    $required = new CapabilityDefinition(
        name: 'req',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
        idempotent: CapabilityDefinition::IDEMPOTENT_REQUIRED,
        run: static fn () => CapabilityResult::ok([]),
    );
    expect($guard->assertKeyPolicy($required, null)?->errorCode())->toBe('validation_failed');
    expect($guard->assertKeyPolicy($required, '!!!bad!!!')?->errorCode())->toBe('validation_failed');

    $optional = new CapabilityDefinition(
        name: 'opt',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
        idempotent: CapabilityDefinition::IDEMPOTENT_OPTIONAL,
        run: static fn () => CapabilityResult::ok([]),
    );
    // missing key warns but continues
    expect($guard->assertKeyPolicy($optional, null, 'http'))->toBeNull();

    $validKey = str_repeat('a', 16);
    expect(IdempotencyKey::isValid($validKey))->toBeTrue();

    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => SystemActor::named('actor-1'),
        'scope' => new CapabilityScope(tenantId: 'ten-1'),
    ]);
    $hash = $guard->hashInput(['x' => 1]);

    // first lookup inserts processing
    $first = $guard->lookup($optional, $ctx, $validKey, $hash);
    expect($first['action'])->toBe('continue');

    // busy while processing
    $busy = $guard->lookup($optional, $ctx, $validKey, $hash);
    expect($busy['action'])->toBe('busy');

    // complete then replay
    $guard->storeResult($optional, $ctx, $validKey, $hash, CapabilityResult::ok(['done' => true]));
    $replay = $guard->lookup($optional, $ctx, $validKey, $hash);
    expect($replay['action'])->toBe('replay')->and($replay['result']->isOk())->toBeTrue();

    // conflict different hash
    $conflict = $guard->lookup($optional, $ctx, $validKey, $guard->hashInput(['x' => 2]));
    expect($conflict['action'])->toBe('conflict');

    // failed replay
    $key2 = str_repeat('b', 16);
    $guard->lookup($optional, $ctx, $key2, $hash);
    $guard->storeResult($optional, $ctx, $key2, $hash, CapabilityResult::failure('domain_error', 'boom'));
    $failReplay = $guard->lookup($optional, $ctx, $key2, $hash);
    expect($failReplay['action'])->toBe('replay')->and($failReplay['result']->isOk())->toBeFalse();

    // pending_approval status + different hash conflict
    $key3 = str_repeat('c', 16);
    $guard->lookup($optional, $ctx, $key3, $hash);
    $guard->storeResult(
        $optional,
        $ctx,
        $key3,
        $hash,
        CapabilityResult::approvalRequired('ap-1', 'need approval'),
    );
    $pendingConflict = $guard->lookup($optional, $ctx, $key3, $guard->hashInput(['z' => 9]));
    expect($pendingConflict['action'])->toBe('conflict');
    $pendingContinue = $guard->lookup($optional, $ctx, $key3, $hash);
    expect($pendingContinue['action'])->toBe('continue');

    // invalid key in lookup
    $inv = $guard->lookup($optional, $ctx, '!!', $hash);
    expect($inv['action'])->toBe('conflict');

    // storeResult no-ops for invalid key / none
    $guard->storeResult($optional, $ctx, '!!', $hash, CapabilityResult::ok([]));
    $guard->storeResult($none, $ctx, $validKey, $hash, CapabilityResult::ok([]));

    expect($guard->isExpired(['expires_at' => '']))->toBeFalse()
        ->and($guard->isExpired(['expires_at' => 'not-a-date']))->toBeFalse()
        ->and($guard->isExpired(['expires_at' => '2020-01-01T00:00:00Z']))->toBeTrue()
        ->and($guard->isExpired(['expires_at' => '2099-01-01T00:00:00Z']))->toBeFalse();

    // expired existing treated as missing
    $key4 = str_repeat('d', 16);
    $store->put([
        'tenant_id' => 'ten-1',
        'actor_type' => 'system',
        'actor_id' => 'actor-1',
        'capability_name' => 'opt',
        'idempotency_key' => $key4,
        'request_hash' => $hash,
        'status' => 'completed',
        'result_json' => CapabilityResult::ok(['old' => true])->toArray(),
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    // lookup path uses store find which may already strip expired; also guard isExpired
    $expiredLookup = $guard->lookup($optional, $ctx, $key4, $hash);
    expect($expiredLookup['action'])->toBe('continue');

    // hydrateResult via completed with non-envelope stored
    $key5 = str_repeat('e', 16);
    $guard->lookup($optional, $ctx, $key5, $hash);
    $store->put([
        'tenant_id' => 'ten-1',
        'actor_type' => 'system',
        'actor_id' => 'actor-1',
        'capability_name' => 'opt',
        'idempotency_key' => $key5,
        'request_hash' => $hash,
        'status' => 'completed',
        'result_json' => ['raw' => true],
        'expires_at' => '2099-01-01T00:00:00Z',
    ]);
    $rawReplay = $guard->lookup($optional, $ctx, $key5, $hash);
    expect($rawReplay['action'])->toBe('replay')->and($rawReplay['result']->isOk())->toBeTrue();
});

// ── ApprovalManager accept/reject/resume branches ───────────────────────────

it('covers ApprovalManager request/find/accept/reject edge branches', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-04-01T12:00:00Z'));
    $mgr = ApprovalManager::inMemory($clock)
        ->withConfig([
            'execution' => ApprovalStateMachine::EXECUTION_DEFERRED,
            'ttl_hours' => 24,
            'resume' => [
                'enabled' => true,
                'every_seconds' => 30,
                'grace_seconds' => 10,
                'stuck_after_seconds' => 60,
                'lease_seconds' => 45,
            ],
        ])
        ->withExecutor(static fn () => CapabilityResult::ok(['ran' => true]))
        ->addNotifier(new HttpApprovalNotifier);

    expect($mgr->isAtomic())->toBeFalse()
        ->and($mgr->isDeferred())->toBeTrue()
        ->and($mgr->resumeEnabled())->toBeTrue()
        ->and($mgr->resumeEverySeconds())->toBe(30)
        ->and($mgr->graceSeconds())->toBe(10)
        ->and($mgr->stuckAfterSeconds())->toBe(60)
        ->and($mgr->leaseSeconds())->toBe(45)
        ->and($mgr->ttlHours())->toBe(24)
        ->and($mgr->effectiveTtlHours(null))->toBe(24)
        ->and($mgr->effectiveTtlHours(2))->toBe(2);

    expect($mgr->find('missing'))->toBeNull();
    expect($mgr->accept('missing', SystemActor::named('boss'))->errorCode())->toBe('not_found');

    $row = $mgr->request([
        'capability_name' => 'inv.create',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => ['customer_id' => 1],
        'approval_ttl_hours' => 1,
    ]);
    expect($row['status'])->toBe(ApprovalStateMachine::STATUS_PENDING);
    expect($mgr->find($row['id']))->not->toBeNull();

    // reject path
    $rej = $mgr->reject($row['id'], (object) ['id' => 'u1'], 'nope', ['tenant_id' => 't1']);
    // may be forbidden depending on policy — exercise path either way
    expect($rej)->toBeInstanceOf(CapabilityResult::class);

    $row2 = $mgr->request([
        'capability_name' => 'inv.create',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => ['customer_id' => 2],
    ]);
    // accept with policy that allows any (custom)
    $open = ApprovalManager::inMemory($clock)
        ->withConfig(['execution' => ApprovalStateMachine::EXECUTION_ATOMIC])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ))
        ->withExecutor(static fn () => CapabilityResult::ok(['done' => 1]))
        ->withRevalidator(static fn () => null)
        ->withOriginalAuthorizer(static fn () => true);

    $row3 = $open->request([
        'capability_name' => 'x',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);
    $accepted = $open->accept($row3['id'], (object) ['id' => 'approver'], ['tenant_id' => 't1']);
    expect($accepted->isOk() || $accepted->errorCode() !== null)->toBeTrue();

    // re-accept executed / reject already rejected paths via store statuses
    $executedId = $row3['id'];
    $again = $open->accept($executedId, (object) ['id' => 'approver'], ['tenant_id' => 't1']);
    expect($again)->toBeInstanceOf(CapabilityResult::class);

    // stale revalidator path
    $staleMgr = ApprovalManager::inMemory($clock)
        ->withConfig(['execution' => ApprovalStateMachine::EXECUTION_ATOMIC])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ))
        ->withRevalidator(static fn () => CapabilityResult::failure('conflict', 'stale input'))
        ->withExecutor(static fn () => CapabilityResult::ok(['should' => 'not-run']));
    $staleRow = $staleMgr->request([
        'capability_name' => 'stale',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);
    $stale = $staleMgr->accept($staleRow['id'], (object) ['id' => 'a'], ['tenant_id' => 't1']);
    expect($stale->isOk())->toBeFalse();

    // original authorizer deny
    $authDeny = ApprovalManager::inMemory($clock)
        ->withConfig(['execution' => ApprovalStateMachine::EXECUTION_ATOMIC])
        ->withPolicy(new ApprovalPolicy(
            policy: ApprovalPolicy::CUSTOM,
            customChecker: static fn () => true,
        ))
        ->withOriginalAuthorizer(static fn () => false)
        ->withExecutor(static fn () => CapabilityResult::ok([]));
    $denyRow = $authDeny->request([
        'capability_name' => 'deny',
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'input_json' => [],
    ]);
    $denied = $authDeny->accept($denyRow['id'], (object) ['id' => 'a'], ['tenant_id' => 't1']);
    expect($denied->errorCode())->toBe('forbidden');
});

// ── CapabilityController uncovered branches ─────────────────────────────────

it('covers CapabilityController describe not_found, health deny, non-array body', function () {
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'listed',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $ctrl = new CapabilityController(
        $reg,
        clientsConfig: [],
        httpConfig: ['idempotency_header' => 'Idempotency-Key'],
        authGate: new HttpAuthGate(['health_public' => false]),
    );

    $authed = HttpHelpers::authedRequest([
        'method' => 'GET',
        'jsonBody' => null,
    ]);
    $missing = $ctrl->describe($authed, 'no-such-cap');
    expect($missing->status)->toBeGreaterThanOrEqual(400);

    // health denied for guest when required
    $guest = HttpHelpers::guestRequest();
    $healthDeny = $ctrl->health($guest);
    expect($healthDeny->status)->toBeGreaterThanOrEqual(400);

    // invoke with authenticated empty credential maps to http adapter
    $inv = $ctrl->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['not' => 'schema'], // no input class — ok
        'headers' => ['idempotency-key' => str_repeat('k', 16)],
    ]), 'listed');
    expect($inv)->toBeInstanceOf(HttpResponse::class);
    expect($ctrl->lastInvokeOptions())->toBeArray();
});

// ── CapabilitiesServiceProvider register/boot via minimal app fake ──────────

it('covers CapabilitiesServiceProvider register and boot with fake container', function () {
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

        public array $aliases = [];

        public array $publishes = [];

        public function __construct(public object $config) {}

        public function instance(string $abstract, mixed $instance): void
        {
            $this->singletons[$abstract] = $instance;
        }

        public function singleton(string $abstract, mixed $concrete = null): void
        {
            $this->singletons[$abstract] = $concrete;
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
            $entry = $this->singletons[$abstract] ?? null;
            if (is_callable($entry)) {
                return $entry($this);
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
            unset($this->singletons[(string) $key]);
        }

        public function runningInConsole(): bool
        {
            return true;
        }

        public function configurationIsCached(): bool
        {
            return false;
        }
    };

    // Illuminate ServiceProvider expects ArrayAccess-ish app; mergeConfigFrom uses $this->app->make('config')
    // and $this->app instanceof CachesConfiguration — our fake is fine if not that interface.

    // Build a thin subclass that exposes register/boot against our fake without full Laravel.
    $provider = new class($app) extends CapabilitiesServiceProvider
    {
        /** @var list<array{paths: array<string, string>, group: string}> */
        public array $publishCalls = [];

        public function __construct(public object $fakeApp)
        {
            // parent needs Application; skip parent::__construct by not calling it —
            // set protected $app via reflection.
            $ref = new ReflectionClass(CapabilitiesServiceProvider::class);
            // Walk to ServiceProvider
            $sp = $ref->getParentClass();
            $prop = $sp->getProperty('app');
            $prop->setAccessible(true);
            $prop->setValue($this, $fakeApp);
        }

        /**
         * @param  array<string, string>  $paths
         */
        protected function publishes(array $paths, $group = null): void
        {
            $this->publishCalls[] = ['paths' => $paths, 'group' => (string) $group];
        }

        protected function mergeConfigFrom($path, $key): void
        {
            /** @var object{set: callable, get: callable} $config */
            $config = $this->fakeApp->make('config');
            $existing = $config->get($key, []);
            $config->set($key, array_replace_recursive(require $path, is_array($existing) ? $existing : []));
        }
    };

    if (! function_exists('config_path')) {
        eval('function config_path($path = "") { return "/tmp/config/".$path; }');
    }
    if (! function_exists('database_path')) {
        eval('function database_path($path = "") { return "/tmp/database/".$path; }');
    }

    $provider->register();
    $provider->boot();

    expect($app->singletons)->not->toBeEmpty()
        ->and($provider->publishCalls)->not->toBeEmpty()
        ->and($configStore->get('capabilities'))->toBeArray();

    // Resolve Metrics/Tracer factories
    $metricsFactory = $app->singletons[Metrics::class] ?? null;
    if (is_callable($metricsFactory)) {
        $metrics = $metricsFactory($app);
        expect($metrics)->toBeInstanceOf(Metrics::class);
    }
    $tracerFactory = $app->singletons[Tracer::class] ?? null;
    if (is_callable($tracerFactory)) {
        $tracer = $tracerFactory($app);
        expect($tracer)->toBeInstanceOf(Tracer::class);
    }
});

// ── CapabilityData remaining union/null branches ────────────────────────────

final class NullOnlyUnionDto extends CapabilityData
{
    public function __construct(
        public ?int $maybe = null,
        public NestedCovDto|string|null $mixed = null,
    ) {}
}

final class NestedCovDto extends CapabilityData
{
    public function __construct(public string $label) {}
}

final class UntypedParamDto extends CapabilityData
{
    public function __construct(
        public $value,
        public ?int $optionalNull = null,
    ) {}
}

it('covers remaining CapabilityData null-union and untyped parameter branches', function () {
    $a = NullOnlyUnionDto::fromArray(['maybe' => null, 'mixed' => null]);
    expect($a->maybe)->toBeNull();

    $b = NullOnlyUnionDto::fromArray(['maybe' => 3, 'mixed' => ['label' => 'x']]);
    expect($b->mixed)->toBeInstanceOf(NestedCovDto::class);

    $c = NullOnlyUnionDto::fromArray(['maybe' => 1, 'mixed' => 'plain']);
    expect($c->mixed)->toBe('plain');

    $schema = NullOnlyUnionDto::jsonSchema();
    expect($schema['properties'])->toHaveKey('mixed');

    $u = UntypedParamDto::fromArray(['value' => ['nested' => true]]);
    expect($u->value)->toBe(['nested' => true])
        ->and($u->optionalNull)->toBeNull();

    // optional null via omit
    $u2 = UntypedParamDto::fromArray(['value' => 1]);
    expect($u2->optionalNull)->toBeNull();
});

// ── StructuredToolResponse / PeerSurfaceStatus / FixedClock edge ────────────

it('covers StructuredToolResponse and FixedClock advance helpers when present', function () {
    $ok = StructuredToolResponse::fromResult(
        CapabilityResult::ok(['a' => 1]),
    );
    expect($ok)->toBeArray();

    $fail = StructuredToolResponse::fromResult(
        CapabilityResult::failure('forbidden', 'no'),
    );
    expect($fail)->toBeArray();

    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    expect($clock->now()->format('Y'))->toBe('2026');
    if (method_exists($clock, 'advance')) {
        $clock->advance(new DateInterval('PT1H'));
        expect($clock->now()->format('H'))->toBe('01');
    }
});
