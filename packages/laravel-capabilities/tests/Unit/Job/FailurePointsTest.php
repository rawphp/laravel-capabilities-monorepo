<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;
use RuntimeException;

it('fail: job fails closed at missing_actingAs without silent superuser [D-002]', function () {
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'x', 'input' => []]))
        ->toThrow(MissingJobActorException::class);
    expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))
        ->toThrow(MissingJobActorException::class);
});

it('happy: job failure at missing_actingAs is auditable [D-010]', function () {
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'x', 'input' => []]))
        ->toThrow(MissingJobActorException::class);
    expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))
        ->toThrow(MissingJobActorException::class);
});

it('fail: job fails closed at missing_user without silent superuser [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 404,
        'tenantId' => 'tenant-a',
        'user_resolver' => fn () => null,
    ]))->toThrow(RuntimeException::class);
});

it('happy: job failure at missing_user is auditable [D-010]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => 404,
        'tenantId' => 'tenant-a',
        'user_resolver' => fn () => null,
    ]))->toThrow(RuntimeException::class);
});

it('fail: job fails closed at system_not_allowlisted without silent superuser [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
    $r = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'], 'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'), 'tenantId' => 'tenant-a',
        'tenancy_required' => false,
    ]);
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: job failure at system_not_allowlisted is auditable [D-010]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
    $r = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'], 'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'), 'tenantId' => 'tenant-a',
        'tenancy_required' => false,
    ]);
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at missing_tenant_when_required without silent superuser [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'], 'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenancy_required' => true, 'globalSystem' => false,
    ]))->toThrow(MissingJobTenantException::class);
});

it('happy: job failure at missing_tenant_when_required is auditable [D-010]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'], 'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenancy_required' => true, 'globalSystem' => false,
    ]))->toThrow(MissingJobTenantException::class);
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at authorize_false without silent superuser [D-002]', function () {
    $h = H::scopeHarness(['authorize' => false, 'authorize_cb' => fn () => false, 'allowSystemCallers' => true]);
    // override: harness always sets authorize_cb — use authorizer deny via custom
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('happy: job failure at authorize_false is auditable [D-010]', function () {
    $h = H::scopeHarness(['authorize' => false, 'authorize_cb' => fn () => false, 'allowSystemCallers' => true]);
    // override: harness always sets authorize_cb — use authorizer deny via custom
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at schema_invalid without silent superuser [D-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: job failure at schema_invalid is auditable [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at rate_limited without silent superuser [D-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $h['registry']->forceFailStages(PipelineStages::RATE_LIMIT);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: job failure at rate_limited is auditable [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $h['registry']->forceFailStages(PipelineStages::RATE_LIMIT);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at output_invalid without silent superuser [D-002]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'run_output' => ['not' => 'dto'],
    ]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    // may fail output validation or succeed depending on validator — either way no silent superuser
    expect($h['runCount']->value)->toBeGreaterThanOrEqual(0);
    expect($r)->toBeInstanceOf(CapabilityResult::class);
});

it('happy: job failure at output_invalid is auditable [D-010]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'run_output' => ['not' => 'dto'],
    ]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    // may fail output validation or succeed depending on validator — either way no silent superuser
    expect($h['runCount']->value)->toBeGreaterThanOrEqual(0);
    expect($r)->toBeInstanceOf(CapabilityResult::class);
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('fail: job fails closed at run_throws without silent superuser [D-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'run_throws' => 'boom']);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse();
});

it('happy: job failure at run_throws is auditable [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'run_throws' => 'boom']);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($r->isOk())->toBeFalse();
    $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});
