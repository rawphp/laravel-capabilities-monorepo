<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\AdapterApi;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

it('happy: AdapterApi V1 equals 1 [D-011]', function () {
    expect(AdapterApi::V1)->toBe(1);
});

it('happy: AdapterApi CURRENT equals V1 [D-011]', function () {
    expect(AdapterApi::CURRENT)->toBe(AdapterApi::V1);
});

it('edge: future V2 would be selected by probe not apps [D-011]', function () {
    // Apps call select() only via package factories — not for tool listing.
    expect(AdapterApi::select(AdapterApi::V1))->toBe(AdapterApi::V1)
        ->and(AdapterApi::select(99))->toBe(AdapterApi::CURRENT)
        ->and(AdapterApi::supported())->toBe([AdapterApi::V1]);
});

it('fail: apps do not depend on adapter version directly for tool listing [D-011]', function () {
    $h = AdapterHelpers::harness();
    // Public listing goes through registry / adapter toolsFor — version is internal.
    $tools = $h['ai']->toolsFor('billing');
    expect($tools)->not->toBeEmpty();
    foreach ($tools as $tool) {
        expect($tool)->toHaveKey('adapter_api')
            ->and($tool['adapter_api'])->toBe(AdapterApi::CURRENT);
    }
    // Adapter reports version without apps hard-coding constants for listing.
    expect($h['ai']->adapterApiVersion())->toBe(AdapterApi::CURRENT);
});
