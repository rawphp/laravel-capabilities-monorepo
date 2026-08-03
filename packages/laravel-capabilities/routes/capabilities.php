<?php

/**
 * Capability HTTP routes — single catalog + invoke API (D-009 / REQ-021).
 *
 * Product CLI is a remote HTTP client of these routes, not a second controller tree.
 * Registered only when surfaces.http.enabled is true via
 * {@see CapabilitiesServiceProvider::bootHttpRoutes()}
 * which maps pure {@see RouteTable} definitions.
 *
 * This file is the documentation / import surface for the table — the service
 * provider is the lifecycle owner (not a hand-duplicated Route:: list).
 *
 * | Method | Path | Action |
 * |--------|------|--------|
 * | GET    | /{prefix} | CapabilityController@list |
 * | GET    | /{prefix}/health | CapabilityController@health |
 * | POST   | /{prefix}/auth/token | AuthController@token |
 * | POST   | /{prefix}/auth/device | AuthController@device |
 * | GET    | /{prefix}/auth/callback | AuthController@oauthCallback |
 * | POST   | /{prefix}/approvals/{id}/accept | ApprovalController@accept |
 * | POST   | /{prefix}/approvals/{id}/reject | ApprovalController@reject |
 * | GET    | /{prefix}/{name} | CapabilityController@describe |
 * | POST   | /{prefix}/{name} | CapabilityController@invoke |
 */

use Rawphp\Capabilities\CapabilitiesServiceProvider;
use Rawphp\Capabilities\Http\HttpRouteRegistrar;
use Rawphp\Capabilities\Http\RouteTable;

return static function (array $httpConfig = []): array {
    $config = $httpConfig === [] ? [
        'enabled' => true,
        'prefix' => 'capabilities',
        'middleware' => ['api', 'auth:sanctum'],
    ] : $httpConfig;

    return HttpRouteRegistrar::definitions($config);
};

// Re-export action keys for static analysis / IDE.
class_exists(RouteTable::class);
class_exists(HttpRouteRegistrar::class);
