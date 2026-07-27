<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CustomSchemaType;

it('happy: docs and generators use package-native examples [D-015]', function () {
    expect(CreateInvoiceInput::class)->toExtend(CapabilityData::class)
        ->and(CreateInvoiceInput::jsonSchema()['$schema'] ?? null)
        ->toBe('https://json-schema.org/draft/2020-12/schema');
});

it('edge: Spatie is optional bridge not primary [D-015]', function () {
    $composerPath = dirname(__DIR__, 3).'/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);
    expect($composer['require'] ?? [])->not->toHaveKey('spatie/laravel-data')
        ->and(is_subclass_of(CreateInvoiceInput::class, CapabilityData::class))->toBeTrue();
});

it('happy: SchemaProvider is escape hatch [D-015]', function () {
    expect(CustomSchemaType::class)->toImplement(SchemaProvider::class)
        ->and(CustomSchemaType::validate(['label' => 'escape']))->toBeInstanceOf(CustomSchemaType::class);
});

it('fail: Spatie is not required dependency for v1 [D-015]', function () {
    $composerPath = dirname(__DIR__, 3).'/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);
    $all = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);
    expect($all)->not->toHaveKey('spatie/laravel-data');
});
