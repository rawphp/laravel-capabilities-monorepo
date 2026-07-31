<?php

// REQ-072: AuthController fail-closed without AuthTokenIssuer (L-002). Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\IlluminateAuthController;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;
use Illuminate\Http\Request;

it('fail-closed: token without issuer returns not_configured and no fake bearer [L-002]', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], null);
    expect($auth->tokenFlowAvailable())->toBeFalse();

    $res = $auth->token(new HttpRequestContext(jsonBody: ['access_token' => 'client-supplied-evil']));

    expect($res->isOk())->toBeFalse()
        ->and($res->errorCode())->toBe('not_configured')
        ->and($res->status)->toBe(501)
        ->and(json_encode($res->body))->not->toContain('client-supplied-evil')
        ->and(json_encode($res->body))->not->toContain('issued-by-host')
        ->and($res->body['data']['access_token'] ?? null)->toBeNull();
});

it('fail-closed: device without issuer returns not_configured no placeholder [L-002]', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true]);
    $res = $auth->device(new HttpRequestContext(jsonBody: []));

    expect($res->errorCode())->toBe('not_configured')
        ->and($res->status)->toBe(501)
        ->and(json_encode($res->body))->not->toContain('device-code-placeholder')
        ->and($res->body['data']['device_code'] ?? null)->toBeNull();
});

it('fail-closed: oauth callback without issuer returns not_configured [L-002]', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true]);
    $res = $auth->oauthCallback(new HttpRequestContext(query: ['code' => 'abc']));

    expect($res->errorCode())->toBe('not_configured')
        ->and($res->status)->toBe(501)
        ->and($res->isOk())->toBeFalse();
});

it('fail-closed: never accepts client-supplied access_token as issued credential [L-002]', function () {
    $issuer = HttpHelpers::fakeAuthTokenIssuer([
        'token' => [
            'token_type' => 'Bearer',
            'access_token' => 'only-from-issuer',
            'expires_in' => 120,
        ],
    ]);
    $auth = new AuthController(['enabled' => true], ['enabled' => true], $issuer);

    $res = $auth->token(new HttpRequestContext(jsonBody: [
        'access_token' => 'client-forged-token',
        'grant_type' => 'client_credentials',
    ]));

    expect($res->isOk())->toBeTrue()
        ->and($res->body['data']['access_token'])->toBe('only-from-issuer')
        ->and($res->body['data']['access_token'])->not->toBe('client-forged-token');
});

it('happy: bound issuer drives token device and oauth flows [L-002]', function () {
    $issuer = HttpHelpers::fakeAuthTokenIssuer();
    $auth = new AuthController(['enabled' => true], ['enabled' => true], $issuer);

    expect($auth->tokenFlowAvailable())->toBeTrue()
        ->and($auth->deviceCodeFlowAvailable())->toBeTrue()
        ->and($auth->oauthCallbackFlowAvailable())->toBeTrue()
        ->and($auth->registeredFlows())->toContain(RouteTable::ROUTE_AUTH_TOKEN);

    $token = $auth->token(new HttpRequestContext(jsonBody: ['grant_type' => 'password']));
    $device = $auth->device(new HttpRequestContext(jsonBody: []));
    $oauth = $auth->oauthCallback(new HttpRequestContext(query: ['code' => 'xyz']));

    expect($token->isOk())->toBeTrue()
        ->and($token->body['data']['access_token'])->toBe('host-issued-token')
        ->and($device->isOk())->toBeTrue()
        ->and($device->body['data']['device_code'])->toBe('host-device-code')
        ->and($oauth->isOk())->toBeTrue()
        ->and($oauth->body['data']['status'])->toBe('authorized')
        ->and($oauth->body['data']['code'])->toBe('xyz');
});

it('fail: http disabled still not_found and unbound issuer does not open flow [L-002]', function () {
    $issuer = HttpHelpers::fakeAuthTokenIssuer();
    $auth = new AuthController(['enabled' => false], ['enabled' => true], $issuer);

    expect($auth->tokenFlowAvailable())->toBeFalse()
        ->and($auth->registeredFlows())->toBeEmpty();

    $res = $auth->token(new HttpRequestContext(jsonBody: []));
    expect($res->errorCode())->toBe('not_found');
});

it('routes: auth endpoints strip auth:sanctum so issuance is not behind sanctum [L-002]', function () {
    $routes = RouteTable::routes([
        'enabled' => true,
        'prefix' => 'capabilities',
        'middleware' => ['api', 'auth:sanctum'],
    ]);

    $token = RouteTable::find($routes, RouteTable::ROUTE_AUTH_TOKEN);
    $device = RouteTable::find($routes, RouteTable::ROUTE_AUTH_DEVICE);
    $oauth = RouteTable::find($routes, RouteTable::ROUTE_AUTH_OAUTH_CALLBACK);
    $invoke = RouteTable::find($routes, RouteTable::ROUTE_INVOKE);

    expect($token['middleware'])->toBe(['api'])
        ->and($token['middleware'])->not->toContain('auth:sanctum')
        ->and($device['middleware'])->toBe(['api'])
        ->and($oauth['middleware'])->toBe(['api'])
        ->and($invoke['middleware'])->toBe(['api', 'auth:sanctum']);
});

it('error map: not_configured is 501 [L-002]', function () {
    expect(ErrorCodeMap::isKnown('not_configured'))->toBeTrue()
        ->and(ErrorCodeMap::httpStatus('not_configured'))->toBe(501);
});

it('IlluminateAuthController fail-closed without issuer [L-002]', function () {
    $wrapper = new IlluminateAuthController(new AuthController(['enabled' => true], ['enabled' => true]));
    $request = Request::create(
        '/capabilities/auth/token',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['access_token' => 'tok-from-client'], JSON_THROW_ON_ERROR),
    );

    $response = $wrapper->token($request);
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(501)
        ->and($payload['error']['code'] ?? null)->toBe('not_configured')
        ->and($payload['data']['access_token'] ?? null)->toBeNull();
});

it('IlluminateAuthController uses bound issuer [L-002]', function () {
    $wrapper = new IlluminateAuthController(
        new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer()),
    );
    $request = Request::create(
        '/capabilities/auth/token',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['access_token' => 'tok-from-client'], JSON_THROW_ON_ERROR),
    );

    $response = $wrapper->token($request);
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['data']['access_token'] ?? null)->toBe('host-issued-token')
        ->and($payload['data']['access_token'] ?? null)->not->toBe('tok-from-client');
});

it('harness auth without issuer is fail-closed [L-002]', function () {
    $h = HttpHelpers::harness();
    $res = $h['auth']->token(HttpHelpers::guestRequest(['method' => 'POST', 'jsonBody' => []]));
    expect($res->errorCode())->toBe('not_configured');
});
