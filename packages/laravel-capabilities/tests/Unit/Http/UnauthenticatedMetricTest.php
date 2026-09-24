<?php

// Unauthenticated HTTP denials leave an ops signal (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InvokeTelemetry;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$routes = [RouteTable::ROUTE_LIST, RouteTable::ROUTE_DESCRIBE, RouteTable::ROUTE_INVOKE, RouteTable::ROUTE_HEALTH];

foreach ($routes as $route) {
    it("fail: unauthenticated {$route} increments http_unauthenticated_total [D-019]", function () use ($route) {
        $metrics = new InMemoryMetrics;
        $controller = new CapabilityController(HttpHelpers::mockBus(), metrics: $metrics);
        $req = HttpHelpers::guestRequest();

        $response = match ($route) {
            RouteTable::ROUTE_LIST => $controller->list($req),
            RouteTable::ROUTE_DESCRIBE => $controller->describe($req, 'billing.create_invoice'),
            RouteTable::ROUTE_INVOKE => $controller->invoke($req->with(['method' => 'POST', 'jsonBody' => []]), 'billing.create_invoice'),
            RouteTable::ROUTE_HEALTH => $controller->health($req),
        };

        expect($response->status)->toBe(401)
            ->and($metrics->emissions())->toBe([[
                'name' => InvokeTelemetry::METRIC_UNAUTHENTICATED,
                'labels' => ['route' => $route, 'auth' => HttpAuthGate::AUTH_NONE],
                'by' => 1,
            ]]);
    });
}

it('fail: authKind claimed but request not authenticated is counted with that auth label [D-019]', function () {
    $metrics = new InMemoryMetrics;
    $controller = new CapabilityController(HttpHelpers::mockBus(), metrics: $metrics);

    $controller->list(HttpHelpers::guestRequest(['authKind' => HttpAuthGate::AUTH_CLI_TOKEN]));

    expect($metrics->get(InvokeTelemetry::METRIC_UNAUTHENTICATED, [
        'route' => RouteTable::ROUTE_LIST,
        'auth' => HttpAuthGate::AUTH_CLI_TOKEN,
    ]))->toBe(1);
});

it('happy: authenticated requests emit no unauthenticated metric [D-019]', function () {
    $metrics = new InMemoryMetrics;
    $controller = new CapabilityController(HttpHelpers::mockBus(), metrics: $metrics);
    $req = HttpHelpers::authedRequest();

    $controller->list($req);
    $controller->health($req);

    expect($metrics->emissions())->toBe([]);
});

it('edge: public health for guests is not an unauthenticated denial [D-019]', function () {
    $metrics = new InMemoryMetrics;
    $controller = new CapabilityController(HttpHelpers::mockBus(), httpConfig: ['health_public' => true], metrics: $metrics);

    $response = $controller->health(HttpHelpers::guestRequest());

    expect($response->status)->toBe(200)
        ->and($metrics->emissions())->toBe([]);
});
