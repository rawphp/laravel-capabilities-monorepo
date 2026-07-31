<?php

// REQ-071: Illuminate Request/Response bridge (L-001). Unit-only request fixtures — no Feature suite.

declare(strict_types=1);

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Adapters\Http\IlluminateApprovalController;
use Rawphp\Capabilities\Adapters\Http\IlluminateAuthController;
use Rawphp\Capabilities\Adapters\Http\IlluminateCapabilityController;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\IlluminateHttpBridge;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

it('maps array fixture stand-in to HttpRequestContext with server-derived auth', function () {
    $user = HttpHelpers::user(7);
    $ctx = IlluminateHttpBridge::fromArray([
        'method' => 'POST',
        'path' => '/capabilities/create-invoice',
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => 'idem-1',
            'Authorization' => 'Bearer secret',
        ],
        'query' => ['include_schemas' => '1'],
        'json' => ['customer_id' => 1, 'amount_cents' => 100, 'currency' => 'USD'],
        'user' => $user,
        'authenticated' => true,
        'token_abilities' => ['capabilities:cli'],
    ]);

    expect($ctx)->toBeInstanceOf(HttpRequestContext::class)
        ->and($ctx->authenticated)->toBeTrue()
        ->and($ctx->user)->toBe($user)
        ->and($ctx->method)->toBe('POST')
        ->and($ctx->path)->toBe('/capabilities/create-invoice')
        ->and($ctx->header('accept'))->toBe('application/json')
        ->and($ctx->header('idempotency-key'))->toBe('idem-1')
        ->and($ctx->jsonBody)->toBe(['customer_id' => 1, 'amount_cents' => 100, 'currency' => 'USD'])
        ->and($ctx->query['include_schemas'] ?? null)->toBe('1')
        ->and($ctx->authKind)->toBe(HttpAuthGate::AUTH_CLI_TOKEN)
        ->and($ctx->credential['token_abilities'] ?? null)->toBe(['capabilities:cli'])
        ->and($ctx->credential['adapter'] ?? null)->toBe('http');
});

