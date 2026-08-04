<?php

// REQ-012: Artisan adapter surface unit tests. Unit-only, no database.
// Artisan is in-server ops only — never the product CLI (D-016).

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Support\MissingArtisanActorException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: capability run artisan command requires acting-as or system for mutations [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);

    $withUser = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'acting_as' => 7,
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($withUser->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('artisan');

    $withSystem = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => 'scheduler',
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);
    expect($withSystem->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('fail: bare artisan capability run for mutating cap refused [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);

    expect(fn () => $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'mutating' => true,
    ]))->toThrow(MissingArtisanActorException::class);

    expect($h['runCount']->value)->toBe(0);
});

it('happy: artisan surface disabled registers no capability commands [SURF-003]', function () {
    $commands = ArtisanCommandTable::commands(['enabled' => false]);
    expect($commands)->toBe([])
        ->and(ArtisanCommandTable::isEnabled(['enabled' => false]))->toBeFalse()
        ->and(ArtisanCommandTable::isEnabled(['enabled' => true]))->toBeTrue()
        ->and(ArtisanCommandTable::commands(['enabled' => true]))->not->toBeEmpty()
        ->and(ArtisanCommandTable::commands(['enabled' => true])[0]['signature'] ?? '')
        ->toContain('capability:run');
});

it('edge: artisan is not the product CLI [D-016]', function () {
    expect(ArtisanCapabilityInvoker::isProductCli())->toBeFalse()
        ->and(ArtisanCapabilityInvoker::role())->toBe('ops')
        ->and(ArtisanCapabilityInvoker::caller())->toBe('artisan')
        ->and(ArtisanCapabilityInvoker::caller())->not->toBe('cli')
        ->and(ArtisanCommandTable::ROLE)->toBe('ops')
        ->and(ArtisanCommandTable::ROLE)->not->toBe('product_cli');
});

it('happy: artisan in-process invoke hits registry [PIPE-008]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
    $invoker = new ArtisanCapabilityInvoker($h['registry']);
    $before = $h['runCount']->value;

    $result = $invoker->run([
        'name' => $h['name'],
        'input' => H::homeInput(),
        'system' => 'scheduler',
        'tenant' => 'tenant-a',
        'mutating' => true,
    ]);

    expect($result->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe($before + 1)
        ->and($h['registry']->lastState())->not->toBeNull()
        ->and($h['registry']->lastState()?->context?->caller())->toBe('artisan');
});
