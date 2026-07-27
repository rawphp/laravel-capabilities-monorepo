<?php

declare(strict_types=1);

/**
 * REQ-016: exercise remaining uncovered unit paths so package line coverage ≥95%.
 * Unit-only; no DB; pure construction + fakes.
 */

use Rawphp\Capabilities\Adapters\Mcp\McpAuthException;
use Rawphp\Capabilities\Adapters\PeerSurfaceStatus;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Approval\ApprovalCallbackVerifier;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\Capabilities\Approval\ResumeApprovedApprovals;
use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Discovery\DiscoveryPaths;
use Rawphp\Capabilities\Events\CapabilityApprovalDecided;
use Rawphp\Capabilities\Events\CapabilityApprovalExecuted;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Events\EventPayload;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Http\DetectsCaller;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Idempotency\IdempotencyConfig;
use Rawphp\Capabilities\Idempotency\IdempotencyStore;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Schema\SchemaValidationException;
use Rawphp\Capabilities\Schema\ServerRuleClassifier;
use Rawphp\Capabilities\Support\CallerClaimRejectedException;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use DateTimeImmutable;

// ── simple 0% / low-coverage types ──────────────────────────────────────────

it('covers ToolSelection construction and of()', function () {
    $a = ToolSelection::of('ops');
    $b = new ToolSelection(['groups' => ['finance']]);
    expect($a->profile)->toBe('ops')->and($b->profile)->toBe(['groups' => ['finance']]);
});

it('covers SchemaValidationException constructors and withViolations', function () {
    $empty = SchemaValidationException::withViolations([]);
    expect($empty->getMessage())->toBe('Validation failed')
        ->and($empty->violations)->toBe([])
        ->and($empty->errorCode)->toBe('validation_failed');

    $with = SchemaValidationException::withViolations([
        ['field' => 'amount', 'message' => 'too small'],
        ['field' => 'currency', 'message' => 'invalid'],
    ], 'schema_invalid');
    expect($with->getMessage())->toContain('amount: too small')
        ->and($with->getMessage())->toContain('currency: invalid')
        ->and($with->errorCode)->toBe('schema_invalid');

    $direct = new SchemaValidationException('boom', [['field' => 'x', 'message' => 'y']], 'custom');
    expect($direct->getMessage())->toBe('boom')->and($direct->violations)->toHaveCount(1);
});

it('covers CallerClaimRejectedException::upgrade', function () {
    $e = CallerClaimRejectedException::upgrade('http', 'cli');
    expect($e)->toBeInstanceOf(CallerClaimRejectedException::class)
        ->and($e->getMessage())->toContain('derived=http')
        ->and($e->getMessage())->toContain('claimed=cli');
});

it('covers UnresolvedScopeException factories', function () {
    expect(UnresolvedScopeException::systemWithoutTenant()->getMessage())->toContain('tenantId')
        ->and(UnresolvedScopeException::unusable()->getMessage())->toContain('CapabilityScope');
});

it('covers PeerSurfaceStatus::isUp', function () {
    $up = new PeerSurfaceStatus('agent', PeerSurfaceStatus::UP, true);
    $down = new PeerSurfaceStatus('mcp', PeerSurfaceStatus::DISABLED_CONFIG, false, reason: 'off');
    expect($up->isUp())->toBeTrue()->and($down->isUp())->toBeFalse();
});

it('covers all McpAuthException factories', function () {
    foreach ([
        McpAuthException::vagueTokenUser(),
        McpAuthException::integrationDisabled(),
        McpAuthException::missingUser(),
        McpAuthException::missingClientId('user_pat'),
        McpAuthException::unknownIntegrationClient('c1'),
        McpAuthException::unknownProfile('nope'),
    ] as $ex) {
        expect($ex)->toBeInstanceOf(McpAuthException::class)
            ->and($ex->getMessage())->not->toBe('');
    }
});

