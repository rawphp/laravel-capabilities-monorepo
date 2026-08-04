<?php

// REQ-010 fleshed unit tests for Catalog/ListFieldMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it('happy: list entry may include name [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('name');
});

it('edge: describe entry includes name when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('name');
});

it('happy: list entry may include description [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('description');
});

it('edge: describe entry includes description when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('description');
});

it('happy: list entry may include surfaces [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('surfaces');
});

it('edge: describe entry includes surfaces when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('surfaces');
});

it('happy: list entry may include readOnly [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('readOnly');
});

it('edge: describe entry includes readOnly when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('readOnly');
});

it('happy: list entry may include schema_version [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('schema_version');
});

it('edge: describe entry includes schema_version when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('schema_version');
});

it('happy: list entry may include idempotent [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('idempotent');
});

it('edge: describe entry includes idempotent when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('idempotent');
});

it('happy: list entry may include deprecated [CAT-001]', function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('deprecated');
});

it('edge: describe entry includes deprecated when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('deprecated');
});

it('happy: list entry may include deprecated_at [CAT-001]', function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('deprecated_at');
});

it('edge: describe entry includes deprecated_at when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('deprecated_at');
});

it('happy: list entry may include aliases [CAT-001]', function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('aliases');
});

it('edge: describe entry includes aliases when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('aliases');
});

it('happy: list entry may include successor [CAT-001]', function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('successor');
});

it('edge: describe entry includes successor when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('successor');
});

it('happy: list entry may include sunset_at [CAT-001]', function () {
    $h = CatalogHelpers::harness(['deprecated' => true, 'deprecated_at' => '2026-01-01', 'aliases' => ['invoice.create'], 'successor' => 'create-invoice-v2', 'sunset_at' => '2027-01-01']);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('sunset_at');
});

it('edge: describe entry includes sunset_at when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('sunset_at');
});

it('happy: list entry may include groups [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('groups');
});

it('edge: describe entry includes groups when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('groups');
});

it('happy: list entry may include tags [CAT-001]', function () {
    $h = CatalogHelpers::harness([]);
    $list = $h['catalog']->list();
    expect($list)->not->toBeEmpty()
        ->and($list[0])->toHaveKey('tags');
});

it('edge: describe entry includes tags when set [CAT-001]', function () {
    $h = CatalogHelpers::harness([
        'deprecated' => true,
        'deprecated_at' => '2026-01-01',
        'aliases' => ['invoice.create'],
        'successor' => 'create-invoice-v2',
        'sunset_at' => '2027-01-01',
    ]);
    $d = $h['catalog']->describe($h['name']);
    expect($d)->toHaveKey('tags');
});
