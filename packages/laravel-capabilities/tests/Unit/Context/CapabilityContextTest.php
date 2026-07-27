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


it("happy: context always has non-null actor after build [CTX-001]", function () {
    $ctx = H::context();
        expect($ctx->actor())->toBeObject()->and($ctx->actor())->not->toBeNull();
});

it("happy: user returns User when actor is User [CTX-001]", function () {
    $u = H::user(3);
        $ctx = H::context(actor: $u);
        expect($ctx->user())->toBe($u)->and($ctx->user()->id)->toBe(3);
});

it("happy: user is null when actor is SystemActor [CTX-001]", function () {
    $ctx = H::context(caller: 'job', actor: H::system());
        expect($ctx->user())->toBeNull()->and($ctx->actor())->toBeInstanceOf(SystemActor::class);
});

it("happy: caller accepts value agent [CTX-001]", function () {
    $ctx = H::context(caller: 'agent', actor: H::user());
        expect($ctx->caller())->toBe('agent');
});

it("happy: caller accepts value mcp [CTX-001]", function () {
    $ctx = H::context(caller: 'mcp', actor: H::user());
        expect($ctx->caller())->toBe('mcp');
});

it("happy: caller accepts value http [CTX-001]", function () {
    $ctx = H::context(caller: 'http', actor: H::user());
        expect($ctx->caller())->toBe('http');
});

it("happy: caller accepts value cli [CTX-001]", function () {
    $ctx = H::context(caller: 'cli', actor: H::user());
        expect($ctx->caller())->toBe('cli');
});

it("happy: caller accepts value job [CTX-001]", function () {
    $ctx = H::context(caller: 'job', actor: H::user());
        expect($ctx->caller())->toBe('job');
});

it("fail: caller rejects unknown value [CTX-001]", function () {
    expect(fn () => CapabilityContext::make(['caller' => 'not-a-caller', 'actor' => H::user()]))
            ->toThrow(InvalidArgumentException::class);
});

it("happy: scope attached after ResolveTenantFromCaller [D-003]", function () {
    $user = H::user(7, null);
    unset($user->current_tenant_id);
    $ctx = H::context(actor: $user);
        $scope = (new ResolveTenantFromCaller(new DefaultScopeResolver(['user_tenants' => []])))
            ->resolve($ctx, ['tenant_id' => 'acme']);
        $ctx2 = $ctx->withScope($scope);
        expect($ctx2->scope())->toBeInstanceOf(CapabilityScope::class)->and($ctx2->tenantId())->toBe('acme');
});

it("happy: accessor tenantId available when set [CTX-001]", function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
        $ctx = H::context(scope: $scope);
        expect($ctx->tenantId())->toBe('t1');
});

it("happy: accessor teamId available when set [CTX-001]", function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
        $ctx = H::context(scope: $scope);
        expect($ctx->teamId())->toBe('team-1');
});

it("happy: accessor organizationId available when set [CTX-001]", function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
        $ctx = H::context(scope: $scope);
        expect($ctx->organizationId())->toBe('org-1');
});

it("happy: accessor requestId available when set [CTX-001]", function () {
    $ctx = H::context(extra: ['request_id' => 'req-1']);
        expect($ctx->requestId())->toBe('req-1');
});

it("happy: accessor traceId available when set [CTX-001]", function () {
    $ctx = H::context(extra: ['trace_id' => 'tr-1']);
        expect($ctx->traceId())->toBe('tr-1');
});

it("happy: agent metadata optional when caller agent [CTX-001]", function () {
    $ctx = H::context(caller: 'agent', extra: ['agent' => ['turn_id' => 't1']]);
        expect($ctx->agent())->toBeArray()->and($ctx->agent()['turn_id'])->toBe('t1');
});

it("happy: mcp metadata when caller mcp includes auth_profile [D-023]", function () {
    $ctx = H::context(caller: 'mcp', extra: ['mcp' => ['auth_profile' => 'user_pat', 'client_id' => 'c1']]);
        expect($ctx->mcp())->toBeArray()->and($ctx->mcp()['auth_profile'])->toBe('user_pat');
});

it("happy: mcp auth_profile accepts user_pat [D-023]", function () {
    $ctx = H::context(caller: 'mcp', extra: ['mcp' => ['auth_profile' => 'user_pat']]);
        expect($ctx->mcp()['auth_profile'])->toBe('user_pat');
});

it("happy: mcp auth_profile accepts integration [D-023]", function () {
    $ctx = H::context(caller: 'mcp', extra: ['mcp' => ['auth_profile' => 'integration']]);
        expect($ctx->mcp()['auth_profile'])->toBe('integration');
});

it("happy: mcp auth_profile accepts user_delegated [D-023]", function () {
    $ctx = H::context(caller: 'mcp', extra: ['mcp' => ['auth_profile' => 'user_delegated']]);
        expect($ctx->mcp()['auth_profile'])->toBe('user_delegated');
});

it("happy: messaging metadata optional on agent turns from chat [D-007]", function () {
    $ctx = H::context(caller: 'agent', extra: ['messaging' => ['channel' => 'telegram']]);
        expect($ctx->messaging())->toBeArray()->and($ctx->messaging()['channel'])->toBe('telegram');
});

it("happy: job metadata optional when caller job [D-002]", function () {
    $ctx = H::context(caller: 'job', actor: H::system(), extra: ['job' => ['tenant_id' => 't7']]);
        expect($ctx->job())->toBeArray()->and($ctx->jobTenantId())->toBe('t7');
});

it("happy: credential audit metadata optional [D-022]", function () {
    $ctx = H::context(extra: ['credential' => ['type' => 'sanctum', 'ability' => 'capabilities:cli']]);
        expect($ctx->credential())->toBeArray()->and($ctx->credential()['type'])->toBe('sanctum');
});

it("fail: context build with null principal is refused [CTX-001]", function () {
    expect(fn () => CapabilityContext::make(['caller' => 'http', 'actor' => null]))
            ->toThrow(InvalidArgumentException::class);
});

it("edge: messaging-originated tools still use caller agent at registry [D-007]", function () {
    $ctx = H::context(caller: 'agent', extra: ['messaging' => ['channel' => 'telegram']]);
        expect($ctx->caller())->toBe('agent')->and($ctx->messaging())->not->toBeNull();
});