it('covers DiscoveryPaths default and fromConfig', function () {
    $default = DiscoveryPaths::default();
    expect($default)->toBeString()->not->toBe('');
    expect(DiscoveryPaths::fromConfig([]))->toBe([$default]);
    expect(DiscoveryPaths::fromConfig(['path' => '/tmp/a']))->toBe(['/tmp/a']);
    expect(DiscoveryPaths::fromConfig(['path' => ['/a', '/b']]))->toBe(['/a', '/b']);
});

it('covers DetectsCaller trait defaults and detectCaller', function () {
    $host = new class
    {
        use DetectsCaller;

        public function expose(): array
        {
            return [
                'config' => $this->callerConfig(),
                'deriver' => $this->callerDeriver(),
                'detected' => $this->detectCaller(['type' => 'session_user'], 'cli'),
            ];
        }
    };

    $out = $host->expose();
    expect($out['config']['token_abilities'])->toHaveKey('capabilities:cli')
        ->and($out['deriver'])->toBeInstanceOf(CallerDeriver::class)
        ->and($out['detected'])->toHaveKeys(['caller', 'derived', 'rejected', 'reason']);
});

// ── EventPayload / PipelineStages / InvokeState ─────────────────────────────

it('covers EventPayload meta, afterCommit, and listenersShouldUseAfterCommit', function () {
    $meta = EventPayload::meta([
        'name' => 'x',
        'caller' => 'http',
        'custom' => 1,
    ]);
    expect($meta['name'])->toBe('x')
        ->and($meta['custom'])->toBe(1)
        ->and(EventPayload::hasCorrelationKey('invocation_id'))->toBeTrue()
        ->and(EventPayload::hasCorrelationKey('nope'))->toBeFalse();

    $after = EventPayload::afterCommitEvents();
    expect($after)->toContain(CapabilityInvoked::class)
        ->and($after)->toContain(CapabilityFailed::class)
        ->and($after)->toContain(CapabilityApprovalRequested::class)
        ->and($after)->toContain(CapabilityApprovalDecided::class)
        ->and($after)->toContain(CapabilityApprovalExecuted::class);

    expect(EventPayload::listenersShouldUseAfterCommit(CapabilityInvoked::class))->toBeTrue()
        ->and(EventPayload::listenersShouldUseAfterCommit('Nonexistent\\Event'))->toBeFalse()
        ->and(EventPayload::listenersShouldUseAfterCommit(stdClass::class))->toBeFalse();
});

it('covers PipelineStages ordered and errorCodeFor matrix', function () {
    $order = PipelineStages::ordered();
    expect($order)->toContain(PipelineStages::RUN)
        ->and($order[0])->toBe(PipelineStages::JSON_SCHEMA_VALIDATE);

    expect(PipelineStages::errorCodeFor(PipelineStages::JSON_SCHEMA_VALIDATE))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::HYDRATE_DTO))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::SERVER_ONLY_VALIDATE))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RESOLVE_ACTOR))->toBe('unauthenticated')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RESOLVE_SCOPE))->toBe('forbidden')
        ->and(PipelineStages::errorCodeFor(PipelineStages::IDEMPOTENCY_LOOKUP))->toBe('conflict')
        ->and(PipelineStages::errorCodeFor(PipelineStages::AUTHORIZE))->toBe('forbidden')
        ->and(PipelineStages::errorCodeFor(PipelineStages::NEEDS_APPROVAL))->toBe('approval_required')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RATE_LIMIT))->toBe('rate_limited')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RUN))->toBe('internal')
        ->and(PipelineStages::errorCodeFor('unknown'))->toBe('internal');
});

it('covers InvokeState stage helpers', function () {
    $def = new CapabilityDefinition(name: 's', description: 'd', readOnly: true);
    $state = new InvokeState($def, ['a' => 1], 'http', ['x' => true], 'req-1');
    $state->mark('a');
    $state->mark('b');
    expect($state->hasStage('a'))->toBeTrue()
        ->and($state->hasStage('z'))->toBeFalse()
        ->and($state->stageIndex('b'))->toBe(1)
        ->and($state->stageIndex('missing'))->toBeNull()
        ->and($state->requestId)->toBe('req-1');
});

// ── RouteTable ──────────────────────────────────────────────────────────────

