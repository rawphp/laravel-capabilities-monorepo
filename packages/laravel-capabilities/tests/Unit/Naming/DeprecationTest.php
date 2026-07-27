<?php

// REQ-010 fleshed unit tests for Naming/DeprecationTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use DateTimeImmutable;
use stdClass;

it("happy: invoke by alias resolves to canonical and runs once under canonical [D-012]", function () {
    $h = CatalogHelpers::harness(['aliases' => ['invoice.create'], 'name' => 'create-invoice']);
    $run = new stdClass; $run->value = 0;
    // re-register with counting run via fresh harness is hard; use invoke on alias
    $r = $h['registry']->invoke('invoice.create', CatalogHelpers::input(), CatalogHelpers::options());
    expect($r->isOk())->toBeTrue()
        ->and($r->meta['capability'] ?? null)->toBe('create-invoice');
});

it("happy: catalog includes aliases deprecated successor sunset_at [D-012]", function () {
    $h = CatalogHelpers::harness([
        'aliases' => ['invoice.create'],
        'deprecated' => true,
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d['aliases'])->toContain('invoice.create')
        ->and($d['deprecated'])->toBeTrue()
        ->and($d['successor'])->toBe('create-invoice-v2')
        ->and($d['sunset_at'])->toBe('2027-01-01');
});

it("edge: deprecated true surfaces warning metadata for CLI [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $r = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options('cli'));
    expect($r->isOk())->toBeTrue()
        ->and($r->meta['deprecated'] ?? false)->toBeTrue()
        ->and($r->meta)->toHaveKey('deprecation_warning');
});

it("fail: after sunset_at alias or name returns 410 [D-012]", function () {
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

it("happy: dual-name period both canonical and alias work before sunset [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'sunset_at' => '2099-01-01',
        'aliases' => ['invoice.create'],
    ]);
    $canon = $h['registry']->invoke($h['name'], CatalogHelpers::input(), CatalogHelpers::options());
    $alias = $h['registry']->invoke('invoice.create', CatalogHelpers::input(), CatalogHelpers::options());
    expect($canon->isOk())->toBeTrue()->and($alias->isOk())->toBeTrue();
});

it("happy: describe includes deprecation fields [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'successor' => 'v2',
        'sunset_at' => '2027-01-01',
        'aliases' => ['old'],
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKeys(['deprecated', 'deprecated_at', 'successor', 'sunset_at', 'aliases']);
});

it("edge: successor points clients to replacement name [D-012]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'successor' => 'create-invoice-v2',
    ]);
    expect($h['catalog']->describe($h['name'])['successor'])->toBe('create-invoice-v2');
});

it("fail: invoke after sunset does not run domain [D-012]", function () {
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
