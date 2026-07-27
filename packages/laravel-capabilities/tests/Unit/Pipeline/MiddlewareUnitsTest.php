<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use Illuminate\Support\Facades\Facade;
use DateTimeImmutable;

it("happy: ResolveActor builds non-null principal [PIPE-010]", function () {
    $actor = (new ResolveActor)->resolve('http', ['actor' => PipelineHelpers::userActor(3)]);
    expect($actor)->toBeObject()->and($actor->id)->toBe(3);
});

it("fail: ResolveActor refuses null principal [PIPE-010]", function () {
    expect(fn () => (new ResolveActor)->resolve('http', ['actor' => null]))
        ->toThrow(RuntimeException::class);
});

it("happy: ResolveTenantFromCaller attaches scope [D-003]", function () {
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(),
    ]);
    $scope = (new ResolveTenantFromCaller)->resolve($ctx, ['tenant_id' => 'acme']);
    expect($scope)->toBeInstanceOf(CapabilityScope::class)->and($scope->tenantId)->toBe('acme');
});

it("fail: ResolveTenantFromCaller fails closed when unusable [D-003]", function () {
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(),
    ]);
    expect(fn () => (new ResolveTenantFromCaller)->resolve($ctx, ['fail_scope' => true]))
        ->toThrow(RuntimeException::class);
});

it("happy: IdempotencyGuard short-circuits on completed same hash [D-005]", function () {
    $fakes = SharedFakes::create();
    $guard = new IdempotencyGuard($fakes->idempotency);
    $def = Capability::define('idem-1')->input(CreateInvoiceInput::class)->output(CreateInvoiceResult::class)
        ->run(fn () => new CreateInvoiceResult(1))->toDefinition();
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(1),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    $hash = hash('sha256', '{"a":1}');
    $fakes->idempotency->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'idem-1',
        'idempotency_key' => 'k',
        'request_hash' => $hash,
        'status' => 'completed',
        'result_json' => ['ok' => true, 'data' => ['invoice_id' => 1], 'meta' => []],
    ]);
    $lookup = $guard->lookup($def, $ctx, 'k', $hash);
    expect($lookup['action'])->toBe('replay')->and($lookup['result']->isOk())->toBeTrue();
});

it("fail: IdempotencyGuard conflicts on different hash [D-005]", function () {
    $fakes = SharedFakes::create();
    $guard = new IdempotencyGuard($fakes->idempotency);
    $def = Capability::define('idem-2')->input(CreateInvoiceInput::class)->output(CreateInvoiceResult::class)
        ->run(fn () => new CreateInvoiceResult(1))->toDefinition();
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(1),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    $fakes->idempotency->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'idem-2',
        'idempotency_key' => 'k',
        'request_hash' => 'hash-a',
        'status' => 'completed',
        'result_json' => ['ok' => true, 'data' => null, 'meta' => []],
    ]);
    $lookup = $guard->lookup($def, $ctx, 'k', 'hash-b');
    expect($lookup['action'])->toBe('conflict')->and($lookup['result']->errorCode())->toBe('conflict');
});

it("edge: IdempotencyGuard processing returns too early or conflict [D-005]", function () {
    $fakes = SharedFakes::create();
    $guard = new IdempotencyGuard($fakes->idempotency);
    $def = Capability::define('idem-3')->input(CreateInvoiceInput::class)->output(CreateInvoiceResult::class)
        ->run(fn () => new CreateInvoiceResult(1))->toDefinition();
    $ctx = CapabilityContext::make([
        'caller' => 'http',
        'actor' => PipelineHelpers::userActor(1),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    $hash = 'h1';
    $fakes->idempotency->put([
        'tenant_id' => 't',
        'actor_type' => 'user',
        'actor_id' => '1',
        'capability_name' => 'idem-3',
        'idempotency_key' => 'k',
        'request_hash' => $hash,
        'status' => 'processing',
    ]);
    $lookup = $guard->lookup($def, $ctx, 'k', $hash);
    expect($lookup['action'])->toBe('busy')->and($lookup['result']->errorCode())->toBe('conflict');
});

