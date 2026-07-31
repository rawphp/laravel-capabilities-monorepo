<?php

namespace Rawphp\Capabilities\Http;

/**
 * Declarative HTTP capability route table (D-009).
 *
 * Pure / unit-testable: no Illuminate Router required. Service provider maps
 * these definitions onto Laravel routes when surfaces.http.enabled is true.
 */
final class RouteTable
{
    public const ROUTE_LIST = 'list';

    public const ROUTE_DESCRIBE = 'describe';

    public const ROUTE_INVOKE = 'invoke';

    public const ROUTE_APPROVAL_ACCEPT = 'approval_accept';

    public const ROUTE_APPROVAL_REJECT = 'approval_reject';

    public const ROUTE_HEALTH = 'health';

    public const ROUTE_AUTH_TOKEN = 'auth_token';

    public const ROUTE_AUTH_DEVICE = 'auth_device';

    public const ROUTE_AUTH_OAUTH_CALLBACK = 'oauth_callback';

    /**
     * Canonical action keys for the single CapabilityController tree.
     *
     * @return list<string>
     */
    public static function actionKeys(): array
    {
        return [
            self::ROUTE_LIST,
            self::ROUTE_DESCRIBE,
            self::ROUTE_INVOKE,
            self::ROUTE_APPROVAL_ACCEPT,
            self::ROUTE_APPROVAL_REJECT,
            self::ROUTE_HEALTH,
            self::ROUTE_AUTH_TOKEN,
            self::ROUTE_AUTH_DEVICE,
            self::ROUTE_AUTH_OAUTH_CALLBACK,
        ];
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     prefix?: string,
     *     middleware?: list<string>
     * }  $httpConfig  config('capabilities.surfaces.http')
     * @return list<array{
     *     key: string,
     *     method: string,
     *     uri: string,
     *     name: string,
     *     action: string,
     *     middleware: list<string>
     * }>
     */
    public static function routes(array $httpConfig = []): array
    {
        if (! ($httpConfig['enabled'] ?? true)) {
            return [];
        }

        $prefix = trim((string) ($httpConfig['prefix'] ?? 'capabilities'), '/');
        if ($prefix === '') {
            $prefix = 'capabilities';
        }

        $middleware = array_values(array_filter(
            (array) ($httpConfig['middleware'] ?? ['api']),
            static fn ($m) => is_string($m) && $m !== '',
        ));

        $defs = [
            [self::ROUTE_LIST, 'GET', $prefix, 'capabilities.list', 'CapabilityController@list'],
            [self::ROUTE_HEALTH, 'GET', $prefix.'/health', 'capabilities.health', 'CapabilityController@health'],
            [self::ROUTE_AUTH_TOKEN, 'POST', $prefix.'/auth/token', 'capabilities.auth.token', 'AuthController@token'],
            [self::ROUTE_AUTH_DEVICE, 'POST', $prefix.'/auth/device', 'capabilities.auth.device', 'AuthController@device'],
            [self::ROUTE_AUTH_OAUTH_CALLBACK, 'GET', $prefix.'/auth/callback', 'capabilities.auth.callback', 'AuthController@oauthCallback'],
            [self::ROUTE_APPROVAL_ACCEPT, 'POST', $prefix.'/approvals/{id}/accept', 'capabilities.approvals.accept', 'ApprovalController@accept'],
            [self::ROUTE_APPROVAL_REJECT, 'POST', $prefix.'/approvals/{id}/reject', 'capabilities.approvals.reject', 'ApprovalController@reject'],
            [self::ROUTE_DESCRIBE, 'GET', $prefix.'/{name}', 'capabilities.describe', 'CapabilityController@describe'],
            [self::ROUTE_INVOKE, 'POST', $prefix.'/{name}', 'capabilities.invoke', 'CapabilityController@invoke'],
        ];

        $routes = [];
        foreach ($defs as [$key, $method, $uri, $name, $action]) {
            // Auth issuance must not sit behind auth:sanctum (chicken-egg for CLI login) (L-002).
            $routeMiddleware = self::isAuthIssuanceRoute($key)
                ? self::withoutAuthMiddleware($middleware)
                : $middleware;

            $routes[] = [
                'key' => $key,
                'method' => $method,
                'uri' => $uri,
                'name' => $name,
                'action' => $action,
                'middleware' => $routeMiddleware,
            ];
        }

        return $routes;
    }

    /**
     * Auth token/device/oauth routes (issuance endpoints, not capability invoke).
     */
    public static function isAuthIssuanceRoute(string $key): bool
    {
        return in_array($key, [
            self::ROUTE_AUTH_TOKEN,
            self::ROUTE_AUTH_DEVICE,
            self::ROUTE_AUTH_OAUTH_CALLBACK,
        ], true);
    }

    /**
     * Strip Laravel auth middleware so token issuance is reachable before login.
     *
     * @param  list<string>  $middleware
     * @return list<string>
     */
    public static function withoutAuthMiddleware(array $middleware): array
    {
        return array_values(array_filter(
            $middleware,
            static function ($m) {
                if (! is_string($m) || $m === '') {
                    return false;
                }

                return $m !== 'auth' && ! str_starts_with($m, 'auth:');
            },
        ));
    }

    /**
     * @param  list<array{key: string, method?: string, uri?: string}>  $routes
     */
    public static function has(array $routes, string $key): bool
    {
        foreach ($routes as $route) {
            if (($route['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key: string, uri?: string, method?: string}>  $routes
     * @return array{key: string, method: string, uri: string, name: string, action: string, middleware: list<string>}|null
     */
    public static function find(array $routes, string $key): ?array
    {
        foreach ($routes as $route) {
            if (($route['key'] ?? null) === $key) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Absolute path under default prefix for assertions.
     */
    public static function pathFor(string $key, string $prefix = 'capabilities', ?string $name = null, ?string $id = null): string
    {
        $prefix = trim($prefix, '/');

        return match ($key) {
            self::ROUTE_LIST => '/'.$prefix,
            self::ROUTE_DESCRIBE => '/'.$prefix.'/'.($name ?? '{name}'),
            self::ROUTE_INVOKE => '/'.$prefix.'/'.($name ?? '{name}'),
            self::ROUTE_APPROVAL_ACCEPT => '/'.$prefix.'/approvals/'.($id ?? '{id}').'/accept',
            self::ROUTE_APPROVAL_REJECT => '/'.$prefix.'/approvals/'.($id ?? '{id}').'/reject',
            self::ROUTE_HEALTH => '/'.$prefix.'/health',
            self::ROUTE_AUTH_TOKEN => '/'.$prefix.'/auth/token',
            self::ROUTE_AUTH_DEVICE => '/'.$prefix.'/auth/device',
            self::ROUTE_AUTH_OAUTH_CALLBACK => '/'.$prefix.'/auth/callback',
            default => '/'.$prefix,
        };
    }
}
