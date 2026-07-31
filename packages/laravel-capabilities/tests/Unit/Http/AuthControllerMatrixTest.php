<?php

// REQ-011 fleshed unit tests for Http/AuthControllerMatrixTest.php. Unit-only, no database.
// REQ-072: flows require host-bound AuthTokenIssuer (L-002).

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

// Inventory titles: "when cli or http auth enabled". Happy paths still require
// http surface enabled + bound AuthTokenIssuer (fail-closed without issuer: L-002).
it("happy: auth flow token available when cli or http auth enabled [D-009]", function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->tokenFlowAvailable())->toBeTrue()
        ->and($auth->registeredFlows())->toContain(RouteTable::ROUTE_AUTH_TOKEN);
    $res = $auth->token(new HttpRequestContext(jsonBody: []));
    expect($res->isOk())->toBeTrue();
});

it("fail: auth flow token not registered when http disabled [D-009]", function () {
    $auth = new AuthController(['enabled' => false], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->tokenFlowAvailable())->toBeFalse()
        ->and($auth->registeredFlows())->toBeEmpty();
    $res = $auth->token(new HttpRequestContext(jsonBody: []));
    expect($res->errorCode())->toBe('not_found');
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_AUTH_TOKEN))->toBeFalse();
});

it("happy: auth flow device_code available when cli or http auth enabled [D-009]", function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => false], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->deviceCodeFlowAvailable())->toBeTrue();
    $res = $auth->device(new HttpRequestContext(jsonBody: []));
    expect($res->isOk())->toBeTrue()->and($res->body['data'])->toHaveKey('device_code');
});

it("fail: auth flow device_code not registered when http disabled [D-009]", function () {
    $auth = new AuthController(['enabled' => false], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->deviceCodeFlowAvailable())->toBeFalse();
    expect(RouteTable::has(HttpHelpers::routes(['enabled' => false]), RouteTable::ROUTE_AUTH_DEVICE))->toBeFalse();
});

it("happy: auth flow oauth_callback available when cli or http auth enabled [D-009]", function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->oauthCallbackFlowAvailable())->toBeTrue();
    $res = $auth->oauthCallback(new HttpRequestContext(query: ['code' => 'abc']));
    expect($res->isOk())->toBeTrue()->and($res->body['data']['code'] ?? null)->toBe('abc');
});

it("fail: auth flow oauth_callback not registered when http disabled [D-009]", function () {
    $auth = new AuthController(['enabled' => false], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());
    expect($auth->oauthCallbackFlowAvailable())->toBeFalse()
        ->and($auth->registeredFlows())->not->toContain(RouteTable::ROUTE_AUTH_OAUTH_CALLBACK);
});