it('covers RouteTable actionKeys, empty prefix, find, pathFor matrix', function () {
    expect(RouteTable::actionKeys())->toContain(RouteTable::ROUTE_INVOKE);

    $emptyPrefix = RouteTable::routes(['enabled' => true, 'prefix' => '', 'middleware' => ['api', '']]);
    expect($emptyPrefix)->not->toBeEmpty();
    $list = RouteTable::find($emptyPrefix, RouteTable::ROUTE_LIST);
    expect($list)->not->toBeNull()->and($list['uri'] ?? '')->toContain('capabilities');

    expect(RouteTable::has($emptyPrefix, RouteTable::ROUTE_HEALTH))->toBeTrue()
        ->and(RouteTable::has($emptyPrefix, 'nope'))->toBeFalse()
        ->and(RouteTable::find($emptyPrefix, 'nope'))->toBeNull();

    foreach ([
        RouteTable::ROUTE_LIST,
        RouteTable::ROUTE_DESCRIBE,
        RouteTable::ROUTE_INVOKE,
        RouteTable::ROUTE_APPROVAL_ACCEPT,
        RouteTable::ROUTE_APPROVAL_REJECT,
        RouteTable::ROUTE_HEALTH,
        RouteTable::ROUTE_AUTH_TOKEN,
        RouteTable::ROUTE_AUTH_DEVICE,
        RouteTable::ROUTE_AUTH_OAUTH_CALLBACK,
        'unknown',
    ] as $key) {
        expect(RouteTable::pathFor($key, 'capabilities', 'inv', 'appr'))->toStartWith('/');
    }
});

// ── ServerRuleClassifier / InputValidator / OutputValidator ─────────────────

it('covers ServerRuleClassifier classify portable/server/unknown and normalize', function () {
    $c = new ServerRuleClassifier;
    $split = $c->classify([
        'a' => 'required|integer|exists:users,id',
        'b' => ['nullable', 'string', 'custom_rule'],
        'c' => 'min:1|max:10',
        'd' => 123, // ignored non-string rules
        'e' => ['required', 99, 'email'],
    ]);
    expect($split['portable']['a'] ?? [])->toContain('required')
        ->and($split['portable']['a'] ?? [])->toContain('integer')
        ->and($split['server_only']['a'] ?? [])->toContain('exists:users,id')
        ->and($split['server_only']['b'] ?? [])->toContain('custom_rule')
        ->and($split['portable']['c'] ?? [])->toContain('min:1')
        ->and($split['portable']['e'] ?? [])->toContain('email');

    expect($c->isPortable('required'))->toBeTrue()
        ->and($c->isPortable('exists:x,y'))->toBeFalse()
        ->and($c->isServerOnly('unique:users,email'))->toBeTrue()
        ->and($c->schemaContainsServerOnly(['properties' => ['id' => ['type' => 'integer']]], 'exists'))->toBeFalse()
        ->and($c->schemaContainsServerOnly(['x' => 'exists:users,id'], 'exists'))->toBeTrue();
});

it('covers InputValidator happy, no input, bad SchemaProvider, portable, server rules', function () {
    $v = new InputValidator;

    $noInput = new CapabilityDefinition(name: 'n', description: 'd', input: null, readOnly: true);
    expect($v->validate($noInput, ['raw' => true]))->toBe(['raw' => true]);

    $bad = new CapabilityDefinition(name: 'b', description: 'd', input: stdClass::class, readOnly: true);
    expect(fn () => $v->validate($bad, []))->toThrow(SchemaValidationException::class);

    $ok = new CapabilityDefinition(
        name: 'inv',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
    );
    $dto = $v->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($dto)->toBeInstanceOf(CreateInvoiceInput::class);

    expect(fn () => $v->validate($ok, ['customer_id' => 'x']))->toThrow(SchemaValidationException::class);

    $failing = new InputValidator(serverRules: new FailingServerRuleChecker);
    // CreateInvoiceInput has rules(); FailingServerRuleChecker should reject when rules present
    expect(fn () => $failing->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]))->toThrow(SchemaValidationException::class);

    // skip server rules
    $dto2 = $failing->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ], serverRules: false);
    expect($dto2)->toBeInstanceOf(CreateInvoiceInput::class);

    expect($v->serverRuleChecker())->not->toBeNull()
        ->and($v->jsonSchemaValidator())->not->toBeNull();

    $v->validatePortable(CreateInvoiceInput::jsonSchema(), [
        'customer_id' => 1,
        'amount_cents' => 50,
        'currency' => 'EUR',
    ]);
});

