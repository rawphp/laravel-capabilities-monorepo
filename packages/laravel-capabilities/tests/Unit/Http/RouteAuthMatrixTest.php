<?php

// REQ-011 fleshed unit tests for Http/RouteAuthMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$protectedRoutes = [
    'GET list' => RouteTable::ROUTE_LIST,
    'GET describe' => RouteTable::ROUTE_DESCRIBE,
    'POST invoke' => RouteTable::ROUTE_INVOKE,
    'POST approval_accept' => RouteTable::ROUTE_APPROVAL_ACCEPT,
    'POST approval_reject' => RouteTable::ROUTE_APPROVAL_REJECT,
];

foreach ($protectedRoutes as $label => $routeKey) {
    it("fail: unauthenticated rejected when {$label} auth=none [HTTP-001]", function () use ($routeKey) {
        $gate = new HttpAuthGate;
        $req = HttpHelpers::guestRequest();
        expect($gate->allows($routeKey, HttpAuthGate::AUTH_NONE, $req))->toBeFalse();

        $h = HttpHelpers::harness();
        $response = match ($routeKey) {
            RouteTable::ROUTE_LIST => $h['controller']->list($req),
            RouteTable::ROUTE_DESCRIBE => $h['controller']->describe($req, $h['name']),
            RouteTable::ROUTE_INVOKE => $h['controller']->invoke($req->with([
                'method' => 'POST',
                'jsonBody' => [],
            ]), $h['name']),
            RouteTable::ROUTE_APPROVAL_ACCEPT => $h['approvalController']->accept($req, 'appr-1'),
            RouteTable::ROUTE_APPROVAL_REJECT => $h['approvalController']->reject($req, 'appr-1'),
        };
        expect($response->errorCode())->toBe('unauthenticated')
            ->and($response->status)->toBe(401);
    });

    foreach ([HttpAuthGate::AUTH_USER, HttpAuthGate::AUTH_CLI_TOKEN, HttpAuthGate::AUTH_API_TOKEN] as $auth) {
        it("happy: authenticated allowed path when {$label} auth={$auth} [HTTP-001]", function () use ($routeKey, $auth) {
            $gate = new HttpAuthGate;
            $req = HttpHelpers::authedRequest(['authKind' => $auth]);
            expect($gate->allows($routeKey, $auth, $req))->toBeTrue();
        });
    }
}

foreach ([HttpAuthGate::AUTH_NONE, HttpAuthGate::AUTH_USER, HttpAuthGate::AUTH_CLI_TOKEN, HttpAuthGate::AUTH_API_TOKEN] as $auth) {
    it("edge: health auth policy when GET health auth={$auth} [HTTP-001]", function () use ($auth) {
        $private = new HttpAuthGate(['health_public' => false]);
        $public = new HttpAuthGate(['health_public' => true]);

        $guest = HttpHelpers::guestRequest(['authKind' => $auth]);
        $user = HttpHelpers::authedRequest(['authKind' => $auth === HttpAuthGate::AUTH_NONE ? HttpAuthGate::AUTH_USER : $auth]);

        if ($auth === HttpAuthGate::AUTH_NONE) {
            expect($private->allowsHealth($auth, $guest))->toBeFalse()
                ->and($public->allowsHealth($auth, $guest))->toBeTrue();
        } else {
            expect($private->allowsHealth($auth, $user))->toBeTrue()
                ->and($public->allowsHealth($auth, $user))->toBeTrue();
        }
    });
}
