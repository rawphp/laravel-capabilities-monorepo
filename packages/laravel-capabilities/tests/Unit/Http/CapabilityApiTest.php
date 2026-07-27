<?php

// REQ-011 fleshed unit tests for Http/CapabilityApiTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\ApprovalController;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

it("happy: catalog list describe invoke approval auth live on one CapabilityController tree [D-009]", function () {
    $h = HttpHelpers::harness();
    $routes = HttpHelpers::routes($h['http']);

    expect(RouteTable::has($routes, RouteTable::ROUTE_LIST))->toBeTrue()
        ->and(RouteTable::has($routes, RouteTable::ROUTE_DESCRIBE))->toBeTrue()
        ->and(RouteTable::has($routes, RouteTable::ROUTE_INVOKE))->toBeTrue()
        ->and(RouteTable::has($routes, RouteTable::ROUTE_APPROVAL_ACCEPT))->toBeTrue()
        ->and(RouteTable::has($routes, RouteTable::ROUTE_AUTH_TOKEN))->toBeTrue();

    $listAction = RouteTable::find($routes, RouteTable::ROUTE_LIST)['action'] ?? '';
    $invokeAction = RouteTable::find($routes, RouteTable::ROUTE_INVOKE)['action'] ?? '';
    expect($listAction)->toStartWith('CapabilityController@')
        ->and($invokeAction)->toStartWith('CapabilityController@')
        ->and(class_exists(CapabilityController::class))->toBeTrue()
        ->and(class_exists(ApprovalController::class))->toBeTrue()
        ->and(class_exists(AuthController::class))->toBeTrue();
});

it("happy: product CLI is HTTP client of same invoke endpoint not separate controller [D-009]", function () {
    $routes = HttpHelpers::routes(['enabled' => true, 'prefix' => 'capabilities']);
    $invoke = RouteTable::find($routes, RouteTable::ROUTE_INVOKE);
    expect($invoke)->not->toBeNull()
        ->and($invoke['uri'])->toBe('capabilities/{name}')
        ->and($invoke['method'])->toBe('POST')
        ->and($invoke['action'])->toBe('CapabilityController@invoke');

    // No CLI-prefixed second tree
    foreach ($routes as $route) {
        expect($route['uri'])->not->toStartWith('cli/');
    }
});

it("fail: no CliApiController invoke pipeline class exists [D-009]", function () {
    expect(class_exists('Rawphp\\Capabilities\\Adapters\\Http\\CliApiController'))->toBeFalse()
        ->and(class_exists('Rawphp\\Capabilities\\Http\\CliApiController'))->toBeFalse()
        ->and(class_exists('CliApiController'))->toBeFalse();
});

it("happy: validation authorize scope approval idempotency audit identical for caller http and cli in-process [D-009]", function () {
    $h = HttpHelpers::harness();
    $input = [
        'customer_id' => 42,
        'amount_cents' => 2500,
        'currency' => 'USD',
    ];

    $http = $h['registry']->invoke($h['name'], $input, ['caller' => 'http', 'actor' => $h['user']]);
    $cli = $h['registry']->invoke($h['name'], $input, ['caller' => 'cli', 'actor' => $h['user']]);

    expect($http->isOk())->toBeTrue()
        ->and($cli->isOk())->toBeTrue()
        ->and($http->data)->toEqual($cli->data);
});

it("edge: Accept vnd.capabilities.cli+json only changes presentation envelope [D-009]", function () {
    $bus = HttpHelpers::mockBus(
        invokeResult: \Rawphp\Capabilities\Support\CapabilityResult::ok(['invoice_id' => 1]),
    );
    $controller = new CapabilityController($bus);
    $req = HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['customer_id' => 1, 'amount_cents' => 100, 'currency' => 'USD'],
        'headers' => ['accept' => 'application/vnd.capabilities.cli+json'],
    ]);

    $res = $controller->invoke($req, 'create-invoice');
    expect($res->isOk())->toBeTrue()
        ->and($res->headers['Content-Type'] ?? null)->toBe('application/vnd.capabilities.cli+json')
        ->and($res->body)->toHaveKey('ok')
        ->and($res->body['ok'])->toBeTrue();
});

it("happy: AuthController serves token and device-code for CLI and API clients [D-009]", function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true]);
    expect($auth->tokenFlowAvailable())->toBeTrue()
        ->and($auth->deviceCodeFlowAvailable())->toBeTrue()
        ->and($auth->registeredFlows())->toContain(RouteTable::ROUTE_AUTH_TOKEN)
        ->and($auth->registeredFlows())->toContain(RouteTable::ROUTE_AUTH_DEVICE);

    $token = $auth->token(new HttpRequestContext(authenticated: true, jsonBody: []));
    $device = $auth->device(new HttpRequestContext(authenticated: true, jsonBody: []));
    expect($token->isOk())->toBeTrue()
        ->and($device->isOk())->toBeTrue()
        ->and($token->body['data'])->toHaveKey('access_token')
        ->and($device->body['data'])->toHaveKey('device_code');
});

