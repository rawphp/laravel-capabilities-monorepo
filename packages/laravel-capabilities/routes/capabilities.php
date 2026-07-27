<?php

/**
 * Capability HTTP routes — single catalog + invoke API (D-009).
 *
 * Product CLI is a remote HTTP client of these routes, not a second controller tree.
 * Registered only when surfaces.http.enabled is true (see RouteTable + service provider).
 *
 * Definitions (pure): {@see \Rawphp\Capabilities\Http\RouteTable::routes()}
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

use Rawphp\Capabilities\Adapters\Http\ApprovalController;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Http\RouteTable;

return static function (array $httpConfig = []): array {
    return RouteTable::routes($httpConfig === [] ? [
        'enabled' => true,
        'prefix' => 'capabilities',
        'middleware' => ['api', 'auth:sanctum'],
    ] : $httpConfig);
};

// Controllers referenced for static discovery / IDE — single tree only.
class_exists(CapabilityController::class);
class_exists(ApprovalController::class);
class_exists(AuthController::class);
