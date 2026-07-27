<?php

// REQ-010 fleshed unit tests for Catalog/DisabledSurfaceCatalogTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("edge: catalog excludes caps only on disabled surface agent [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['agent' => false],
        'cap_surfaces' => ['agent'],
        'name' => 'only-agent',
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("happy: catalog still lists caps with other surfaces when agent disabled [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['agent' => false],
        'cap_surfaces' => ['agent', 'http'],
        'name' => 'multi-agent',
    ]);
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("edge: catalog excludes caps only on disabled surface mcp [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['mcp' => false],
        'cap_surfaces' => ['mcp'],
        'name' => 'only-mcp',
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("happy: catalog still lists caps with other surfaces when mcp disabled [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['mcp' => false],
        'cap_surfaces' => ['mcp', 'http'],
        'name' => 'multi-mcp',
    ]);
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("edge: catalog excludes caps only on disabled surface http [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['http' => false],
        'cap_surfaces' => ['http'],
        'name' => 'only-http',
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("happy: catalog still lists caps with other surfaces when http disabled [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['http' => false],
        'cap_surfaces' => ['http', 'cli'],
        'name' => 'multi-http',
    ]);
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("edge: catalog excludes caps only on disabled surface cli [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['cli' => false],
        'cap_surfaces' => ['cli'],
        'name' => 'only-cli',
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("happy: catalog still lists caps with other surfaces when cli disabled [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['cli' => false],
        'cap_surfaces' => ['cli', 'http'],
        'name' => 'multi-cli',
    ]);
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("edge: catalog excludes caps only on disabled surface job [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['job' => false],
        'cap_surfaces' => ['job'],
        'name' => 'only-job',
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("happy: catalog still lists caps with other surfaces when job disabled [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['job' => false],
        'cap_surfaces' => ['job', 'http'],
        'name' => 'multi-job',
    ]);
    expect($h['catalog']->list())->not->toBeEmpty();
});
