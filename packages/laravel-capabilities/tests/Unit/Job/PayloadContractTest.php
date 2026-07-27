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


it("happy: job payload may include name [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->name)->not->toBeNull();
});

it("happy: job payload may include input [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->input)->not->toBeNull();
});

it("happy: job payload may include actingAs [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->actingAs)->not->toBeNull();
});

it("happy: job payload may include tenantId [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->tenantId)->not->toBeNull();
});

it("happy: job payload may include teamId [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->teamId)->not->toBeNull();
});

it("happy: job payload may include organizationId [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->organizationId)->not->toBeNull();
});

it("happy: job payload may include idempotencyKey [D-002]", function () {
    $job = RunCapabilityJob::fromPayload([
            'name' => 'cap',
            'input' => ['a' => 1],
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 't1',
            'teamId' => 'team',
            'organizationId' => 'org',
            'idempotencyKey' => 'k1',
        ]);
        expect($job->idempotencyKey)->not->toBeNull();
});

it("fail: job payload input must not be used as tenant authority for SystemActor [P2-005]", function () {
    // First-class tenantId is authority; wire input magic keys are not (P2-005).
    expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'evil']))->toBeTrue();
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastScopeTenant())->toBe('tenant-a');
    expect($h['registry']->lastScopeTenant())->not->toBe('evil');
});

it("fail: job payload missing actingAs not dispatchable [D-002]", function () {
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'x', 'input' => []]))
            ->toThrow(MissingJobActorException::class);
        expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))
            ->toThrow(MissingJobActorException::class);
});