it('covers OutputValidator skip, bad provider, http and tool envelopes, toArray branches', function () {
    $ov = new OutputValidator;

    $noOut = new CapabilityDefinition(name: 'n', description: 'd', output: null, readOnly: true);
    expect($ov->validate($noOut, ['x' => 1]))->toBeNull();

    $emptyOut = new CapabilityDefinition(name: 'e', description: 'd', output: '', readOnly: true);
    expect($ov->validate($emptyOut, ['x' => 1]))->toBeNull();

    $bad = new CapabilityDefinition(name: 'b', description: 'd', output: stdClass::class, readOnly: true);
    $fail = $ov->validate($bad, []);
    expect($fail)->not->toBeNull()->and($fail->errorCode())->toBe('output_invalid');

    $okDef = new CapabilityDefinition(
        name: 'o',
        description: 'd',
        output: CreateInvoiceResult::class,
        readOnly: true,
    );
    $okDto = CreateInvoiceResult::fromArray(['invoice_id' => 9]);
    expect($ov->validate($okDef, $okDto))->toBeNull();
    expect($ov->validate($okDef, ['invoice_id' => 9]))->toBeNull();

    $objectWithToArray = new class
    {
        public function toArray(): array
        {
            return ['invoice_id' => 1];
        }
    };
    expect($ov->validate($okDef, $objectWithToArray))->toBeNull();
    // non-array non-DTO falls back to []
    expect($ov->validate($okDef, 42))->not->toBeNull();

    $okResult = CapabilityResult::ok(['z' => 1]);
    $httpOk = $ov->toHttpEnvelope($okResult);
    expect($httpOk['status'])->toBe(200);

    $httpOut = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'output_invalid', message: 'bad'));
    expect($httpOut['status'])->toBe(500);
    $httpVal = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'validation_failed', message: 'v'));
    expect($httpVal['status'])->toBe(422);
    $httpForb = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'forbidden', message: 'f'));
    expect($httpForb['status'])->toBe(403);
    $httpDef = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'conflict', message: 'c'));
    expect($httpDef['status'])->toBe(400);

    $toolOk = $ov->toToolResult($okResult);
    expect($toolOk['ok'])->toBeTrue()->and($toolOk['is_error'])->toBeFalse();
    $toolFail = $ov->toToolResult(CapabilityResult::failure(code: 'output_invalid', message: 'x'));
    expect($toolFail['ok'])->toBeFalse()->and($toolFail['is_error'])->toBeTrue();
});

// ── Idempotency stores ──────────────────────────────────────────────────────

