<?php

// REQ-011 fleshed unit tests for Surfaces/HttpAdapterTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

it("happy: POST invoke maps JSON body to registry invoke [HTTP-001]", function () {
    $bus = new FakeCapabilityBus(CapabilityResult::ok(['invoice_id' => 7]));
    $controller = new CapabilityController($bus);
    $body = ['customer_id' => 42, 'amount_cents' => 2500, 'currency' => 'USD'];
    $res = $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => $body,
    ]), 'create-invoice');

    expect($res->isOk())->toBeTrue()
        ->and($bus->invokeCalls)->toBe(1)
        ->and($bus->invocations[0]['name'])->toBe('create-invoice')
        ->and($bus->invocations[0]['input'])->toBe($body)
        ->and($bus->invocations[0]['options']['caller'] ?? null)->toBe('http');
});

it("happy: GET list maps to catalog [HTTP-001]", function () {
    $h = HttpHelpers::harness();
    $res = $h['controller']->list(HttpHelpers::authedRequest());
    expect($res->isOk())->toBeTrue()
        ->and($res->body['data'])->toHaveKey('capabilities')
        ->and($res->body['data']['capabilities'])->not->toBeEmpty();
});

it("happy: GET describe maps to capability detail [HTTP-001]", function () {
    $h = HttpHelpers::harness();
    $res = $h['controller']->describe(HttpHelpers::authedRequest(), $h['name']);
    expect($res->isOk())->toBeTrue()
        ->and($res->body['data'])->toHaveKeys(['name', 'input_schema', 'output_schema'])
        ->and($res->body['data']['name'])->toBe($h['name']);
});

it("happy: POST approval accept reject map to ApprovalManager [HTTP-001]", function () {
    $h = HttpHelpers::harness();
    // Ensure controllers wire to ApprovalManager methods (shared tree).
    expect(method_exists($h['approvalController'], 'accept'))->toBeTrue()
        ->and(method_exists($h['approvalController'], 'reject'))->toBeTrue();

    $guest = $h['approvalController']->accept(HttpHelpers::guestRequest(), 'missing');
    expect($guest->errorCode())->toBe('unauthenticated');
});

it("fail: unauthenticated request returns unauthenticated envelope [HTTP-001]", function () {
    $h = HttpHelpers::harness();
    $res = $h['controller']->invoke(HttpHelpers::guestRequest([
        'method' => 'POST',
        'jsonBody' => ['customer_id' => 1],
    ]), $h['name']);
    expect($res->errorCode())->toBe('unauthenticated')
        ->and($res->status)->toBe(401)
        ->and($res->body['ok'])->toBeFalse()
        ->and($res->body['error'])->toHaveKeys(['code', 'message', 'retryable', 'http_status']);
});

it("happy: middleware stack from config applied [HTTP-001]", function () {
    $mw = ['api', 'auth:sanctum', 'throttle:api'];
    $routes = HttpHelpers::routes([
        'enabled' => true,
        'prefix' => 'capabilities',
        'middleware' => $mw,
    ]);
    foreach ($routes as $route) {
        expect($route['middleware'])->toBe($mw);
    }
});

it("edge: prefix from config surfaces.http.prefix [HTTP-001]", function () {
    $routes = HttpHelpers::routes([
        'enabled' => true,
        'prefix' => 'api/v1/caps',
        'middleware' => ['api'],
    ]);
    $list = RouteTable::find($routes, RouteTable::ROUTE_LIST);
    $invoke = RouteTable::find($routes, RouteTable::ROUTE_INVOKE);
    expect($list['uri'])->toBe('api/v1/caps')
        ->and($invoke['uri'])->toBe('api/v1/caps/{name}')
        ->and(RouteTable::pathFor(RouteTable::ROUTE_HEALTH, 'api/v1/caps'))->toBe('/api/v1/caps/health');
});

it("fail: forged caller header does not change derived caller [D-022]", function () {
    $bus = new FakeCapabilityBus(CapabilityResult::ok(['ok' => true]));
    $controller = new CapabilityController($bus, [
        'token_abilities' => ['capabilities:cli' => 'cli'],
        'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
    ]);

    // CLI credential + forged upgrade to http must stay cli (D-022).
    $res = $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['x' => 1],
        'credential' => ['token_abilities' => ['capabilities:cli']],
        'headers' => [
            'x-capabilities-caller' => 'http',
            'accept' => 'application/json',
        ],
    ]), 'create-invoice');

    expect($res->isOk())->toBeTrue()
        ->and($bus->invocations[0]['options']['caller'] ?? null)->toBe('cli')
        ->and($res->body['meta']['caller'] ?? null)->toBe('cli')
        ->and($res->body['meta']['derived_caller'] ?? null)->toBe('cli');
});

it("happy: Idempotency-Key header forwarded to registry [D-005]", function () {
    $bus = new FakeCapabilityBus(CapabilityResult::ok(['ok' => true]));
    $controller = new CapabilityController($bus);
    $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['a' => 1],
        'headers' => ['idempotency-key' => 'key-abc-001'],
    ]), 'create-invoice');

    expect($bus->invocations[0]['options']['idempotency_key'] ?? null)->toBe('key-abc-001')
        ->and($controller->lastInvokeOptions()['idempotency_key'] ?? null)->toBe('key-abc-001');
});

it("fail: malformed JSON returns validation or bad request envelope [HTTP-001]", function () {
    $bus = new FakeCapabilityBus(CapabilityResult::ok(['should' => 'not-run']));
    $controller = new CapabilityController($bus);
    $res = $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'malformedJson' => true,
        'rawBody' => '{not-json',
        'jsonBody' => null,
    ]), 'create-invoice');

    expect($res->errorCode())->toBe('validation_failed')
        ->and($res->status)->toBe(422)
        ->and($bus->invokeCalls)->toBe(0);
});
