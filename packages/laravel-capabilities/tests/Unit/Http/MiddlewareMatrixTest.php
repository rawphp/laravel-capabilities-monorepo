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
            // Auth issuance strips auth:* (L-002) and adds the default throttle (L-018).
            if (RouteTable::isAuthIssuanceRoute($route['key'])) {
                expect($route['middleware'])->toBe([
                    ...RouteTable::withoutAuthMiddleware($middleware),
                    RouteTable::DEFAULT_AUTH_THROTTLE,
                ]);
            } else {
                expect($route['middleware'])->toBe($middleware);
            }
        }
    });
}

it('fail: unauthenticated request blocked when auth middleware on [HTTP-001]', function () {
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

// L-018: credential-issuing routes are unauthenticated, so they must be throttled by default.
it('throttles auth issuance routes by default while list/invoke keep auth:sanctum [L-018]', function () {
    $routes = HttpHelpers::routes();

    foreach ([RouteTable::ROUTE_AUTH_TOKEN, RouteTable::ROUTE_AUTH_DEVICE, RouteTable::ROUTE_AUTH_OAUTH_CALLBACK] as $key) {
        expect(RouteTable::find($routes, $key)['middleware'])->toBe(['api', 'throttle:6,1,capabilities-auth']);
    }
    foreach ([RouteTable::ROUTE_LIST, RouteTable::ROUTE_INVOKE] as $key) {
        expect(RouteTable::find($routes, $key)['middleware'])->toBe(['api', 'auth:sanctum']);
    }
});

it('surfaces.http.auth_middleware replaces the auth issuance stack verbatim [L-018]', function () {
    $routes = HttpHelpers::routes([
        'enabled' => true,
        'middleware' => ['api', 'auth:sanctum'],
        'auth_middleware' => ['api', 'throttle:12,1'],
    ]);

    expect(RouteTable::find($routes, RouteTable::ROUTE_AUTH_DEVICE)['middleware'])->toBe(['api', 'throttle:12,1'])
        ->and(RouteTable::find($routes, RouteTable::ROUTE_INVOKE)['middleware'])->toBe(['api', 'auth:sanctum']);
});
