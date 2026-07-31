<?php

namespace Rawphp\Capabilities\Adapters\Http;

use Rawphp\Capabilities\Contracts\AuthTokenIssuer;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Http\RouteTable;

/**
 * Token + device-code auth helpers shared by CLI & API (D-009 / L-002).
 *
 * Issues/accepts credentials used by the **same** capability HTTP API —
 * not a second invoke pipeline. Credential material comes only from a
 * host-bound {@see AuthTokenIssuer}; unbound → fail closed (not_configured).
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
        private readonly ?AuthTokenIssuer $issuer = null,
    ) {}

    /**
     * Whether token auth flow is available (HTTP on + real issuer bound).
     */
    public function tokenFlowAvailable(): bool
    {
        return $this->issuerBound() && $this->authFlowsEnabled();
    }

    public function deviceCodeFlowAvailable(): bool
    {
        return $this->issuerBound() && $this->authFlowsEnabled();
    }

    public function oauthCallbackFlowAvailable(): bool
    {
        return $this->issuerBound() && $this->authFlowsEnabled();
    }

    /**
     * Route keys this controller owns when HTTP is enabled and issuer is bound.
     *
     * @return list<string>
     */
    public function registeredFlows(): array
    {
        if (! $this->tokenFlowAvailable()) {
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
        if (! $this->authFlowsEnabled()) {
            return HttpResponse::failure('not_found', 'Auth token flow is not available.');
        }

        if (! $this->issuerBound()) {
            return HttpResponse::failure(
                'not_configured',
                'Auth token issuer is not configured. Bind AuthTokenIssuer in the host app.',
            );
        }

        $body = is_array($request->jsonBody) ? $request->jsonBody : [];
        // Host issuer is sole source of credentials — never echo client access_token.
        $data = $this->issuer->issueToken($request, $body);

        return HttpResponse::ok($data, meta: ['flow' => 'token']);
    }

    public function device(HttpRequestContext $request): HttpResponse
    {
        if (! $this->authFlowsEnabled()) {
            return HttpResponse::failure('not_found', 'Device-code flow is not available.');
        }

        if (! $this->issuerBound()) {
            return HttpResponse::failure(
                'not_configured',
                'Auth token issuer is not configured. Bind AuthTokenIssuer in the host app.',
            );
        }

        $body = is_array($request->jsonBody) ? $request->jsonBody : [];
        $data = $this->issuer->issueDeviceCode($request, $body);

        return HttpResponse::ok($data, meta: ['flow' => 'device_code']);
    }

    public function oauthCallback(HttpRequestContext $request): HttpResponse
    {
        if (! $this->authFlowsEnabled()) {
            return HttpResponse::failure('not_found', 'OAuth callback is not available.');
        }

        if (! $this->issuerBound()) {
            return HttpResponse::failure(
                'not_configured',
                'Auth token issuer is not configured. Bind AuthTokenIssuer in the host app.',
            );
        }

        $query = is_array($request->query) ? $request->query : [];
        $data = $this->issuer->handleOAuthCallback($request, $query);

        return HttpResponse::ok($data, meta: ['flow' => 'oauth_callback']);
    }

    private function issuerBound(): bool
    {
        return $this->issuer instanceof AuthTokenIssuer;
    }

    private function authFlowsEnabled(): bool
    {
        $httpOn = (bool) ($this->httpConfig['enabled'] ?? true);

        // Auth helpers exist when HTTP is on (CLI is a client of HTTP).
        // If HTTP is disabled, nothing is registered (D-009 fail closed).
        return $httpOn;
    }
}
