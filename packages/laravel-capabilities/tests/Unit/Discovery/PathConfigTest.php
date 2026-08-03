<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Discovery\DiscoveryPaths;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\Outside\OutsideCapability;

it('happy: default path is app_path Capabilities [D-017]', function () {
    $path = DiscoveryPaths::default();
    expect($path)->toContain('Capabilities');
    // When app_path helper is absent, falls back to app/Capabilities string.
    expect($path === 'app/Capabilities' || str_ends_with($path, 'Capabilities'))->toBeTrue();
});

it('edge: custom path config is scanned [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $path = dirname(__DIR__, 2).'/Fixtures/Capabilities';
    $registry->discover(paths: [$path]);

    expect($registry->has('create-invoice'))->toBeTrue()
        ->and($registry->has('list-customers'))->toBeTrue();
});

it('fail: classes outside path not auto-discovered [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    $path = dirname(__DIR__, 2).'/Fixtures/Capabilities';
    $registry->discover(paths: [$path]);

    expect($registry->has('outside-cap'))->toBeFalse()
        ->and(class_exists(OutsideCapability::class))->toBeTrue();

    // Explicit class map can still register outside classes when asked.
    $explicit = (new AttributeDiscoverer)->fromClass(OutsideCapability::class);
    expect($explicit)->not->toBeNull()
        ->and($explicit->name)->toBe('outside-cap');
});

it('happy: fluent define works regardless of path [D-017]', function () {
    $registry = DiscoveryHelpers::registry();
    // No path discovery — fluent alone.
    Capability::define('path-independent')
        ->input(CreateInvoiceInput::class)
        ->surfaces(['http'])
        ->run(fn ($in) => ['ok' => true])
        ->register($registry);

    expect($registry->has('path-independent'))->toBeTrue();
});
