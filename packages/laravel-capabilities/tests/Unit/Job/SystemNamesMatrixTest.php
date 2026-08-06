<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: system actor scheduler allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('scheduler'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor scheduler denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('scheduler'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: system actor reconciliation allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['reconciliation']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('reconciliation'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor reconciliation denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('reconciliation'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: system actor horizon allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['horizon']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('horizon'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor horizon denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('horizon'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: system actor billing-bot allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['billing-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('billing-bot'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor billing-bot denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('billing-bot'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: system actor mcp-billing-service allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['mcp-billing-service']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('mcp-billing-service'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor mcp-billing-service denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('mcp-billing-service'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: system actor unknown allowed when listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['unknown']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('unknown'),
        'job' => ['tenant_id' => 'tenant-a'],
        'tenant_id' => 'tenant-a',
    ]));
    expect($result->isOk())->toBeTrue();
});

it('fail: system actor unknown denied when not listed [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['other-bot']]);
    $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
        'actor' => SystemActor::named('unknown'),
        'job' => ['tenant_id' => 'tenant-a'],
    ]));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: empty system actor name rejected [D-002]', function () {
    expect(fn () => SystemActor::named(''))->toThrow(InvalidArgumentException::class);
});

it('fail: empty system actor name rejected (case 1) [D-002]', function () {
    expect(fn () => SystemActor::named(''))->toThrow(InvalidArgumentException::class);
});
