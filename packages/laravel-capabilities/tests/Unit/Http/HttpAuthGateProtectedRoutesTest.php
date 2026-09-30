<?php

// L-109 / C-001: the auth issuance routes are how the CLI logs in — they are never "protected".

declare(strict_types=1);

use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\RouteTable;

it('does not list the auth issuance routes as protected', function () {
    $gate = new HttpAuthGate;

    expect(HttpAuthGate::PROTECTED)->not->toContain(RouteTable::ROUTE_AUTH_TOKEN)
        ->and(HttpAuthGate::PROTECTED)->not->toContain(RouteTable::ROUTE_AUTH_DEVICE)
        ->and($gate->isProtected(RouteTable::ROUTE_AUTH_TOKEN))->toBeFalse()
        ->and($gate->isProtected(RouteTable::ROUTE_AUTH_DEVICE))->toBeFalse()
        ->and($gate->isProtected(RouteTable::ROUTE_AUTH_OAUTH_CALLBACK))->toBeFalse();
});

it('keeps list, describe, invoke and the approval decisions protected', function () {
    $gate = new HttpAuthGate;

    foreach ([RouteTable::ROUTE_LIST, RouteTable::ROUTE_DESCRIBE, RouteTable::ROUTE_INVOKE, RouteTable::ROUTE_APPROVAL_ACCEPT, RouteTable::ROUTE_APPROVAL_REJECT] as $route) {
        expect($gate->isProtected($route))->toBeTrue();
    }

    expect((new HttpAuthGate)->isProtected(RouteTable::ROUTE_HEALTH))->toBeTrue()
        ->and((new HttpAuthGate(['health_public' => true]))->isProtected(RouteTable::ROUTE_HEALTH))->toBeFalse();
});
