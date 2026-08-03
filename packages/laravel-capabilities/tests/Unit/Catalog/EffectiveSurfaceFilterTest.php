<?php

// REQ-010 fleshed unit tests for Catalog/EffectiveSurfaceFilterTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it('happy: catalog includes cap with effective surface agent [CAT-001]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['agent']]);
    $names = array_column($h['catalog']->list(), 'name');
    expect($names)->toContain($h['name']);
});

it('edge: catalog excludes cap when only surface agent globally disabled [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['agent' => false],
        'cap_surfaces' => ['agent'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it('happy: catalog includes cap with effective surface mcp [CAT-001]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['mcp']]);
    $names = array_column($h['catalog']->list(), 'name');
    expect($names)->toContain($h['name']);
});

it('edge: catalog excludes cap when only surface mcp globally disabled [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['mcp' => false],
        'cap_surfaces' => ['mcp'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it('happy: catalog includes cap with effective surface http [CAT-001]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['http']]);
    $names = array_column($h['catalog']->list(), 'name');
    expect($names)->toContain($h['name']);
});

it('edge: catalog excludes cap when only surface http globally disabled [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['http' => false],
        'cap_surfaces' => ['http'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it('happy: catalog includes cap with effective surface cli [CAT-001]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['cli']]);
    $names = array_column($h['catalog']->list(), 'name');
    expect($names)->toContain($h['name']);
});

it('edge: catalog excludes cap when only surface cli globally disabled [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['cli' => false],
        'cap_surfaces' => ['cli'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it('happy: catalog includes cap with effective surface job [CAT-001]', function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['job']]);
    $names = array_column($h['catalog']->list(), 'name');
    expect($names)->toContain($h['name']);
});

it('edge: catalog excludes cap when only surface job globally disabled [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['job' => false],
        'cap_surfaces' => ['job'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});