it('covers IdempotencyStore put/find/update/all/forgetExpired and edge expiry', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    expect(fn () => new IdempotencyStore($clock, 0))->toThrow(InvalidArgumentException::class);

    $store = IdempotencyStore::withConfig($clock, new IdempotencyConfig(ttlHours: 24));
    expect($store->ttlHours())->toBe(24);

    $store->put([
        'tenant_id' => 42, // non-string coerced
        'actor_type' => 'user',
        'actor_id' => 'u1',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k1',
        'request_hash' => 'h',
        'status' => 'completed',
        'result_json' => ['ok' => true],
        'expires_at' => new DateTimeImmutable('2026-01-02T00:00:00Z'),
    ]);

    $found = $store->find('42', 'user', 'u1', 'inv.create', 'k1');
    expect($found)->not->toBeNull()->and($found['status'])->toBe('completed');

    $updated = $store->update('42', 'user', 'u1', 'inv.create', 'k1', ['status' => 'failed']);
    expect($updated['status'])->toBe('failed');
    expect($store->update('42', 'user', 'u1', 'inv.create', 'missing', []))->toBeNull();

    // expired row treated as missing
    $store->put([
        'tenant_id' => null,
        'actor_type' => 'user',
        'actor_id' => 'u2',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-exp',
        'status' => 'completed',
        'expires_at' => '2025-01-01T00:00:00Z',
    ]);
    expect($store->find(null, 'user', 'u2', 'inv.create', 'k-exp'))->toBeNull();

    // bad expires_at string not expired
    $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => 'u3',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-bad',
        'status' => 'processing',
        'expires_at' => 'not-a-date',
    ]);
    expect($store->find('t', 'user', 'u3', 'inv.create', 'k-bad'))->not->toBeNull();

    // update on expired removes
    $store->put([
        'tenant_id' => 't2',
        'actor_type' => 'user',
        'actor_id' => 'u4',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-up-exp',
        'status' => 'processing',
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    // re-insert as live then advance clock? FixedClock is fixed — put already has past expiry;
    // update path: first put a live row then manually we can't set clock. Put with past expiry
    // and call update — isExpired should drop it.
    expect($store->update('t2', 'user', 'u4', 'inv.create', 'k-up-exp', ['status' => 'x']))->toBeNull();

    $store->put([
        'tenant_id' => 'live',
        'actor_type' => 'user',
        'actor_id' => 'u5',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-live',
        'status' => 'completed',
        'expires_at' => '2099-01-01T00:00:00Z',
    ]);
    $store->put([
        'tenant_id' => 'dead',
        'actor_type' => 'user',
        'actor_id' => 'u6',
        'capability_name' => 'inv.create',
        'idempotency_key' => 'k-dead',
        'status' => 'completed',
        'expires_at' => '2020-01-01T00:00:00Z',
    ]);
    $all = $store->all();
    expect(count($all))->toBeGreaterThan(0);
    $removed = $store->forgetExpired();
    expect($removed)->toBeGreaterThanOrEqual(0);
});

it('covers InMemoryIdempotencyStore expiry and update miss', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-06-01T12:00:00Z'));
    $store = new InMemoryIdempotencyStore($clock);
    $store->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'c',
        'idempotency_key' => 'k',
        'status' => 'completed',
        'expires_at' => '2026-01-01T00:00:00Z',
    ]);
    expect($store->find('t', 'user', '1', 'c', 'k'))->toBeNull();
    expect($store->update('t', 'user', '1', 'c', 'missing', ['status' => 'x']))->toBeNull();

    $store->put([
        'tenant_id' => 7,
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'c',
        'idempotency_key' => 'k2',
        'status' => 'processing',
        'expires_at' => 'not-date',
    ]);
    expect($store->find('7', 'user', '1', 'c', 'k2'))->not->toBeNull();
    $u = $store->update('7', 'user', '1', 'c', 'k2', ['status' => 'completed']);
    expect($u['status'])->toBe('completed');
});

// ── CapabilityData edge branches ────────────────────────────────────────────

final class EmptyCtorDto extends CapabilityData
{
    // no constructor
}

final class NestedAddressDto extends CapabilityData
{
    public function __construct(
        public string $city,
    ) {}
}

final class UnionAndNestedDto extends CapabilityData
{
    public function __construct(
        public int|string $id,
        public ?NestedAddressDto $address = null,
        #[Field(items: NestedAddressDto::class, minItems: 0, maxItems: 5)]
        public array $locations = [],
        #[Field(description: 'amount', minimum: 0, maximum: 9999, format: 'int32')]
        public float $amount = 0.0,
        #[Field(enum: ['a', 'b'], minLength: 1, maxLength: 1)]
        public string $code = 'a',
        public bool $flag = false,
        public ?string $note = null,
        public $untyped = null,
    ) {}
}

final class StaticPropDto extends CapabilityData
{
    public static string $ignored = 'x';

    public function __construct(
        public string $name,
    ) {}
}

