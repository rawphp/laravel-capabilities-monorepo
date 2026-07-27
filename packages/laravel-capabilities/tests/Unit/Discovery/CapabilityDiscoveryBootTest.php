<?php

// REQ-022: Boot capability auto-discovery. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot;
use Rawphp\Capabilities\Discovery\DiscoveryPaths;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\Outside\OutsideCapability;

it('discovers attribute capabilities from configured path into registry', function () {
    $registry = DiscoveryHelpers::registry();
    $path = dirname(__DIR__, 2).'/Fixtures/Capabilities';
    $names = CapabilityDiscoveryBoot::run($registry, ['path' => $path]);

    expect($names)->not->toBeEmpty()
        ->and($registry->has('create-invoice'))->toBeTrue()
        ->and($registry->has('list-customers'))->toBeTrue();
});

it('missing discovery path is a no-op without throw', function () {
    $registry = new CapabilityRegistry;
    $names = CapabilityDiscoveryBoot::run($registry, [
        'path' => sys_get_temp_dir().'/capabilities-missing-'.uniqid('', true),
    ]);

    expect($names)->toBeEmpty()
        ->and($registry->all())->toBe([]);
});

it('does not discover classes outside the configured path', function () {
    $registry = DiscoveryHelpers::registry();
    $path = dirname(__DIR__, 2).'/Fixtures/Capabilities';
    CapabilityDiscoveryBoot::run($registry, ['path' => $path]);

    expect($registry->has('outside-cap'))->toBeFalse()
        ->and(class_exists(OutsideCapability::class))->toBeTrue();
});

it('duplicate name after discovery fails closed', function () {
    $registry = DiscoveryHelpers::registry();
    $path = dirname(__DIR__, 2).'/Fixtures/Capabilities';
    CapabilityDiscoveryBoot::run($registry, ['path' => $path]);

    expect(fn () => CapabilityDiscoveryBoot::run($registry, ['path' => $path]))
        ->toThrow(InvalidArgumentException::class);
});

it('paths() follows config path key defaulting to DiscoveryPaths', function () {
    expect(CapabilityDiscoveryBoot::paths(['path' => '/tmp/caps']))->toBe(['/tmp/caps'])
        ->and(CapabilityDiscoveryBoot::paths([]))->toBe(DiscoveryPaths::fromConfig([]));
});
