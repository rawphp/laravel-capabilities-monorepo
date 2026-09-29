<?php

declare(strict_types=1);

// RouteTable lookups and paths.

use Rawphp\Capabilities\Http\RouteTable;

it('RouteTable finds routes with an empty prefix and builds a rooted path for every action key', function () {
    expect(RouteTable::actionKeys())->toContain(RouteTable::ROUTE_INVOKE);

    $emptyPrefix = RouteTable::routes(['enabled' => true, 'prefix' => '', 'middleware' => ['api', '']]);
    expect($emptyPrefix)->not->toBeEmpty();
    $list = RouteTable::find($emptyPrefix, RouteTable::ROUTE_LIST);
    expect($list)->not->toBeNull()->and($list['uri'] ?? '')->toContain('capabilities');

    expect(RouteTable::has($emptyPrefix, RouteTable::ROUTE_HEALTH))->toBeTrue()
        ->and(RouteTable::has($emptyPrefix, 'nope'))->toBeFalse()
        ->and(RouteTable::find($emptyPrefix, 'nope'))->toBeNull();

    foreach ([
        RouteTable::ROUTE_LIST,
        RouteTable::ROUTE_DESCRIBE,
        RouteTable::ROUTE_INVOKE,
        RouteTable::ROUTE_APPROVAL_ACCEPT,
        RouteTable::ROUTE_APPROVAL_REJECT,
        RouteTable::ROUTE_HEALTH,
        RouteTable::ROUTE_AUTH_TOKEN,
        RouteTable::ROUTE_AUTH_DEVICE,
        RouteTable::ROUTE_AUTH_OAUTH_CALLBACK,
        'unknown',
    ] as $key) {
        expect(RouteTable::pathFor($key, 'capabilities', 'inv', 'appr'))->toStartWith('/');
    }
});
