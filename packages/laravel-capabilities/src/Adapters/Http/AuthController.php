<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Http\RouteTable;

/**
 * Token + device-code auth helpers shared by CLI & API (D-009).
 *
 * Issues/accepts credentials used by the **same** capability HTTP API —
 * not a second invoke pipeline.
 */
final class AuthController
{
    /**
     * @param  array{
     *     enabled?: bool,
     *     prefix?: string,
     *     middleware?: list<string>
     * }  $httpConfig  surfaces.http
     * @param  array{
     *     enabled?: bool
     * }  $cliConfig  surfaces.cli
     */
    public function __construct(
        private readonly array $httpConfig = ['enabled' => true],
        private readonly array $cliConfig = ['enabled' => true],
    ) {}

    /**
     * Whether token auth flow is available (registered) for CLI/API clients.
     */
    public function tokenFlowAvailable(): bool
    {
        return $this->authFlowsEnabled();
    }

    public function deviceCodeFlowAvailable(): bool
    {
        return $this->authFlowsEnabled();
    }

    public function oauthCallbackFlowAvailable(): bool
    {
        return $this->authFlowsEnabled();
    }

    /**
     * Route keys this controller owns when HTTP surface is enabled.
     *
     * @return list<string>
     */
    public function registeredFlows(): array
    {
        if (! $this->authFlowsEnabled()) {
            return [];
        }

        return [
            RouteTable::ROUTE_AUTH_TOKEN,
            RouteTable::ROUTE_AUTH_DEVICE,
            RouteTable::ROUTE_AUTH_OAUTH_CALLBACK,
        ];
    }

    public function token(HttpRequestContext $request): HttpResponse
    {
        if (! $this->tokenFlowAvailable()) {
            return HttpResponse::failure('not_found', 'Auth token flow is not available.');
        }

        // Host app wires real Sanctum/Passport issuance; package returns contract shape.
        $body = is_array($request->jsonBody) ? $request->jsonBody : [];

        return HttpResponse::ok([
            'token_type' => 'Bearer',
            'access_token' => $body['access_token'] ?? 'issued-by-host',
            'expires_in' => $body['expires_in'] ?? 3600,
        ], meta: ['flow' => 'token']);
    }

    public function device(HttpRequestContext $request): HttpResponse
    {
        if (! $this->deviceCodeFlowAvailable()) {
            return HttpResponse::failure('not_found', 'Device-code flow is not available.');
        }

        return HttpResponse::ok([
            'device_code' => 'device-code-placeholder',
            'user_code' => 'USER-CODE',
            'verification_uri' => '/capabilities/auth/device/verify',
            'expires_in' => 600,
            'interval' => 5,
        ], meta: ['flow' => 'device_code']);
    }

    public function oauthCallback(HttpRequestContext $request): HttpResponse
    {
        if (! $this->oauthCallbackFlowAvailable()) {
            return HttpResponse::failure('not_found', 'OAuth callback is not available.');
        }

        return HttpResponse::ok([
            'status' => 'received',
            'code' => $request->query['code'] ?? null,
        ], meta: ['flow' => 'oauth_callback']);
    }

    private function authFlowsEnabled(): bool
    {
        $httpOn = (bool) ($this->httpConfig['enabled'] ?? true);
        $cliOn = (bool) ($this->cliConfig['enabled'] ?? true);

        // Auth helpers exist when HTTP is on (CLI is a client of HTTP).
        // If HTTP is disabled, nothing is registered (D-009 fail closed).
        return $httpOn && ($httpOn || $cliOn);
    }
}
