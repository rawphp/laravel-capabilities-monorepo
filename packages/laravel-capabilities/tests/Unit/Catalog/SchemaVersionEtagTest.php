<?php

// REQ-010 fleshed unit tests for Catalog/SchemaVersionEtagTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it('happy: catalog entries include schema_version [D-004]', function () {
    $h = CatalogHelpers::harness(['schema_version' => '2']);
    expect($h['catalog']->list()[0]['schema_version'])->toBe('2');
});

it('edge: catalog may include etag for cache [D-004]', function () {
    $h = CatalogHelpers::harness();
    $env = $h['catalog']->listEnvelope();
    expect($env)->toHaveKey('etag')
        ->and($env['etag'])->toBeString()
        ->and($env['etag'])->not->toBe('');
});

it('happy: describe includes schema_version matching list [D-004]', function () {
    $h = CatalogHelpers::harness(['schema_version' => '9']);
    $list = $h['catalog']->list()[0];
    $desc = $h['catalog']->describe($h['name']);
    expect($list['schema_version'])->toBe($desc['schema_version'])->toBe('9');
});

it('edge: cli cache invalidates on schema_version change [D-004]', function () {
    $h = CatalogHelpers::harness(['schema_version' => '1']);
    $e1 = $h['catalog']->etag();
    // re-register with different version via new harness
    $h2 = CatalogHelpers::harness(['schema_version' => '2', 'name' => 'create-invoice']);
    $e2 = $h2['catalog']->etag();
    expect($e1)->not->toBe($e2);
});
