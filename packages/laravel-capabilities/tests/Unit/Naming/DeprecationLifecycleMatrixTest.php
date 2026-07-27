<?php

// REQ-010 fleshed unit tests for Naming/DeprecationLifecycleMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use DateTimeImmutable;
use stdClass;

it("happy: canonical invoke in phase active resolves and runs once [D-012]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'sunset_at' => '2099-01-01']);
    expect($h['catalog']->describe($h['name'])['deprecated'])->toBeTrue();
});

it("edge: catalog shows deprecation metadata in phase active for canonical [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2099-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});

it("happy: alias invoke in phase active resolves and runs once [D-012]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'sunset_at' => '2099-01-01']);
    expect($h['catalog']->describe($h['name'])['deprecated'])->toBeTrue();
});

it("edge: catalog shows deprecation metadata in phase active for alias [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2099-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});

it("happy: canonical invoke in phase deprecated_before_sunset resolves and runs once [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
    ]);
    $canon = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options());
    $alias = $h['registry']->invoke('invoice.create', CatalogHelpers::input(), CatalogHelpers::options());
    expect($canon->isOk())->toBeTrue()->and($alias->isOk())->toBeTrue();
});

it("edge: catalog shows deprecation metadata in phase deprecated_before_sunset for canonical [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2099-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});

it("happy: alias invoke in phase deprecated_before_sunset resolves and runs once [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
    ]);
    $canon = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options());
    $alias = $h['registry']->invoke('invoice.create', CatalogHelpers::input(), CatalogHelpers::options());
    expect($canon->isOk())->toBeTrue()->and($alias->isOk())->toBeTrue();
});

it("edge: catalog shows deprecation metadata in phase deprecated_before_sunset for alias [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2099-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});

it("fail: canonical invoke in phase after_sunset returns 410 [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2020-01-01',
        'successor' => 'create-invoice-v2',
        'aliases' => ['invoice.create'],
    ]);
    $counter = new stdClass; $counter->value = 0;
    // definition already registered without counter — assert gone
    $r = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options());
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('gone')
        ->and($r->error['http_status'] ?? null)->toBe(410);
});

it("edge: catalog shows deprecation metadata in phase after_sunset for canonical [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2020-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2020-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});

it("fail: alias invoke in phase after_sunset returns 410 [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2020-01-01',
        'successor' => 'create-invoice-v2',
        'aliases' => ['invoice.create'],
    ]);
    $counter = new stdClass; $counter->value = 0;
    // definition already registered without counter — assert gone
    $r = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options());
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('gone')
        ->and($r->error['http_status'] ?? null)->toBe(410);
});

it("edge: catalog shows deprecation metadata in phase after_sunset for alias [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2020-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['deprecated'])->toBeTrue()
        ->and($d['sunset_at'])->toBe('2020-01-01')
        ->and($d['successor'])->toBe('create-invoice-v2');
});
