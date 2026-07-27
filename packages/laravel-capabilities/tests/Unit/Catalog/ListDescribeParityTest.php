<?php

// REQ-010 fleshed unit tests for Catalog/ListDescribeParityTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("happy: list and describe agree on name [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'aliases' => ['invoice.create'],
        'schema_version' => '3',
    ]);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['name'] ?? null)->toBe($desc['name'] ?? null);
});

it("happy: list and describe agree on description [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'aliases' => ['invoice.create'],
        'schema_version' => '3',
    ]);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['description'] ?? null)->toBe($desc['description'] ?? null);
});

it("happy: list and describe agree on schema_version [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'aliases' => ['invoice.create'],
        'schema_version' => '3',
    ]);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['schema_version'] ?? null)->toBe($desc['schema_version'] ?? null);
});

it("happy: list and describe agree on deprecated [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'aliases' => ['invoice.create'],
        'schema_version' => '3',
    ]);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['deprecated'] ?? null)->toBe($desc['deprecated'] ?? null);
});

it("happy: list and describe agree on aliases [CAT-001]", function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'aliases' => ['invoice.create'],
        'schema_version' => '3',
    ]);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['aliases'] ?? null)->toBe($desc['aliases'] ?? null);
});

it("edge: describe has full schemas list may omit [CAT-001]", function () {
    $h = CatalogHelpers::harness();
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list)->not->toHaveKey('input_schema')
        ->and($desc)->toHaveKey('input_schema')
        ->and($desc)->toHaveKey('output_schema');
});
