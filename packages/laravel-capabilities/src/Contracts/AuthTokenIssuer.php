<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Http\HttpRequestContext;

/**
 * Host-bound credential issuance for CLI/API auth helpers (L-002 / D-009).
 *
 * Core never invents or echoes tokens. When unbound, {@see AuthController}
 * fails closed with {@code not_configured} (HTTP 501).
 */
interface AuthTokenIssuer
{
    /**
     * Issue a host-defined credential. Must not trust client-supplied access_token as the issued value.
     *
     * @param  array<string, mixed>  $body  JSON body (grant_type, client_id, …)
     * @return array<string, mixed> wire data (typically token_type, access_token, expires_in)
     */
    public function issueToken(HttpRequestContext $request, array $body): array;

    /**
     * Start a device-code flow.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function issueDeviceCode(HttpRequestContext $request, array $body): array;

    /**
     * Complete OAuth authorization-code callback.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function handleOAuthCallback(HttpRequestContext $request, array $query): array;
}
