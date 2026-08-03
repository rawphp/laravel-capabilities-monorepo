<?php

namespace Rawphp\Capabilities\Http;

use Illuminate\Http\Request;
use Rawphp\Capabilities\Adapters\Http\IlluminateApprovalController;
use Rawphp\Capabilities\Adapters\Http\IlluminateAuthController;
use Rawphp\Capabilities\Adapters\Http\IlluminateCapabilityController;

/**
 * Maps pure {@see RouteTable} definitions onto a Laravel-like router (REQ-021).
 *
 * Unit-testable: accepts any object with a {@see register()} method or array sink.
 * Service provider calls {@see registerInto()} when surfaces.http.enabled.
 */
final class HttpRouteRegistrar
{
    /**
     * Controller class map for action strings (CapabilityController@list → class).
     *
     * Points at Illuminate thin wrappers so Laravel method injection receives
     * {@see Request} and returns JsonResponse (L-001 / REQ-071).
     * Pure domain controllers remain for unit tests without the kernel.
     *
     * @var array<string, class-string>
     */
    public const CONTROLLERS = [
        'CapabilityController' => IlluminateCapabilityController::class,
        'AuthController' => IlluminateAuthController::class,
        'ApprovalController' => IlluminateApprovalController::class,
    ];

    /**
     * @param  array{enabled?: bool, prefix?: string, middleware?: list<string>}  $httpConfig
     * @return list<array{
     *     key: string,
     *     method: string,
     *     uri: string,
     *     name: string,
     *     action: string,
     *     uses: array{0: class-string, 1: string},
     *     middleware: list<string>
     * }>
     */
    public static function definitions(array $httpConfig = []): array
    {
        $routes = RouteTable::routes($httpConfig);
        $out = [];
        foreach ($routes as $route) {
            [$controller, $method] = self::parseAction((string) $route['action']);
            $out[] = [
                'key' => $route['key'],
                'method' => strtoupper((string) $route['method']),
                'uri' => (string) $route['uri'],
                'name' => (string) $route['name'],
                'action' => (string) $route['action'],
                'uses' => [$controller, $method],
                'middleware' => array_values((array) $route['middleware']),
            ];
        }

        return $out;
    }

    /**
     * Register routes into a sink. Sink may be:
     * - callable(array $route): void
     * - object with method addRoute(string $method, string $uri, array $action): void
     * - list accumulator (array by reference via ArrayRouteSink)
     *
     * @param  array{enabled?: bool, prefix?: string, middleware?: list<string>}  $httpConfig
     * @param  callable(array<string, mixed>): void|object  $sink
     * @return list<string> registered route keys
     */
    public static function registerInto(array $httpConfig, callable|object $sink): array
    {
        $defs = self::definitions($httpConfig);
        $keys = [];
        foreach ($defs as $def) {
            if (is_callable($sink) && ! is_object($sink)) {
                $sink($def);
            } elseif (is_object($sink) && method_exists($sink, 'addRoute')) {
                $sink->addRoute($def['method'], $def['uri'], [
                    'uses' => $def['uses'],
                    'as' => $def['name'],
                    'middleware' => $def['middleware'],
                    'key' => $def['key'],
                ]);
            } elseif (is_object($sink) && method_exists($sink, '__invoke')) {
                $sink($def);
            } else {
                throw new \InvalidArgumentException('Route sink must be callable or expose addRoute().');
            }
            $keys[] = $def['key'];
        }

        return $keys;
    }

    /**
     * @return array{0: class-string, 1: string}
     */
    public static function parseAction(string $action): array
    {
        if (! str_contains($action, '@')) {
            throw new \InvalidArgumentException("Invalid route action [{$action}]; expected Controller@method.");
        }
        [$short, $method] = explode('@', $action, 2);
        $class = self::CONTROLLERS[$short] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException("Unknown capability HTTP controller [{$short}].");
        }

        return [$class, $method];
    }

    /**
     * @param  array{enabled?: bool, prefix?: string, middleware?: list<string>}  $httpConfig
     * @return list<string>
     */
    public static function registeredKeys(array $httpConfig = []): array
    {
        return array_column(self::definitions($httpConfig), 'key');
    }
}
