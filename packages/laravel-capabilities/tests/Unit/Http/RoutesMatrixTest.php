<?php

// REQ-011 fleshed unit tests for Http/RoutesMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$enabledCases = [
    'list' => RouteTable::ROUTE_LIST,
    'describe' => RouteTable::ROUTE_DESCRIBE,
    'invoke' => RouteTable::ROUTE_INVOKE,
    'approval_accept' => RouteTable::ROUTE_APPROVAL_ACCEPT,
    'approval_reject' => RouteTable::ROUTE_APPROVAL_REJECT,
    'health' => RouteTable::ROUTE_HEALTH,
    'auth_token' => RouteTable::ROUTE_AUTH_TOKEN,
    'auth_device' => RouteTable::ROUTE_AUTH_DEVICE,
];

foreach ($enabledCases as $label => $key) {
    it("happy: http surface enabled registers {$label} [D-009]", function () use ($key) {
        $routes = HttpHelpers::routes(['enabled' => true, 'prefix' => 'capabilities']);
        expect(RouteTable::has($routes, $key))->toBeTrue();
        $r = RouteTable::find($routes, $key);
        expect($r)->toHaveKeys(['key', 'method', 'uri', 'name', 'action', 'middleware']);
    });
}

foreach ($enabledCases as $label => $key) {
    it("fail: http surface disabled does not register {$label} [D-009]", function () use ($key) {
        $routes = HttpHelpers::routes(['enabled' => false]);
        expect(RouteTable::has($routes, $key))->toBeFalse();
    });
}