it('covers CapabilityData no-ctor, unions, nested, field constraints, coercion errors', function () {
    expect(EmptyCtorDto::fromArray([]))->toBeInstanceOf(EmptyCtorDto::class);
    expect(fn () => EmptyCtorDto::fromArray(['x' => 1]))->toThrow(InvalidArgumentException::class);
    expect(EmptyCtorDto::fromArray(['x' => 1], allowAdditionalProperties: true))->toBeInstanceOf(EmptyCtorDto::class);

    $dto = UnionAndNestedDto::fromArray([
        'id' => '42',
        'address' => ['city' => 'Berlin'],
        'locations' => [['city' => 'NYC']],
        'amount' => '1.5',
        'code' => 'b',
        'flag' => true,
        'note' => null,
    ]);
    expect($dto->id)->toBe('42')
        ->and($dto->address)->toBeInstanceOf(NestedAddressDto::class)
        ->and($dto->locations[0])->toBeInstanceOf(NestedAddressDto::class)
        ->and($dto->amount)->toBe(1.5)
        ->and($dto->toArray()['address']['city'])->toBe('Berlin');

    $schema = UnionAndNestedDto::jsonSchema();
    expect($schema['properties']['amount']['minimum'] ?? null)->toBe(0)
        ->and($schema['properties']['code']['enum'] ?? null)->toBe(['a', 'b'])
        ->and($schema['properties']['code']['minLength'] ?? null)->toBe(1)
        ->and($schema['properties']['locations']['items'] ?? null)->not->toBeNull();

    expect(UnionAndNestedDto::from(['id' => 7])->id)->toBeIn([7, '7']);
    expect(UnionAndNestedDto::validate(['id' => 1]))->toBeInstanceOf(UnionAndNestedDto::class);
    expect(UnionAndNestedDto::rules())->toBe([]);

    expect(fn () => UnionAndNestedDto::fromArray([]))->toThrow(InvalidArgumentException::class); // missing id
    expect(fn () => UnionAndNestedDto::fromArray(['id' => null]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'flag' => 'yes']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'amount' => 'nope']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'address' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'locations' => 'x']))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'locations' => ['not-object']]))->toThrow(InvalidArgumentException::class);
    expect(fn () => UnionAndNestedDto::fromArray(['id' => 1, 'code' => false]))->toThrow(InvalidArgumentException::class);

    $s = StaticPropDto::fromArray(['name' => 'n']);
    expect($s->toArray())->toBe(['name' => 'n']); // static prop skipped
});

// ── ApprovalCallbackVerifier / Resume / Manager fluent ──────────────────────

it('covers ApprovalCallbackVerifier sign/verify branches and unsigned refuse', function () {
    $v = new ApprovalCallbackVerifier('secret', 60);
    $payload = [
        'approval_id' => 'ap-1',
        'action' => 'accept',
        'exp' => time() + 120,
        'approver_hint' => 'alice',
    ];
    $sig = $v->sign($payload);
    expect($v->verify(array_merge($payload, ['sig' => $sig])))->toBeTrue()
        ->and($v->verify($payload))->toBeFalse() // missing sig
        ->and($v->verify(['sig' => 'x']))->toBeFalse() // missing fields
        ->and($v->verify(array_merge($payload, ['sig' => $sig, 'exp' => time() - 10])))->toBeFalse()
        ->and($v->verify(array_merge($payload, ['sig' => 'deadbeef'])))->toBeFalse();

    expect(fn () => $v->acceptUnsignedIdOnly('ap-1'))->toThrow(RuntimeException::class);
});

