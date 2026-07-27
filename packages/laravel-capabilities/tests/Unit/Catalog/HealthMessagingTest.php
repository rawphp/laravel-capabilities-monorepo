<?php

// REQ-010 fleshed unit tests for Catalog/HealthMessagingTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;

it("edge: health includes messaging readiness when messaging surface on [D-021]", function () {
    $h = CatalogHelpers::harness(['surfaces' => ['messaging' => true]]);
    $report = $h['catalog']->health();
    expect($report)->toHaveKey('messaging')
        ->and($report['messaging'])->toHaveKeys(['ready', 'configured']);
});

it("edge: health omits messaging details when messaging surface off [D-021]", function () {
    $h = CatalogHelpers::harness(['surfaces' => ['messaging' => false]]);
    $report = $h['catalog']->health();
    expect($report)->not->toHaveKey('messaging');
});

it("happy: health never requires telegram secrets at boot-only probe [D-021]", function () {
    $h = CatalogHelpers::harness(['surfaces' => ['messaging' => true]]);
    $report = $h['catalog']->health();
    $json = json_encode($report);
    expect($json)->not->toContain('BOT_TOKEN')
        ->and($json)->not->toContain('api_key')
        ->and($json)->not->toContain('secret');
});
