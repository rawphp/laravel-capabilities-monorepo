<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

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

it('fail: denied when allow=[] actor=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('scheduler'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: denied when allow=[] actor=reconciliation [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('reconciliation'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('fail: denied when allow=[] actor=evil [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => []]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('evil'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
});

it('edge: allow any system when allow=True actor=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('scheduler'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('edge: allow any system when allow=True actor=reconciliation [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('reconciliation'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('edge: allow any system when allow=True actor=evil [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('evil'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});