it('maps unauthenticated Illuminate Request defaults closed', function () {
    $request = Request::create('/capabilities', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $ctx = IlluminateHttpBridge::fromIlluminate($request);

    expect($ctx->authenticated)->toBeFalse()
        ->and($ctx->user)->toBeNull()
        ->and($ctx->authKind)->toBe(HttpAuthGate::AUTH_NONE)
        ->and($ctx->credential)->toBe([])
        ->and($ctx->method)->toBe('GET')
        ->and($ctx->header('accept'))->toBe('application/json');
});

it('maps authenticated Illuminate Request user and token abilities from server resolver', function () {
    $user = new class
    {
        public int $id = 42;

        public function currentAccessToken(): object
        {
            return (object) ['abilities' => ['*']];
        }
    };

    $request = Request::create('/capabilities/health', 'GET');
    $request->setUserResolver(static fn () => $user);

    $ctx = IlluminateHttpBridge::fromIlluminate($request);

    expect($ctx->authenticated)->toBeTrue()
        ->and($ctx->user)->toBe($user)
        ->and($ctx->authKind)->toBe(HttpAuthGate::AUTH_USER)
        ->and($ctx->credential['adapter'] ?? null)->toBe('http')
        ->and($ctx->credential['token_abilities'] ?? null)->toBe(['*']);
});

it('ignores client-claimed caller and tenant in body for credential (D-022)', function () {
    $user = HttpHelpers::user(3);
    $ctx = IlluminateHttpBridge::fromArray([
        'authenticated' => true,
        'user' => $user,
        'json' => [
            'caller' => 'cli',
            'tenant' => 'evil-tenant',
            'actor' => 'spoofed',
            'customer_id' => 9,
        ],
        'headers' => [
            'X-Capabilities-Caller' => 'cli',
            'X-Capabilities-Tenant' => 'evil-tenant',
        ],
        // no token abilities — server-derived adapter only
    ]);

    expect($ctx->authenticated)->toBeTrue()
        ->and($ctx->jsonBody['caller'] ?? null)->toBe('cli') // body may still carry junk for domain
        ->and($ctx->credential)->not->toHaveKey('server_caller')
        ->and($ctx->credential['adapter'] ?? null)->toBe('http')
        ->and($ctx->credential)->not->toHaveKey('tenant')
        ->and($ctx->credential)->not->toHaveKey('caller')
        ->and($ctx->claimedCallerHeader())->toBe('cli'); // header preserved for CallerDeriver policy only

    $controller = new CapabilityController(HttpHelpers::mockBus());
    $resolved = $controller->resolveCaller($ctx);
    // Derived always from server credential (adapter=http), never from body.caller (D-022).
    // Optional X-Capabilities-Caller may only downgrade privilege after server derive.
    expect($resolved['derived'])->toBe('http')
        ->and($resolved['derived'])->not->toBe('cli')
        ->and($resolved['reason'])->toBe('downgrade') // header cli is a privilege downgrade from http
        ->and($resolved['caller'])->toBe('cli');
});

it('ignores client body caller when mapping real Illuminate Request (D-022)', function () {
    $user = HttpHelpers::user(1);
    $request = Request::create(
        '/capabilities/foo',
        'POST',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CAPABILITIES_CALLER' => 'agent',
            'HTTP_ACCEPT' => 'application/json',
        ],
        json_encode(['caller' => 'agent', 'tenant_id' => 't-1', 'amount_cents' => 1], JSON_THROW_ON_ERROR),
    );
    $request->setUserResolver(static fn () => $user);

    $ctx = IlluminateHttpBridge::fromIlluminate($request);

    expect($ctx->credential)->not->toHaveKey('server_caller')
        ->and($ctx->credential['adapter'] ?? null)->toBe('http')
        ->and($ctx->jsonBody['caller'] ?? null)->toBe('agent')
        ->and($ctx->claimedCallerHeader())->toBe('agent');

    $controller = new CapabilityController(HttpHelpers::mockBus());
    $resolved = $controller->resolveCaller($ctx);
    // Server-derived is http; body caller is never the derivation source.
    expect($resolved['derived'])->toBe('http')
        ->and($resolved['derived'])->not->toBe('agent');
});

it('header alone without server auth never establishes caller identity (D-022)', function () {
    $ctx = IlluminateHttpBridge::fromArray([
        'authenticated' => false,
        'headers' => ['x-capabilities-caller' => 'cli'],
        'json' => ['caller' => 'cli'],
    ]);

    $controller = new CapabilityController(HttpHelpers::mockBus());
    $resolved = $controller->resolveCaller($ctx);

    expect($ctx->authenticated)->toBeFalse()
        ->and($ctx->credential)->toBe([])
        ->and($resolved['derived'])->toBe('http')
        ->and($resolved['caller'])->toBe('http')
        ->and($resolved['reason'])->toBe('header_alone_ignored');
});

it('converts HttpResponse to Illuminate JsonResponse with status headers body', function () {
    $http = HttpResponse::ok(['id' => 1], ['caller' => 'http'], 201, [
        'Content-Type' => 'application/json',
        'X-Request-Id' => 'req-9',
    ]);

    $illuminate = IlluminateHttpBridge::toIlluminate($http);

    expect($illuminate)->toBeInstanceOf(JsonResponse::class)
        ->and($illuminate->getStatusCode())->toBe(201)
        ->and($illuminate->headers->get('X-Request-Id'))->toBe('req-9')
        ->and(json_decode($illuminate->getContent(), true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'ok' => true,
            'data' => ['id' => 1],
        ]);
});

it('HttpResponse is Responsable and yields JsonResponse', function () {
    $http = HttpResponse::failure('unauthenticated', 'Authentication required.');

    expect($http)->toBeInstanceOf(Responsable::class);

    $response = $http->toResponse(Request::create('/capabilities', 'GET'));

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['ok'] ?? null)->toBeFalse();
});

it('fromIlluminate and fromArray are available aliases on bridge', function () {
    expect(method_exists(IlluminateHttpBridge::class, 'fromIlluminate'))->toBeTrue()
        ->and(method_exists(IlluminateHttpBridge::class, 'fromArray'))->toBeTrue()
        ->and(method_exists(IlluminateHttpBridge::class, 'toIlluminate'))->toBeTrue()
        ->and(method_exists(IlluminateHttpBridge::class, 'toRequestContext'))->toBeTrue();
});

