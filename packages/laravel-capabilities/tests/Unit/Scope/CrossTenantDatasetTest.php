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


it("fail: dataset cross-tenant deny via agent [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("happy: dataset same-tenant allow control via agent [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it("fail: dataset cross-tenant deny via mcp [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("happy: dataset same-tenant allow control via mcp [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it("fail: dataset cross-tenant deny via http [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("happy: dataset same-tenant allow control via http [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it("fail: dataset cross-tenant deny via cli [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("happy: dataset same-tenant allow control via cli [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it("fail: dataset cross-tenant deny via job [D-003]", function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("happy: dataset same-tenant allow control via job [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

