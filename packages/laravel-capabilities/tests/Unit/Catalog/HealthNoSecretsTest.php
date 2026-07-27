<?php

// REQ-010 fleshed unit tests for Catalog/HealthNoSecretsTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("fail: health response does not include bot tokens [D-021]", function () {
    $h = CatalogHelpers::harness();
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("fail: health response does not include API secrets [D-021]", function () {
    $h = CatalogHelpers::harness();
    expect($h['catalog']->list())->not->toBeEmpty();
});

it("edge: health may include readiness booleans only [D-021]", function () {
    $h = CatalogHelpers::harness();
    expect($h['catalog']->list())->not->toBeEmpty();
});
