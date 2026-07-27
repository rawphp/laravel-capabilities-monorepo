<?php

// REQ-021: HTTP route registration from RouteTable. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;

it('registers every RouteTable action key when http enabled', function () {
    $http = ['enabled' => true, 'prefix' => 'capabilities', 'middleware' => ['api']];
    $sink = [];
    $keys = HttpRouteRegistrar::registerInto($http, function (array $route) use (&$sink): void {
        $sink[] = $route;
    });

    $sinkKeys = array_column($sink, 'key');
    foreach (RouteTable::actionKeys() as $key) {
        expect($keys)->toContain($key)
            ->and($sinkKeys)->toContain($key);
    }
    expect($keys)->toHaveCount(count(RouteTable::actionKeys()));
});

it('registers zero routes when http disabled', function () {
    $keys = HttpRouteRegistrar::registerInto(['enabled' => false], function (): void {
        throw new RuntimeException('sink must not be called');
    });

    expect($keys)->toBeEmpty()
        ->and(HttpRouteRegistrar::definitions(['enabled' => false]))->toBeEmpty()
        ->and(HttpRouteRegistrar::registeredKeys(['enabled' => false]))->toBeEmpty();
});

it('uses RouteTable as sole path/action source of truth', function () {
    $http = ['enabled' => true, 'prefix' => 'cap-api', 'middleware' => ['api', 'auth:sanctum']];
    $table = RouteTable::routes($http);
    $defs = HttpRouteRegistrar::definitions($http);

    expect(count($defs))->toBe(count($table));
    foreach ($table as $i => $row) {
        expect($defs[$i]['key'])->toBe($row['key'])
            ->and($defs[$i]['method'])->toBe(strtoupper($row['method']))
            ->and($defs[$i]['uri'])->toBe($row['uri'])
            ->and($defs[$i]['name'])->toBe($row['name'])
            ->and($defs[$i]['middleware'])->toBe($row['middleware'])
            ->and($defs[$i]['uses'][1])->toBe(explode('@', $row['action'])[1]);
    }
});

it('resolves controller classes for each action', function () {
    $defs = HttpRouteRegistrar::definitions(['enabled' => true]);
    foreach ($defs as $def) {
        expect(class_exists($def['uses'][0]))->toBeTrue()
            ->and($def['uses'][1])->not->toBe('');
    }
});

it('provider registration plan still lists http keys when enabled', function () {
    $plan = \Rawphp\Capabilities\CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
        'surfaces' => BootHelpers::surfaces(['http' => true]),
    ]));
    expect($plan['routes'])->toContain(RouteTable::ROUTE_INVOKE)
        ->and($plan['routes'])->toContain(RouteTable::ROUTE_LIST);
});

it('does not include messaging routes in capability HTTP tree', function () {
    $keys = HttpRouteRegistrar::registeredKeys(['enabled' => true]);
    foreach ($keys as $key) {
        expect(str_contains(strtolower($key), 'telegram'))->toBeFalse()
            ->and(str_contains(strtolower($key), 'messaging'))->toBeFalse();
    }
});