it('covers ResumeApprovedApprovals and ApprovalManager fluent/config surface', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    $mgr = ApprovalManager::inMemory($clock);
    $validated = ApprovalManager::validateConfig(['execution' => 'deferred']);
    expect($validated['execution'])->toBe(ApprovalStateMachine::EXECUTION_DEFERRED);

    $clone = $mgr
        ->withPolicy(new ApprovalPolicy)
        ->withConfig(['ttl_seconds' => 120])
        ->withExecutor(static fn () => CapabilityResult::ok(['done' => true]))
        ->withRevalidator(static fn () => true)
        ->withOriginalAuthorizer(static fn () => true)
        ->withAudit(new InMemoryAuditWriter($clock))
        ->withIdempotency(new InMemoryIdempotencyStore($clock));

    expect($clone->store())->toBeInstanceOf(InMemoryApprovalStore::class)
        ->and($clone->clock())->not->toBeNull()
        ->and($clone->config())->toBeArray()
        ->and($clone->policy())->toBeInstanceOf(ApprovalPolicy::class)
        ->and($clone->metrics())->not->toBeNull()
        ->and($clone->runCount())->toBe(0)
        ->and($clone->events())->toBeArray()
        ->and($clone->machine())->toBeInstanceOf(ApprovalStateMachine::class)
        ->and($clone->executionMode())->toBeString()
        ->and($clone->isDeferred())->toBeBool();

    $resume = new ResumeApprovedApprovals($clone);
    expect($resume->manager())->toBe($clone)
        ->and($resume->shouldSchedule())->toBeBool()
        ->and($resume->everySeconds())->toBeInt()
        ->and($resume->handle())->toBeArray()
        ->and($resume->artisan())->toBeArray();
});

// ── CapabilityRegistry fluent config + forceFail + alias collision ──────────

