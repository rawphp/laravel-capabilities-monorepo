<?php

// REQ-014: Publish tags (BOOT-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;

it('edge: publish tag capabilities-config available [BOOT-001]', function () {
    expect(ContainerBindings::hasPublishTag('capabilities-config'))->toBeTrue()
        ->and(CapabilitiesServiceProvider::publishTags())->toContain('capabilities-config');
});

it('edge: publish tag capabilities-migrations available [BOOT-001]', function () {
    expect(ContainerBindings::hasPublishTag('capabilities-migrations'))->toBeTrue()
        ->and(CapabilitiesServiceProvider::publishTags())->toContain('capabilities-migrations');
});
