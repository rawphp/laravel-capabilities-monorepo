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


it("happy: allowed when allow=['scheduler'] actor=scheduler [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("fail: denied when allow=['scheduler'] actor=reconciliation [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: denied when allow=['scheduler'] actor=evil [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('evil'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("happy: allowed when allow=['scheduler', 'reconciliation'] actor=scheduler [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler', 'reconciliation']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("happy: allowed when allow=['scheduler', 'reconciliation'] actor=reconciliation [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler', 'reconciliation']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("fail: denied when allow=['scheduler', 'reconciliation'] actor=evil [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler', 'reconciliation']]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('evil'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: denied when allow=[] actor=scheduler [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: denied when allow=[] actor=reconciliation [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("fail: denied when allow=[] actor=evil [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('evil'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it("edge: allow any system when allow=True actor=scheduler [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("edge: allow any system when allow=True actor=reconciliation [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("edge: allow any system when allow=True actor=evil [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('evil'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

