<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('fail: attack customer_id_other_tenant via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack customer_id_other_tenant via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack customer_id_other_tenant via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack customer_id_other_tenant via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack customer_id_other_tenant via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack customer_id_other_tenant via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack customer_id_other_tenant via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack customer_id_other_tenant via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack customer_id_other_tenant via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack customer_id_other_tenant via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack invoice_id_other_tenant via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack invoice_id_other_tenant via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack invoice_id_other_tenant via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack invoice_id_other_tenant via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack invoice_id_other_tenant via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack invoice_id_other_tenant via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack invoice_id_other_tenant via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack invoice_id_other_tenant via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack invoice_id_other_tenant via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack invoice_id_other_tenant via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack team_id_spoof_header via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack team_id_spoof_header via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack team_id_spoof_header via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack team_id_spoof_header via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack team_id_spoof_header via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack team_id_spoof_header via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack team_id_spoof_header via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack team_id_spoof_header via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack team_id_spoof_header via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack team_id_spoof_header via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack tenant_id_in_body via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack tenant_id_in_body via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack tenant_id_in_body via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack tenant_id_in_body via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack tenant_id_in_body via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack tenant_id_in_body via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack tenant_id_in_body via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack tenant_id_in_body via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack tenant_id_in_body via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack tenant_id_in_body via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack organization_id_in_query via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack organization_id_in_query via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack organization_id_in_query via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack organization_id_in_query via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack organization_id_in_query via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack organization_id_in_query via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack organization_id_in_query via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack organization_id_in_query via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack organization_id_in_query via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack organization_id_in_query via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack nested_resource_other_tenant via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack nested_resource_other_tenant via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack nested_resource_other_tenant via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack nested_resource_other_tenant via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack nested_resource_other_tenant via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack nested_resource_other_tenant via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack nested_resource_other_tenant via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack nested_resource_other_tenant via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack nested_resource_other_tenant via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack nested_resource_other_tenant via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack batch_ids_mixed_tenants via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack batch_ids_mixed_tenants via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack batch_ids_mixed_tenants via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack batch_ids_mixed_tenants via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack batch_ids_mixed_tenants via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack batch_ids_mixed_tenants via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack batch_ids_mixed_tenants via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack batch_ids_mixed_tenants via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack batch_ids_mixed_tenants via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack batch_ids_mixed_tenants via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack alias_id_other_tenant via agent denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack alias_id_other_tenant via agent produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('agent', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack alias_id_other_tenant via mcp denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack alias_id_other_tenant via mcp produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('mcp', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack alias_id_other_tenant via http denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack alias_id_other_tenant via http produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('http', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack alias_id_other_tenant via cli denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack alias_id_other_tenant via cli produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('cli', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});

it('fail: attack alias_id_other_tenant via job denied without run [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $result = $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: attack alias_id_other_tenant via job produces no domain side effects [D-003]', function () {
    $h = H::scopeHarness(['tenancy_required' => true]);
    $h['registry']->invoke($h['name'], H::foreignInput(), H::options('job', [
        'tenant_id' => 'tenant-a',
        'require_scope' => true,
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($h['runCount']->value)->toBe(0)->and($h['runCount']->sideEffect)->toBeFalse();
});
