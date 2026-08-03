<?php

declare(strict_types=1);

use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: authorize re-resolves resources under scope for agent [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('happy: run re-resolves resources under scope for agent [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['runCount']->resolved)->not->toBeNull();
    expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it('fail: authorize does not trust wire id alone for agent [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: run does not trust wire id alone for agent [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: authorize re-resolves resources under scope for mcp [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('happy: run re-resolves resources under scope for mcp [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['runCount']->resolved)->not->toBeNull();
    expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it('fail: authorize does not trust wire id alone for mcp [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: run does not trust wire id alone for mcp [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: authorize re-resolves resources under scope for http [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('happy: run re-resolves resources under scope for http [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['runCount']->resolved)->not->toBeNull();
    expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it('fail: authorize does not trust wire id alone for http [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: run does not trust wire id alone for http [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: authorize re-resolves resources under scope for cli [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('happy: run re-resolves resources under scope for cli [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['runCount']->resolved)->not->toBeNull();
    expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it('fail: authorize does not trust wire id alone for cli [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: run does not trust wire id alone for cli [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('happy: authorize re-resolves resources under scope for job [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('happy: run re-resolves resources under scope for job [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeTrue();
    expect($h['runCount']->resolved)->not->toBeNull();
    expect($h['runCount']->resolved['tenant_id'])->toBe('tenant-a');
});

it('fail: authorize does not trust wire id alone for job [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: run does not trust wire id alone for job [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a', 'require_scope' => true, 'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});
