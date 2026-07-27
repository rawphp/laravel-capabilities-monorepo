<?php

// REQ-010 fleshed unit tests for Catalog/CatalogTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("happy: list includes field name when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('name');
});

it("happy: list includes field description when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('description');
});

it("happy: list includes field surfaces when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('surfaces');
});

it("happy: list includes field readOnly when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('readOnly');
});

it("happy: list includes field schema_version when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('schema_version');
});

it("happy: list includes field idempotent when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('idempotent');
});

it("happy: list includes field deprecated when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('deprecated');
});

it("happy: list includes field aliases when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('aliases');
});

it("happy: list includes field successor when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('successor');
});

it("happy: list includes field sunset_at when applicable [CAT-001]", function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('sunset_at');
});

it("happy: describe returns full input_schema output_schema [D-004]", function () {
    $h = CatalogHelpers::harness();
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKeys(['input_schema', 'output_schema'])
        ->and($d['input_schema'])->toBeArray()
        ->and($d['output_schema'])->toBeArray();
});

it("happy: catalog wire is JSON Schema only never Laravel rule strings [D-004]", function () {
    $h = CatalogHelpers::harness();
    $d = $h['catalog']->describe($h['name']);
    $json = json_encode($d);
    expect($json)->not->toContain('required|')
        ->and($json)->not->toContain('exists:')
        ->and($d['input_schema'])->toBeArray();
});

it("edge: catalog only lists capabilities with at least one effective invoke surface for caller [SURF-001]", function () {
    $h = CatalogHelpers::harness([
        'surfaces' => ['agent' => false, 'mcp' => false, 'http' => false, 'cli' => false, 'job' => false, 'artisan' => false, 'messaging' => false],
        'cap_surfaces' => ['http'],
    ]);
    expect($h['catalog']->list())->toBeEmpty();
});

it("edge: catalog visibility respects caller agent effective surfaces [CAT-001]", function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['agent']]);
    $visible = $h['catalog']->list(false, ['caller' => 'agent']);
    expect($visible)->not->toBeEmpty();
    $hidden = $h['catalog']->list(false, ['caller' => 'artisan']);
    // artisan not in cap surfaces
    expect(count($hidden))->toBe(0);
});

it("edge: catalog visibility respects caller mcp effective surfaces [CAT-001]", function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['mcp']]);
    $visible = $h['catalog']->list(false, ['caller' => 'mcp']);
    expect($visible)->not->toBeEmpty();
    $hidden = $h['catalog']->list(false, ['caller' => 'artisan']);
    // artisan not in cap surfaces
    expect(count($hidden))->toBe(0);
});

it("edge: catalog visibility respects caller http effective surfaces [CAT-001]", function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['http']]);
    $visible = $h['catalog']->list(false, ['caller' => 'http']);
    expect($visible)->not->toBeEmpty();
    $hidden = $h['catalog']->list(false, ['caller' => 'artisan']);
    // artisan not in cap surfaces
    expect(count($hidden))->toBe(0);
});

it("edge: catalog visibility respects caller cli effective surfaces [CAT-001]", function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['cli']]);
    $visible = $h['catalog']->list(false, ['caller' => 'cli']);
    expect($visible)->not->toBeEmpty();
    $hidden = $h['catalog']->list(false, ['caller' => 'artisan']);
    // artisan not in cap surfaces
    expect(count($hidden))->toBe(0);
});

it("edge: catalog visibility respects caller job effective surfaces [CAT-001]", function () {
    $h = CatalogHelpers::harness(['cap_surfaces' => ['job']]);
    $visible = $h['catalog']->list(false, ['caller' => 'job']);
    expect($visible)->not->toBeEmpty();
    $hidden = $h['catalog']->list(false, ['caller' => 'artisan']);
    // artisan not in cap surfaces
    expect(count($hidden))->toBe(0);
});

it("happy: GET health reports surface status up disabled_incompatible disabled_config [D-011]", function () {
    $h = CatalogHelpers::harness([
        'health_overrides' => [
            'agent' => 'up',
            'mcp' => 'disabled_incompatible',
            'http' => 'up',
        ],
    ]);
    $report = $h['catalog']->health();
    expect($report['surfaces']['agent']['status'])->toBe('up')
        ->and($report['surfaces']['mcp']['status'])->toBe('disabled_incompatible')
        ->and($report['surfaces']['http']['status'])->toBe('up');
});

it("fail: catalog does not dump full tool schemas for every list entry by default [CAT-001]", function () {
    $h = CatalogHelpers::harness();
    $list = $h['catalog']->list(false);
    expect($list[0])->not->toHaveKey('input_schema')
        ->and($list[0])->not->toHaveKey('output_schema');
});

it("happy: describe by alias resolves canonical capability [D-012]", function () {
    $h = CatalogHelpers::harness(['aliases' => ['invoice.create']]);
    $d = $h['catalog']->describe('invoice.create');
    expect($d['name'])->toBe($h['name']);
});
