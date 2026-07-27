<?php

// REQ-020: Laravel glue boot path closure. Unit-only.
// REQ-048: makeRegistry accepts shared store instances (no dual-create).

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandRegistrar;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Discovery\CapabilityDiscoveryBoot;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('path: enabled surfaces expose routes, commands, config-driven bindings, and discovery entry points', function () {
    $config = BootHelpers::config([
        'surfaces' => BootHelpers::surfaces([
            'http' => true,
            'artisan' => true,
        ]),
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory', 'mode' => 'best_effort'],
        'path' => dirname(__DIR__, 2).'/Fixtures/Capabilities',
    ]);

    $plan = CapabilitiesServiceProvider::registrationPlan($config);
    expect($plan['routes'])->toContain(RouteTable::ROUTE_INVOKE)
        ->and($plan['commands'])->not->toBeEmpty();

    expect(HttpRouteRegistrar::registeredKeys($config['surfaces']['http']))->toContain(RouteTable::ROUTE_INVOKE)
        ->and(ArtisanCommandRegistrar::classes($config['surfaces']['artisan']))->not->toBeEmpty()
        ->and(ContainerBindings::resolve($config)['drivers']['approval_store']['resolved'])->toBe('memory');

    $registry = ContainerBindings::makeRegistry($config);
    $names = CapabilityDiscoveryBoot::run($registry, $config);
    expect($names)->not->toBeEmpty()
        ->and($registry->has('create-invoice'))->toBeTrue();
});

it('path: disabled http and artisan register empty artifacts', function () {
    $config = BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['http' => false, 'artisan' => false]),
    ]);
    $plan = CapabilitiesServiceProvider::registrationPlan($config);
    expect($plan['routes'])->toBeEmpty()
        ->and($plan['commands'])->toBeEmpty()
        ->and(HttpRouteRegistrar::registeredKeys(['enabled' => false]))->toBeEmpty()
        ->and(ArtisanCommandRegistrar::classes(['enabled' => false]))->toBeEmpty();
});

it('path: provider exposes boot glue methods for routes discovery and artisan', function () {
    expect(method_exists(CapabilitiesServiceProvider::class, 'bootHttpRoutes'))->toBeTrue()
        ->and(method_exists(CapabilitiesServiceProvider::class, 'bootCapabilityDiscovery'))->toBeTrue()
        ->and(method_exists(CapabilitiesServiceProvider::class, 'bootArtisanCommands'))->toBeTrue();
});

it('path REQ-048: makeRegistry reuses prebuilt approval/idempotency stores (memory + database)', function () {
    $memory = BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
    ]);
    $memApproval = ContainerBindings::makeApprovalManager($memory);
    $memIdem = ContainerBindings::makeIdempotencyStore($memory);
    $memReg = ContainerBindings::makeRegistry(
        $memory,
        approvalStore: $memApproval->store(),
        idempotencyStore: $memIdem,
    );
    expect($memReg->approvalStore())->toBe($memApproval->store())
        ->and($memReg->idempotencyStore())->toBe($memIdem);

    $gateway = new ArrayTableGateway;
    $database = BootHelpers::config([
        'approval' => ['store' => 'database'],
        'idempotency' => ['driver' => 'database'],
    ]);
    $dbApproval = ContainerBindings::makeApprovalManager($database, $gateway);
    $dbIdem = ContainerBindings::makeIdempotencyStore($database, $gateway);
    $dbReg = ContainerBindings::makeRegistry(
        $database,
        $gateway,
        $dbApproval->store(),
        $dbIdem,
    );
    expect($dbReg->approvalStore())->toBe($dbApproval->store())
        ->and($dbReg->idempotencyStore())->toBe($dbIdem)
        ->and($dbReg->approvalStore())->toBeInstanceOf(DatabaseApprovalStore::class);

    $tableProp = (new ReflectionClass(DatabaseApprovalStore::class))->getProperty('table');
    expect($tableProp->getValue($dbReg->approvalStore()))->toBe($gateway)
        ->and($tableProp->getValue($dbApproval->store()))->toBe($gateway);
});
