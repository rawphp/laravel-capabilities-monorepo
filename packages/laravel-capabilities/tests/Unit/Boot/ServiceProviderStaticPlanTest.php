<?php

declare(strict_types=1);

// CapabilitiesServiceProvider static plan helpers.

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\CapabilitiesServiceProvider;

it('CapabilitiesServiceProvider static helpers return boot guards, registration plan, bindings, tags, jobs, commands, and surfaces', function () {
    $config = CapabilitiesConfig::defaults();
    // Disable peer-backed surfaces so boot guards do not require laravel/ai|mcp.
    $config['surfaces']['agent']['enabled'] = false;
    $config['surfaces']['mcp']['enabled'] = false;
    $config['surfaces']['messaging']['enabled'] = false;

    $guards = CapabilitiesServiceProvider::runBootGuards($config, skipBootChecks: true);
    expect($guards)->toBeArray();

    $guards2 = CapabilitiesServiceProvider::runBootGuards($config, messagingPackageInstalled: false, appEnv: 'testing', skipBootChecks: false);
    expect($guards2)->toBeArray();

    expect(CapabilitiesServiceProvider::registrationPlan($config))->toBeArray()
        ->and(CapabilitiesServiceProvider::bindingAbstracts())->not->toBeEmpty()
        ->and(CapabilitiesServiceProvider::publishTags())->not->toBeEmpty()
        ->and(CapabilitiesServiceProvider::jobHelpers(['enabled' => true]))->toBeArray()
        ->and(CapabilitiesServiceProvider::artisanCommands(['enabled' => true]))->toBeArray()
        ->and(CapabilitiesServiceProvider::knownSurfaces())->not->toBeEmpty();
});
