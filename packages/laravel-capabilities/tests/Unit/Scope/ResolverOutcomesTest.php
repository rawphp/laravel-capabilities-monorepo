<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\CallerClaimRejectedException;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use InvalidArgumentException;
use RuntimeException;
use stdClass;


it("edge: scope resolution when caller=agent tenancy=multi actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'agent', actor: H::user(7, "tenant-a"));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=agent tenancy=multi actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'agent', actor: H::user(7, null));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=agent tenancy=multi actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 't1'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('t1');
});

it("fail: unresolved scope when caller=agent tenancy=multi actor=system tenant_present=False and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("edge: scope resolution when caller=agent tenancy=single actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=agent tenancy=single actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=agent tenancy=single actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = ["tenant_id" => "t1"];
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=agent tenancy=single actor=system tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'agent',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=mcp tenancy=multi actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'mcp', actor: H::user(7, "tenant-a"));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=mcp tenancy=multi actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'mcp', actor: H::user(7, null));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=mcp tenancy=multi actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 't1'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('t1');
});

it("fail: unresolved scope when caller=mcp tenancy=multi actor=system tenant_present=False and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("edge: scope resolution when caller=mcp tenancy=single actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=mcp tenancy=single actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=mcp tenancy=single actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = ["tenant_id" => "t1"];
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=mcp tenancy=single actor=system tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'mcp',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=http tenancy=multi actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'http', actor: H::user(7, "tenant-a"));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=http tenancy=multi actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'http', actor: H::user(7, null));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=http tenancy=multi actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 't1'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('t1');
});

it("fail: unresolved scope when caller=http tenancy=multi actor=system tenant_present=False and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("edge: scope resolution when caller=http tenancy=single actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=http tenancy=single actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=http tenancy=single actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = ["tenant_id" => "t1"];
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=http tenancy=single actor=system tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'http',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=cli tenancy=multi actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'cli', actor: H::user(7, "tenant-a"));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=cli tenancy=multi actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'cli', actor: H::user(7, null));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=cli tenancy=multi actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 't1'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('t1');
});

it("fail: unresolved scope when caller=cli tenancy=multi actor=system tenant_present=False and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("edge: scope resolution when caller=cli tenancy=single actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=cli tenancy=single actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=cli tenancy=single actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = ["tenant_id" => "t1"];
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=cli tenancy=single actor=system tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'cli',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=job tenancy=multi actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'job', actor: H::user(7, "tenant-a"));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=job tenancy=multi actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'tenancy_required' => true,
            'user_tenants' => [7 => 'tenant-a'],
        ]);
        $ctx = H::context(caller: 'job', actor: H::user(7, null));
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->not->toBeNull();
});

it("edge: scope resolution when caller=job tenancy=multi actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 't1'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('t1');
});

it("fail: unresolved scope when caller=job tenancy=multi actor=system tenant_present=False and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("edge: scope resolution when caller=job tenancy=single actor=user tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=job tenancy=single actor=user tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = H::user(7);
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=job tenancy=single actor=system tenant_present=True [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = ["tenant_id" => "t1"];
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: scope resolution when caller=job tenancy=single actor=system tenant_present=False [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $actorObj = SystemActor::named("scheduler");
        $job = [];
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => $actorObj,
            'job' => $job,
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