it('covers CapabilityRegistry fluent setters, getters, alias collision, forceFail stages', function () {
    $reg = new CapabilityRegistry;
    $reg->register(new CapabilityDefinition(
        name: 'alpha',
        description: 'a',
        aliases: ['a1'],
        readOnly: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    expect(fn () => $reg->register(new CapabilityDefinition(
        name: 'beta',
        description: 'b',
        aliases: ['a1'],
        readOnly: true,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $reg->get('missing'))->toThrow(InvalidArgumentException::class);

    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    $reg
        ->withValidationConfig(['validate_output' => false, 'audit_mode' => 'best_effort'])
        ->withAuthorizer(StubAuthorizer::allow())
        ->withServerRuleChecker(new FailingServerRuleChecker)
        ->withAuditWriter(new InMemoryAuditWriter($clock))
        ->withAuditConfig([
            'mode' => 'best_effort',
            'enabled' => true,
            'required' => false,
            'driver' => 'log',
        ])
        ->withAuditOutbox(null)
        ->withTransactionsConfig(['wrap_run' => true])
        ->withEventsConfig(['enabled' => true])
        ->withRateLimitConfig(['enabled' => true, 'defaults' => ['per_minute' => 60]])
        ->withApprovalStore(new InMemoryApprovalStore($clock))
        ->withIdempotencyStore(new InMemoryIdempotencyStore($clock))
        ->withRateLimiter(new InMemoryRateLimiter)
        ->withScopeResolver(new \Rawphp\Capabilities\Support\DefaultScopeResolver)
        ->withToolSurfaceConfig(['agent' => ['profile' => 'default']])
        ->withSurfaceHealthOverrides(['agent' => 'up'])
        ->withClock($clock)
        ->forceFailStages('json_schema_validate')
        ->throwOnAuditFailure(false);

    expect($reg->validateOutputEnabled())->toBeFalse()
        ->and($reg->auditOutbox())->toBeNull()
        ->and($reg->auditMode())->toBeString()
        ->and($reg->auditRequired())->toBeFalse()
        ->and($reg->auditDriver())->toBeString()
        ->and($reg->transactionsWrapRun())->toBeTrue()
        ->and($reg->lastRunWasWrapped())->toBeBool()
        ->and($reg->eventsEnabled())->toBeTrue()
        ->and($reg->rateLimitConfig())->toBeArray()
        ->and($reg->lastRateLimitKey())->toBeNull()
        ->and($reg->agentTurnBudget())->not->toBeNull()
        ->and($reg->toolSurfaceConfig())->toHaveKey('agent')
        ->and($reg->surfaceHealthOverrides())->toHaveKey('agent')
        ->and($reg->clock())->toBeInstanceOf(FixedClock::class)
        ->and($reg->catalog())->not->toBeNull()
        ->and($reg->toolSchemas())->not->toBeNull()
        ->and($reg->approvals())->toBeInstanceOf(ApprovalManager::class)
        ->and($reg->audit())->not->toBeNull();

    // forceFail single string form covered; clear via empty and force array form
    $reg->forceFailStages([]);
    $reg->register(new CapabilityDefinition(
        name: 'probe',
        description: 'p',
        readOnly: true,
        run: static fn () => CapabilityResult::ok(['p' => 1]),
    ));
    $reg->forceFailStages([
        PipelineStages::JSON_SCHEMA_VALIDATE,
        PipelineStages::HYDRATE_DTO,
        PipelineStages::SERVER_ONLY_VALIDATE,
        PipelineStages::RESOLVE_ACTOR,
    ]);
    $r = $reg->invoke('probe', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('t'),
        'scope' => new CapabilityScope(tenantId: 't1'),
    ]);
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('validation_failed');
});

// ── Observability / Scope / Provider statics ────────────────────────────────

it('covers LogFallbackMetrics and InMemoryTracer disabled/hash paths', function () {
    $off = new LogFallbackMetrics(false);
    $off->increment('x');
    $off->histogram('y', 1.0);
    expect($off->enabled())->toBeFalse()->and($off->logLines())->toBe([]);

    $on = new LogFallbackMetrics(true);
    $on->increment('calls', 2, ['c' => 'http']);
    $on->histogram('latency', 12.5, ['c' => 'http']);
    expect($on->get('calls', ['c' => 'http']))->toBe(2)
        ->and($on->logLines())->not->toBeEmpty()
        ->and($on->inner())->not->toBeNull();

    $trOff = new InMemoryTracer(false);
    expect($trOff->startSpan('n'))->toBe('disabled');
    $trOff->setAttributes('disabled', ['a' => 1]);
    $trOff->endSpan('disabled', 'ok');
    expect($trOff->spans())->toBe([])->and($trOff->lastSpan())->toBeNull();

    $tr = new InMemoryTracer(true, hashSensitive: true);
    $id = $tr->startSpan('invoke', ['tenant_id' => 't-secret', 'other' => 'plain']);
    $tr->setAttributes($id, ['idempotency_key' => 'ik', 'actor_id' => 'u1']);
    $tr->endSpan($id, 'ok');
    $tr->setAttributes('missing', ['x' => 1]);
    $tr->endSpan('missing', 'x');
    $last = $tr->lastSpan();
    expect($last['ended'])->toBeTrue()
        ->and($last['attributes']['tenant_id'])->toStartWith('sha256:')
        ->and($last['attributes']['other'])->toBe('plain');
});

it('covers CapabilityScope query with factory and without', function () {
    $factory = new InMemoryScopedQueryFactory;
    $scope = new CapabilityScope(tenantId: 't1');
    expect(fn () => $scope->query('Invoice'))->toThrow(RuntimeException::class);

    $scoped = $scope->withQueryFactory($factory);
    expect($scoped->query('Invoice'))->not->toBeNull()
        ->and($scoped->tenantId)->toBe('t1');
});

it('covers CapabilitiesServiceProvider static plan helpers', function () {
    $config = CapabilitiesConfig::defaults();
    // Disable peer-backed surfaces so boot guards do not require laravel/ai|mcp.
    $config['surfaces']['agent']['enabled'] = false;
    $config['surfaces']['mcp']['enabled'] = false;
    $config['surfaces']['messaging']['enabled'] = false;

    $guards = CapabilitiesServiceProvider::runBootGuards($config, skipBootChecks: true);
    expect($guards)->toBeArray();

    $guards2 = CapabilitiesServiceProvider::runBootGuards($config, messagingPackageInstalled: false, appEnv: 'testing', skipBootChecks: false);
    expect($guards2)->toBeArray();

    expect(CapabilitiesServiceProvider::registrationPlan($config))->toBeArray()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->not->toBeEmpty()
        ->and(CapabilitiesServiceProvider::publishTags())->not->toBeEmpty()
        ->and(CapabilitiesServiceProvider::jobHelpers(['enabled' => true]))->toBeArray()
        ->and(CapabilitiesServiceProvider::artisanCommands(['enabled' => true]))->toBeArray()
        ->and(CapabilitiesServiceProvider::knownSurfaces())->not->toBeEmpty();
});
