<?php

// REQ-012: Artisan flag matrix for mutating capability:run (D-002). Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker;
use Rawphp\Capabilities\Support\InvalidArtisanFlagsException;
use Rawphp\Capabilities\Support\MissingArtisanActorException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('edge: artisan mutate path when --acting-as=1 [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags(['acting-as' => '1']);

    expect($parsed['acting_as'])->toBe(1)
        ->and($parsed['system'])->toBeNull();

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'acting_as' => $parsed['acting_as'],
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('artisan');
});

it('edge: artisan mutate path when --system=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags(['system' => 'scheduler']);

    expect($parsed['system'])->toBe('scheduler')
        ->and($parsed['acting_as'])->toBeNull();

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => $parsed['system'],
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('edge: artisan mutate path when --system=scheduler --tenant=t1 [D-002]', function () {
    // Seed home tenant as t1 so scoped authorize matches --tenant=t1.
    $h = H::scopeHarness([
        'allowSystemCallers' => ['scheduler'],
        'tenancy_required' => true,
        'tenant_id' => 't1',
    ]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags([
        'system' => 'scheduler',
        'tenant' => 't1',
    ]);

    expect($parsed['system'])->toBe('scheduler')
        ->and($parsed['tenant'])->toBe('t1');

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => $parsed['system'],
        'tenant' => $parsed['tenant'],
        'mutating' => true,
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastScopeTenant())->toBe('t1');
});

it('fail: artisan mutate refused or invalid when no-flags [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $parsed = ArtisanCapabilityInvoker::parseFlags([]);

    expect($parsed['acting_as'])->toBeNull()
        ->and($parsed['system'])->toBeNull();

    expect(fn () => $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'mutating' => true,
    ]))->toThrow(MissingArtisanActorException::class);

    expect($h['runCount']->value)->toBe(0);
});

it('fail: artisan mutate refused or invalid when --acting-as=1 --system=scheduler [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);

    expect(fn () => ArtisanCapabilityInvoker::parseFlags([
        'acting-as' => '1',
        'system' => 'scheduler',
    ]))->toThrow(InvalidArtisanFlagsException::class);

    expect(fn () => $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'acting_as' => 1,
        'system' => 'scheduler',
        'mutating' => true,
    ]))->toThrow(InvalidArtisanFlagsException::class);

    expect($h['runCount']->value)->toBe(0);
});
