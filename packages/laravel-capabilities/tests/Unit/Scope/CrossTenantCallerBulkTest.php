<?php

declare(strict_types=1);

use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('fail: cross-tenant customer via agent denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant customer via agent no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant customer via agent may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant invoice via agent denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant invoice via agent no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant invoice via agent may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant subscription via agent denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant subscription via agent no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant subscription via agent may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant payment_method via agent denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant payment_method via agent no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant payment_method via agent may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant report via agent denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant report via agent no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant report via agent may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant customer via mcp denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant customer via mcp no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant customer via mcp may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant invoice via mcp denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant invoice via mcp no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant invoice via mcp may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant subscription via mcp denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant subscription via mcp no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant subscription via mcp may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant payment_method via mcp denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant payment_method via mcp no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant payment_method via mcp may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant report via mcp denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant report via mcp no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant report via mcp may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant customer via http denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant customer via http no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant customer via http may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant invoice via http denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant invoice via http no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant invoice via http may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant subscription via http denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant subscription via http no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant subscription via http may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant payment_method via http denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant payment_method via http no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant payment_method via http may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant report via http denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant report via http no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant report via http may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant customer via cli denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant customer via cli no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant customer via cli may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant invoice via cli denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant invoice via cli no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant invoice via cli may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant subscription via cli denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant subscription via cli no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant subscription via cli may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant payment_method via cli denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant payment_method via cli no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant payment_method via cli may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant report via cli denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant report via cli no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant report via cli may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant customer via job denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant customer via job no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant customer via job may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant invoice via job denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant invoice via job no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant invoice via job may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant subscription via job denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant subscription via job no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant subscription via job may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant payment_method via job denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant payment_method via job no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant payment_method via job may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});

it('fail: cross-tenant report via job denied [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: cross-tenant report via job no run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: same-tenant report via job may authorize [D-003]', function () {
    $h = H::scopeHarness();
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('http'));
    expect($result->isOk())->toBeTrue();
    expect($h['registry']->lastStages())->toContain(PipelineStages::RESOLVE_SCOPE);
});
