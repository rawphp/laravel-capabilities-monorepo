<?php

declare(strict_types=1);

use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('fail: dataset cross-tenant deny via agent [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: dataset same-tenant allow control via agent [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: dataset cross-tenant deny via mcp [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: dataset same-tenant allow control via mcp [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: dataset cross-tenant deny via http [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: dataset same-tenant allow control via http [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: dataset cross-tenant deny via cli [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: dataset same-tenant allow control via cli [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: dataset cross-tenant deny via job [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: dataset same-tenant allow control via job [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});