it("happy: ApprovalController shared for UI CLI and API [D-009]", function () {
    expect(class_exists(ApprovalController::class))->toBeTrue();
    $routes = HttpHelpers::routes(['enabled' => true]);
    $accept = RouteTable::find($routes, RouteTable::ROUTE_APPROVAL_ACCEPT);
    $reject = RouteTable::find($routes, RouteTable::ROUTE_APPROVAL_REJECT);
    expect($accept['action'])->toBe('ApprovalController@accept')
        ->and($reject['action'])->toBe('ApprovalController@reject');
});

it("happy: route GET /capabilities registered when http enabled [D-009]", function () {
    $routes = HttpHelpers::routes(['enabled' => true, 'prefix' => 'capabilities']);
    $r = RouteTable::find($routes, RouteTable::ROUTE_LIST);
    expect($r)->not->toBeNull()
        ->and($r['method'])->toBe('GET')
        ->and($r['uri'])->toBe('capabilities')
        ->and(RouteTable::pathFor(RouteTable::ROUTE_LIST))->toBe('/capabilities');
});

it("fail: route GET /capabilities not registered when http disabled [D-009]", function () {
    $routes = HttpHelpers::routes(['enabled' => false]);
    expect(RouteTable::has($routes, RouteTable::ROUTE_LIST))->toBeFalse()
        ->and($routes)->toBeEmpty();
});

it("happy: route GET /capabilities/{name} registered when http enabled [D-009]", function () {
    $routes = HttpHelpers::routes(['enabled' => true]);
    $r = RouteTable::find($routes, RouteTable::ROUTE_DESCRIBE);
    expect($r['method'])->toBe('GET')->and($r['uri'])->toBe('capabilities/{name}');
});

it("fail: route GET /capabilities/{name} not registered when http disabled [D-009]", function () {
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_DESCRIBE))->toBeFalse();
});

it("happy: route POST /capabilities/{name} registered when http enabled [D-009]", function () {
    $r = RouteTable::find(HttpHelpers::routes(['enabled' => true]), RouteTable::ROUTE_INVOKE);
    expect($r['method'])->toBe('POST')->and($r['uri'])->toBe('capabilities/{name}');
});

it("fail: route POST /capabilities/{name} not registered when http disabled [D-009]", function () {
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_INVOKE))->toBeFalse();
});

it("happy: route POST /capabilities/approvals/{id}/accept registered when http enabled [D-009]", function () {
    $r = RouteTable::find(HttpHelpers::routes(['enabled' => true]), RouteTable::ROUTE_APPROVAL_ACCEPT);
    expect($r['method'])->toBe('POST')->and($r['uri'])->toBe('capabilities/approvals/{id}/accept');
});

it("fail: route POST /capabilities/approvals/{id}/accept not registered when http disabled [D-009]", function () {
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_APPROVAL_ACCEPT))->toBeFalse();
});

it("happy: route POST /capabilities/approvals/{id}/reject registered when http enabled [D-009]", function () {
    $r = RouteTable::find(HttpHelpers::routes(['enabled' => true]), RouteTable::ROUTE_APPROVAL_REJECT);
    expect($r['uri'])->toBe('capabilities/approvals/{id}/reject');
});

it("fail: route POST /capabilities/approvals/{id}/reject not registered when http disabled [D-009]", function () {
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_APPROVAL_REJECT))->toBeFalse();
});

it("happy: route GET /capabilities/health registered when http enabled [D-009]", function () {
    $r = RouteTable::find(HttpHelpers::routes(['enabled' => true]), RouteTable::ROUTE_HEALTH);
    expect($r['method'])->toBe('GET')->and($r['uri'])->toBe('capabilities/health');
});

it("fail: route GET /capabilities/health not registered when http disabled [D-009]", function () {
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_HEALTH))->toBeFalse();
});

it("fail: second invoke controller tree for CLI is refused [D-009]", function () {
    $routes = HttpHelpers::routes(['enabled' => true]);
    $invokeActions = array_filter($routes, fn ($r) => str_contains($r['action'], 'invoke'));
    expect(count($invokeActions))->toBe(1)
        ->and(array_values($invokeActions)[0]['action'])->toBe('CapabilityController@invoke')
        ->and(class_exists('Rawphp\\Capabilities\\Adapters\\Http\\CliCapabilityController'))->toBeFalse();
});
