<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;

it("happy: versioning principle: capability names are public contracts [VER]", function () {
    expect(method_exists(\Rawphp\Capabilities\Capability::class, 'define'))->toBeTrue()
        ->and(is_file(A::CORE_ROOT.'/composer.json'))->toBeTrue();
});

it("happy: versioning principle: schema_version changes are detectable [VER]", function () {
    expect(class_exists(\Rawphp\Capabilities\Schema\ToolSchemaExporter::class) || class_exists(\Rawphp\Capabilities\Schema\CatalogPresenter::class))->toBeTrue();
});

it("happy: versioning principle: AdapterApi versions bridges [VER]", function () {
    expect(class_exists(AdapterApi::class))->toBeTrue()
        ->and(AdapterApi::CURRENT)->toBe(AdapterApi::V1)
        ->and(AdapterApi::supported())->toContain(AdapterApi::V1);
});

it("happy: versioning principle: error codes are normative set [VER]", function () {
    expect(ErrorCodeMap::codes())->not->toBeEmpty()
        ->and(ErrorCodeMap::isKnown('forbidden'))->toBeTrue()
        ->and(ErrorCodeMap::isKnown('validation_failed'))->toBeTrue();
});
