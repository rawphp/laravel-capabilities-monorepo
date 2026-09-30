<?php

// REQ-021: HTTP route registration from RouteTable. Unit-only.

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

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
    $plan = CapabilitiesServiceProvider::registrationPlan(BootHelpers::config([
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

function httpRoutesRealRouter(): Router
{
    return new Router(new Dispatcher, new Container);
}

it('registers every route on a real Illuminate router with its name, controller action, and middleware once', function () {
    $http = ['enabled' => true, 'prefix' => 'capabilities', 'middleware' => ['api', 'auth:sanctum']];
    $router = httpRoutesRealRouter();

    $keys = HttpRouteRegistrar::registerInto($http, $router);

    expect($keys)->toBe(HttpRouteRegistrar::registeredKeys($http));
    foreach (HttpRouteRegistrar::definitions($http) as $def) {
        $route = $router->getRoutes()->getByName($def['name']);
        expect($route)->not->toBeNull()
            ->and($route->uri())->toBe(ltrim($def['uri'], '/'))
            ->and($route->methods())->toContain($def['method'])
            ->and($route->getActionName())->toBe($def['uses'][0].'@'.$def['uses'][1])
            ->and($route->middleware())->toBe($def['middleware']);
    }
});

it('rejects a sink that is neither callable nor a router', function () {
    HttpRouteRegistrar::registerInto(['enabled' => true], new stdClass);
})->throws(InvalidArgumentException::class, 'Route sink must be callable or expose addRoute().');

it('rejects route actions that are not Controller@method or name an unknown controller', function () {
    expect(fn () => HttpRouteRegistrar::parseAction('CapabilityController'))
        ->toThrow(InvalidArgumentException::class, 'expected Controller@method')
        ->and(fn () => HttpRouteRegistrar::parseAction('BillingController@list'))
        ->toThrow(InvalidArgumentException::class, 'Unknown capability HTTP controller [BillingController]');
});

it('provider boot maps RouteTable onto the bound router when http is enabled', function () {
    $router = httpRoutesRealRouter();
    $app = FakeProviderApp::registered(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'audit' => ['driver' => 'memory'],
    ]), ['router' => $router]);

    $keys = $app->provider->bootHttpRoutes(['enabled' => true, 'prefix' => 'capabilities', 'middleware' => ['api']]);

    expect($keys)->toContain(RouteTable::ROUTE_INVOKE)
        ->and($router->getRoutes()->count())->toBe(count($keys))
        ->and($router->getRoutes()->getByName('capabilities.invoke')?->middleware())->toBe(['api'])
        ->and($app->provider->bootHttpRoutes(['enabled' => false]))->toBe([]);
});

it('provider boot registers nothing but still reports keys when no usable router is bound', function () {
    $config = BootHelpers::config(['approval' => ['store' => 'memory'], 'idempotency' => ['driver' => 'memory'], 'audit' => ['driver' => 'memory']]);
    $http = ['enabled' => true, 'prefix' => 'capabilities'];

    $noRouter = FakeProviderApp::registered($config)->provider->bootHttpRoutes($http);
    $notARouter = FakeProviderApp::registered($config, ['router' => new stdClass])->provider->bootHttpRoutes($http);

    expect($noRouter)->toBe(HttpRouteRegistrar::registeredKeys($http))
        ->and($notARouter)->toBe(HttpRouteRegistrar::registeredKeys($http));
});
