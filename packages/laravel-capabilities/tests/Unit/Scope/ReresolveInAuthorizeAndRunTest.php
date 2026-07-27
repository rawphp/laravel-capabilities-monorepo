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


it("happy: authorize re-resolves resources under scope for agent [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("happy: run re-resolves resources under scope for agent [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['runCount']->resolved)->not->toBeNull();
        expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it("fail: authorize does not trust wire id alone for agent [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: run does not trust wire id alone for agent [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("happy: authorize re-resolves resources under scope for mcp [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("happy: run re-resolves resources under scope for mcp [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['runCount']->resolved)->not->toBeNull();
        expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it("fail: authorize does not trust wire id alone for mcp [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: run does not trust wire id alone for mcp [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("happy: authorize re-resolves resources under scope for http [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("happy: run re-resolves resources under scope for http [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['runCount']->resolved)->not->toBeNull();
        expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it("fail: authorize does not trust wire id alone for http [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: run does not trust wire id alone for http [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("happy: authorize re-resolves resources under scope for cli [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("happy: run re-resolves resources under scope for cli [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['runCount']->resolved)->not->toBeNull();
        expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it("fail: authorize does not trust wire id alone for cli [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: run does not trust wire id alone for cli [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("happy: authorize re-resolves resources under scope for job [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("happy: run re-resolves resources under scope for job [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeTrue();
        expect($h['runCount']->resolved)->not->toBeNull();
        expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it("fail: authorize does not trust wire id alone for job [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: run does not trust wire id alone for job [D-003]", function () {
    $h = H::scopeHarness();
        $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
            'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

