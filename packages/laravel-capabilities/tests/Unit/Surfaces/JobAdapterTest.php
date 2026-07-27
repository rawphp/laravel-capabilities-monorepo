<?php

// REQ-012: Job adapter surface unit tests. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\JobSurface;
use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: RunCapability job payload requires actingAs [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('job')
        ->and($h['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('happy: RunCapability passes tenantId as first-class field [P2-005]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastScopeTenant())->toBe('tenant-a');
});

it('fail: RunCapability refuses tenant magic keys from input for system scope [P2-005]', function () {
    expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'evil']))->toBeTrue();

    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastScopeTenant())->toBe('tenant-a')
        ->and($h['registry']->lastScopeTenant())->not->toBe('evil');
});

it('happy: job optional idempotencyKey forwarded [D-005]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'idempotencyKey' => 'job-key-abc',
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->idempotencyKey)->toBe('job-key-abc');
});

it('fail: dispatch without actingAs is not enqueued [D-002]', function () {
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'x', 'input' => []]))
        ->toThrow(MissingJobActorException::class);
    expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))
        ->toThrow(MissingJobActorException::class);
    expect(fn () => RunCapabilityJob::dispatchSync(
        H::scopeHarness()['registry'],
        ['name' => 'x', 'input' => []],
    ))->toThrow(MissingJobActorException::class);
});

it('edge: failed job tagged with capability name [D-019]', function () {
    $job = new RunCapabilityJob(
        name: 'daily-reconciliation',
        actingAs: SystemActor::named('scheduler'),
        tenantId: 't1',
    );
    expect($job->failureTags()['capability'])->toBe('daily-reconciliation')
        ->and($job->failureTags()['caller'])->toBe('job');
});

it('happy: job handle uses registry not domain action directly [PIPE-008]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $before = $h['runCount']->value;
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe($before + 1)
        ->and($h['registry']->lastState())->not->toBeNull()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('job');
});

it('fail: job surface disabled does not register RunCapability helpers [SURF-003]', function () {
    $helpers = JobSurface::registeredHelpers(['enabled' => false]);
    expect($helpers)->toBe([])
        ->and(JobSurface::isEnabled(['enabled' => false]))->toBeFalse()
        ->and(JobSurface::isEnabled(['enabled' => true]))->toBeTrue()
        ->and(JobSurface::registeredHelpers(['enabled' => true]))->not->toBeEmpty()
        ->and(JobSurface::registeredHelpers(['enabled' => true]))->toContain(RunCapabilityJob::class);
});