it('toRequestContext accepts array fixture or Illuminate Request', function () {
    $fromArray = IlluminateHttpBridge::toRequestContext(['method' => 'GET', 'path' => '/x']);
    $fromReq = IlluminateHttpBridge::toRequestContext(Request::create('/x', 'GET'));

    expect($fromArray)->toBeInstanceOf(HttpRequestContext::class)
        ->and($fromReq)->toBeInstanceOf(HttpRequestContext::class)
        ->and($fromArray->authenticated)->toBeFalse()
        ->and($fromReq->authenticated)->toBeFalse();
});

it('thin IlluminateCapabilityController maps Request → pure controller → JsonResponse', function () {
    $bus = HttpHelpers::mockBus();
    $inner = new CapabilityController($bus, [], ['health_public' => true]);
    $wrapper = new IlluminateCapabilityController($inner);

    $request = Request::create('/capabilities/health', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $wrapper->health($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['ok'] ?? null)->toBeTrue();
});

it('thin IlluminateCapabilityController denies unauthenticated list', function () {
    $wrapper = new IlluminateCapabilityController(
        new CapabilityController(HttpHelpers::mockBus()),
    );

    $response = $wrapper->list(Request::create('/capabilities', 'GET'));

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['error']['code'] ?? null)->toBe('unauthenticated');
});

it('thin IlluminateAuthController issues token shape via bridge', function () {
    $issuer = HttpHelpers::fakeAuthTokenIssuer([
        'token' => [
            'token_type' => 'Bearer',
            'access_token' => 'tok-from-host',
            'expires_in' => 3600,
        ],
    ]);
    $wrapper = new IlluminateAuthController(new AuthController(['enabled' => true], ['enabled' => true], $issuer));
    $request = Request::create(
        '/capabilities/auth/token',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['grant_type' => 'client_credentials'], JSON_THROW_ON_ERROR),
    );

    $response = $wrapper->token($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true)['data']['access_token'] ?? null)->toBe('tok-from-host');
});

it('RouteTable registrar resolves Illuminate wrapper controllers for Laravel-style uses', function () {
    $defs = HttpRouteRegistrar::definitions(['enabled' => true]);
    $byKey = [];
    foreach ($defs as $def) {
        $byKey[$def['key']] = $def;
    }

    expect($byKey['list']['uses'][0])->toBe(IlluminateCapabilityController::class)
        ->and($byKey['list']['uses'][1])->toBe('list')
        ->and($byKey['invoke']['uses'][0])->toBe(IlluminateCapabilityController::class)
        ->and($byKey['auth_token']['uses'][0])->toBe(IlluminateAuthController::class)
        ->and($byKey['approval_accept']['uses'][0])->toBe(IlluminateApprovalController::class);

    foreach ($defs as $def) {
        expect(class_exists($def['uses'][0]))->toBeTrue()
            ->and(method_exists($def['uses'][0], $def['uses'][1]))->toBeTrue();
    }
});

it('malformed JSON body sets malformedJson on context', function () {
    $request = Request::create(
        '/capabilities/x',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        '{not-json',
    );
    $request->setUserResolver(static fn () => HttpHelpers::user());

    $ctx = IlluminateHttpBridge::fromIlluminate($request);

    expect($ctx->malformedJson)->toBeTrue()
        ->and($ctx->jsonBody)->toBeNull();
});

it('array fixture without user stays unauthenticated by default', function () {
    $ctx = IlluminateHttpBridge::fromArray([
        'method' => 'GET',
        'headers' => ['x-capabilities-caller' => 'cli'],
        'json' => ['caller' => 'cli'],
    ]);

    expect($ctx->authenticated)->toBeFalse()
        ->and($ctx->user)->toBeNull()
        ->and($ctx->authKind)->toBe(HttpAuthGate::AUTH_NONE)
        ->and($ctx->credential)->toBe([]);
});
