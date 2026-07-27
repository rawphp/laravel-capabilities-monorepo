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


it("happy: ScopeResolver resolve called after actor before authorize [D-003]", function () {
    $h = H::scopeHarness();
        $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        $stages = $h['registry']->lastStages();
        $actorIdx = array_search(PipelineStages::RESOLVE_ACTOR, $stages, true);
        $scopeIdx = array_search(PipelineStages::RESOLVE_SCOPE, $stages, true);
        $authIdx = array_search(PipelineStages::AUTHORIZE, $stages, true);
        expect($actorIdx)->toBeInt()->and($scopeIdx)->toBeInt()->and($authIdx)->toBeInt();
        expect($actorIdx < $scopeIdx)->toBeTrue()->and($scopeIdx < $authIdx)->toBeTrue();
});

it("happy: CapabilityScope query delegates to ScopedQueryFactory [D-003]", function () {
    $factory = new InMemoryScopedQueryFactory();
        $factory->put('Customer', [1 => ['tenant_id' => 't1', 'data' => ['n' => 'a']]]);
        $scope = (new CapabilityScope(tenantId: 't1'))->withQueryFactory($factory);
        $row = $scope->query('Customer')->find(1);
        expect($row)->not->toBeNull()->and($row['tenant_id'])->toBe('t1');
        $miss = $scope->query('Customer')->find(99);
        expect($miss)->toBeNull();
});

it("happy: user scope from membership not from untrusted input alone [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'user_tenants' => [7 => 'tenant-a'],
            'memberships' => ['7' => ['tenant-a']],
            'tenancy_required' => true,
        ]);
        $ctx = H::context(actor: H::user(7, 'tenant-a'), extra: ['attributes' => ['tenant_hint' => 'tenant-b']]);
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('tenant-a');
});

it("fail: SystemActor without tenantId throws when tenancy required and not globalSystem [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("fail: SystemActor scope never reads input magic key _tenant_id [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('_tenant_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'evil']))->toBeTrue();
});

it("fail: SystemActor scope never reads input magic key tenant_id [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('tenant_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['tenant_id' => 'evil']))->toBeTrue();
});

it("fail: SystemActor scope never reads input magic key tenantId [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('tenantId');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['tenantId' => 'evil']))->toBeTrue();
});

it("fail: SystemActor scope never reads input magic key organization_id [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('organization_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['organization_id' => 'evil']))->toBeTrue();
});

it("fail: SystemActor scope never reads input magic key team_id [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('team_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['team_id' => 'evil']))->toBeTrue();
});

it("fail: SystemActor scope never reads input magic key scope_id [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'trusted'],
            'attributes' => [],
        ]);
        // Input is not passed into context attributes — magic key cannot affect scope
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('trusted');
        expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('scope_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['scope_id' => 'evil']))->toBeTrue();
});

it("happy: SystemActor tenant from first-class job tenantId only [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'job-tenant'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('job-tenant');
});

it("happy: SystemActor tenant from trusted context attributes only [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'attributes' => ['tenant_id' => 'attr-tenant'],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBe('attr-tenant');
});

it("happy: globalSystem capability allows null tenant for system actor [D-003]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'attributes' => ['global_system' => true],
        ]);
        expect($resolver->resolve($ctx)->tenantId)->toBeNull();
});

it("happy: single-tenant resolver still runs and returns default scope [D-003]", function () {
    $resolver = new DefaultScopeResolver(['single_tenant_id' => 'default']);
        $ctx = H::context();
        expect($resolver->resolve($ctx)->tenantId)->toBe('default');
});

it("edge: X-Tenant-Id header is hint only and must pass membership check [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'user_tenants' => [7 => 'tenant-a'],
            'memberships' => ['7' => ['tenant-a']],
            'tenancy_required' => true,
        ]);
        $ctx = H::context(actor: H::user(7), extra: ['attributes' => ['tenant_hint' => 'tenant-b', 'x_tenant_id' => 'tenant-b', 'cli_tenant' => 'tenant-b']]);
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('tenant-a');
});

it("edge: CLI --tenant is hint only and must pass membership check [D-003]", function () {
    $resolver = new DefaultScopeResolver([
            'user_tenants' => [7 => 'tenant-a'],
            'memberships' => ['7' => ['tenant-a']],
            'tenancy_required' => true,
        ]);
        $ctx = H::context(actor: H::user(7), extra: ['attributes' => ['tenant_hint' => 'tenant-b', 'x_tenant_id' => 'tenant-b', 'cli_tenant' => 'tenant-b']]);
        $scope = $resolver->resolve($ctx);
        expect($scope->tenantId)->toBe('tenant-a');
});

