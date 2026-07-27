<?php

// REQ-011 fleshed unit tests for Http/MiddlewareMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$stacks = [
    'api' => ['api'],
    'auth:sanctum' => ['api', 'auth:sanctum'],
    'throttle' => ['api', 'throttle:60,1'],
    'custom' => ['api', 'capabilities.custom'],
];

foreach ($stacks as $label => $middleware) {
    it("edge: middleware {$label} can be applied via config [HTTP-001]", function () use ($middleware) {
        $routes = HttpHelpers::routes([
            'enabled' => true,
            'prefix' => 'capabilities',
            'middleware' => $middleware,
        ]);
        expect($routes)->not->toBeEmpty();
        foreach ($routes as $route) {
            expect($route['middleware'])->toBe($middleware);
        }
    });
}

it("fail: unauthenticated request blocked when auth middleware on [HTTP-001]", function () {
    $routes = HttpHelpers::routes([
        'enabled' => true,
        'middleware' => ['api', 'auth:sanctum'],
    ]);
    expect(RouteTable::find($routes, RouteTable::ROUTE_INVOKE)['middleware'])->toContain('auth:sanctum');

    $h = HttpHelpers::harness();
    $res = $h['controller']->invoke(HttpHelpers::guestRequest([
        'method' => 'POST',
        'jsonBody' => ['customer_id' => 1],
    ]), $h['name']);
    expect($res->errorCode())->toBe('unauthenticated')->and($res->status)->toBe(401);
});
