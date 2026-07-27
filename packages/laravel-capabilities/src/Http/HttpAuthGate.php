<?php

namespace Rawphp\Capabilities\Http;

/**
 * Auth policy for capability HTTP routes (unit-testable; middleware-compatible).
 *
 * Protected routes reject unauthenticated callers. Health may be opened via
 * config; default matches list/invoke (auth required).
 */
final class HttpAuthGate
{
    public const AUTH_NONE = 'none';

    public const AUTH_USER = 'user';

    public const AUTH_CLI_TOKEN = 'cli_token';

    public const AUTH_API_TOKEN = 'api_token';

    /**
     * Routes that always require authentication.
     *
     * @var list<string>
     */
    public const PROTECTED = [
        RouteTable::ROUTE_LIST,
        RouteTable::ROUTE_DESCRIBE,
        RouteTable::ROUTE_INVOKE,
        RouteTable::ROUTE_APPROVAL_ACCEPT,
        RouteTable::ROUTE_APPROVAL_REJECT,
        RouteTable::ROUTE_AUTH_TOKEN,
        RouteTable::ROUTE_AUTH_DEVICE,
    ];

    /**
     * @param  array{health_public?: bool}  $options
     */
    public function __construct(
        private readonly array $options = [],
    ) {}

    /**
     * Whether the request is allowed for the given route under an auth presentation.
     *
     * @param  value-of<self::AUTH_*>|'none'|'user'|'cli_token'|'api_token'  $authType
     */
    public function allows(string $routeKey, string $authType, HttpRequestContext $request): bool
    {
        if ($routeKey === RouteTable::ROUTE_HEALTH) {
            return $this->allowsHealth($authType, $request);
        }

        if ($authType === self::AUTH_NONE || ! $request->authenticated) {
            return false;
        }

        return match ($authType) {
            self::AUTH_USER, self::AUTH_CLI_TOKEN, self::AUTH_API_TOKEN => true,
            default => false,
        };
    }

    /**
     * Health auth policy — public only when explicitly configured.
     */
    public function allowsHealth(string $authType, HttpRequestContext $request): bool
    {
        $public = (bool) ($this->options['health_public'] ?? false);

        if ($public && $authType === self::AUTH_NONE) {
            return true;
        }

        if ($authType === self::AUTH_NONE || ! $request->authenticated) {
            return $public;
        }

        return in_array($authType, [self::AUTH_USER, self::AUTH_CLI_TOKEN, self::AUTH_API_TOKEN], true);
    }

    public function isProtected(string $routeKey): bool
    {
        if ($routeKey === RouteTable::ROUTE_HEALTH) {
            return ! (bool) ($this->options['health_public'] ?? false);
        }

        return in_array($routeKey, self::PROTECTED, true);
    }
}
