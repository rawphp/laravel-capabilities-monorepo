<?php

// REQ-011 fleshed unit tests for Http/RouteRegistrationMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$matrix = [
    ['True', 'GET', 'list', RouteTable::ROUTE_LIST, true],
    ['True', 'GET', 'describe', RouteTable::ROUTE_DESCRIBE, true],
    ['True', 'POST', 'invoke', RouteTable::ROUTE_INVOKE, true],
    ['True', 'POST', 'accept', RouteTable::ROUTE_APPROVAL_ACCEPT, true],
    ['True', 'POST', 'reject', RouteTable::ROUTE_APPROVAL_REJECT, true],
    ['True', 'GET', 'health', RouteTable::ROUTE_HEALTH, true],
    ['True', 'POST', 'auth_token', RouteTable::ROUTE_AUTH_TOKEN, true],
    ['True', 'POST', 'auth_device', RouteTable::ROUTE_AUTH_DEVICE, true],
    ['False', 'GET', 'list', RouteTable::ROUTE_LIST, false],
    ['False', 'GET', 'describe', RouteTable::ROUTE_DESCRIBE, false],
    ['False', 'POST', 'invoke', RouteTable::ROUTE_INVOKE, false],
    ['False', 'POST', 'accept', RouteTable::ROUTE_APPROVAL_ACCEPT, false],
    ['False', 'POST', 'reject', RouteTable::ROUTE_APPROVAL_REJECT, false],
    ['False', 'GET', 'health', RouteTable::ROUTE_HEALTH, false],
    ['False', 'POST', 'auth_token', RouteTable::ROUTE_AUTH_TOKEN, false],
    ['False', 'POST', 'auth_device', RouteTable::ROUTE_AUTH_DEVICE, false],
];

foreach ($matrix as [$enabledLabel, $method, $label, $key, $enabled]) {
    $verb = $enabled ? 'registers' : 'does not register';
    it("{$verb}: http_enabled={$enabledLabel} {$method} {$label} [D-009]", function () use ($key, $enabled, $method) {
        $routes = HttpHelpers::routes(['enabled' => $enabled, 'prefix' => 'capabilities']);
        if ($enabled) {
            expect(RouteTable::has($routes, $key))->toBeTrue();
            $r = RouteTable::find($routes, $key);
            expect($r['method'])->toBe($method);
        } else {
            expect(RouteTable::has($routes, $key))->toBeFalse();
        }
    });
}