it("fail: cross-tenant customer_id via caller agent denies authorize via scoped query null [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: cross-tenant resource via caller agent does not run [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("fail: cross-tenant customer_id via caller mcp denies authorize via scoped query null [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: cross-tenant resource via caller mcp does not run [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("fail: cross-tenant customer_id via caller http denies authorize via scoped query null [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: cross-tenant resource via caller http does not run [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("fail: cross-tenant customer_id via caller cli denies authorize via scoped query null [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: cross-tenant resource via caller cli does not run [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("fail: cross-tenant customer_id via caller job denies authorize via scoped query null [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: cross-tenant resource via caller job does not run [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: assertCannotInvokeAcrossTenant helper fails test when leak possible [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        expect(fn () => $h['registry']->assertCannotInvokeAcrossTenant([
            'name' => $h['name'],
            'input' => H::foreignInput(),
            'foreignTenant' => 'tenant-b',
            'tenant_id' => 'tenant-a',
            'options' => ['actor' => H::user(7), 'require_scope' => true],
        ]))->not->toThrow(Throwable::class);
        // helper throws when leak (success) — force allow to prove failure path
        $leaky = H::scopeHarness(['name' => 'leaky', 'authorize_cross_tenant' => false, 'authorize_cb' => fn () => true]);
        expect(fn () => $leaky['registry']->assertCannotInvokeAcrossTenant([
            'name' => 'leaky',
            'input' => H::foreignInput(),
            'foreignTenant' => 'tenant-b',
            'tenant_id' => 'tenant-a',
            'options' => ['actor' => H::user(7)],
        ]))->toThrow(InvalidArgumentException::class);
});

it("happy: package examples never teach input tenant for SystemActor [P2-005]", function () {
    expect(DefaultScopeResolver::FORBIDDEN_SYSTEM_INPUT_KEYS)->toContain('_tenant_id');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'x']))->toBeTrue();
});

it("fail: unusable scope when tenancy required fails closed [D-003]", function () {
    $user = H::user(7, null);
    unset($user->current_tenant_id);
    $resolver = new DefaultScopeResolver(['tenancy_required' => true, 'user_tenants' => [], 'memberships' => []]);
    expect(fn () => (new ResolveTenantFromCaller($resolver))
            ->resolve(H::context(actor: $user, extra: ['attributes' => []]), ['require_scope' => true]))
            ->toThrow(UnresolvedScopeException::class);
});

it("fail: exists global without scope is insufficient alone for multi-tenant safety [D-003]", function () {
    // Document package stance: global exists ≠ tenancy
        $factory = new InMemoryScopedQueryFactory();
        $factory->put('Customer', [99 => ['tenant_id' => 'other']]);
        $scope = (new CapabilityScope(tenantId: 'tenant-a'))->withQueryFactory($factory);
        expect($scope->query('Customer')->find(99))->toBeNull();
});

it("happy: scoped re-resolve on approval accept [D-006]", function () {
    // On approval accept, authorize/run must still re-resolve under original scope
        $h = H::scopeHarness();
        $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', ['tenant_id' => 'tenant-a']));
        expect($h['registry']->lastScopeTenant())->toBe('tenant-a');
        // foreign id still denied under same scope
        $r2 = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', ['tenant_id' => 'tenant-a', 'require_scope' => true]));
        expect($r2->isOk())->toBeFalse();
});

it("edge: teamId organizationId convenience when app uses those dimensions [D-003]", function () {
    $scope = new CapabilityScope(tenantId: 't', teamId: 'team', organizationId: 'org');
        $ctx = H::context(scope: $scope);
        expect($ctx->teamId())->toBe('team')->and($ctx->organizationId())->toBe('org');
});

it("fail: MissingJobTenantException when system job omits tenantId and tenancy required [P2-005]", function () {
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
        $ctx = CapabilityContext::make([
            'caller' => 'job',
            'actor' => SystemActor::named('scheduler'),
            'job' => [],
        ]);
        expect(fn () => $resolver->resolve($ctx))->toThrow(MissingJobTenantException::class);
});

it("happy: assertLastScopeTenant reflects first-class tenant not smuggled input [P2-005]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
            // attributes must not smuggle wire input into system scope
            'attributes' => [],
        ]));
        $h['registry']->assertLastScopeTenant('tenant-a');
        expect($h['registry']->lastScopeTenant())->not->toBe('evil');
        expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'evil']))->toBeTrue();
});

it("happy: assertScopeResolvedTo fails when scope mismatches [D-003]", function () {
    $h = H::scopeHarness();
        $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', ['tenant_id' => 'tenant-a']));
        expect(fn () => $h['registry']->assertScopeResolvedTo('wrong'))->toThrow(InvalidArgumentException::class);
        expect($h['registry']->assertScopeResolvedTo('tenant-a'))->toBeTrue();
});

it("edge: parity dataset deny class for cross-tenant via agent [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("edge: parity dataset deny class for cross-tenant via mcp [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("edge: parity dataset deny class for cross-tenant via http [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("edge: parity dataset deny class for cross-tenant via cli [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("edge: parity dataset deny class for cross-tenant via job [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

