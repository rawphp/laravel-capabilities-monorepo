<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Http\HttpRequestContext;

/**
 * Host-bound credential issuance for CLI/API auth helpers (L-002 / D-009).
 *
 * Core never invents or echoes tokens. When unbound, {@see AuthController}
 * fails closed with {@code not_configured} (HTTP 501).
 *
 * ## Device-code poll contract (RFC 8628 over the ok envelope — C-001)
 *
 * The product CLI (`capabilities auth login --base-url=…`) drives this flow:
 *
 * 1. `POST {prefix}/auth/device` `{"client_id":"capabilities-cli"}` → {@see issueDeviceCode()}
 *    returns `device_code`, `user_code`, `verification_uri`, `expires_in`, `interval`.
 * 2. Every `interval` seconds (the CLI floors it at **10 s** because the auth routes are
 *    throttled `6,1` — keep `interval >= 10` unless `surfaces.http.auth_middleware` loosens
 *    that) the CLI `POST`s `{prefix}/auth/token` with
 *    `{"grant_type": GRANT_DEVICE_CODE, "device_code": …, "client_id": "capabilities-cli"}`
 *    → {@see issueToken()}.
 * 3. While the user has not decided, the issuer returns **inside the `ok: true` envelope**
 *    (`AuthController` wraps whatever it returns) `['status' => 'authorization_pending']`
 *    (or RFC-style `['error' => 'authorization_pending']`). `slow_down` asks the CLI to add
 *    5 s to its interval; `access_denied` and `expired_token` end the flow. See
 *    {@see DEVICE_POLL_STATUSES}.
 * 4. Once approved, return the token shape (`access_token`, `token_type`, `expires_in`).
 *    Mint that token — and any PAT a user pastes into `capabilities auth login --token` — with
 *    the ability mapped to caller `cli` in `clients.token_abilities` (default `capabilities:cli`,
 *    e.g. `$user->createToken('cli', ['capabilities:cli'])->plainTextToken`). Core derives the
 *    caller from that ability only (D-022); a token without it is an `http` caller, so
 *    capabilities exposed on `cli` but not `http` vanish from the CLI catalog and `run` answers
 *    `not_found` (C-103).
 *
 * Any other response without `access_token` makes the CLI fail closed.
 */
interface AuthTokenIssuer
{
    /** RFC 8628 grant the CLI polls the token route with. */
    public const GRANT_DEVICE_CODE = 'urn:ietf:params:oauth:grant-type:device_code';

    /**
     * Pending / terminal poll statuses the CLI understands in `data.status` (or `data.error`).
     *
     * @var list<string>
     */
    public const DEVICE_POLL_STATUSES = ['authorization_pending', 'slow_down', 'access_denied', 'expired_token'];

    /**
     * Issue a host-defined credential. Must not trust client-supplied access_token as the issued value.
     *
     * For `grant_type = GRANT_DEVICE_CODE` return either the token shape or one of
     * {@see DEVICE_POLL_STATUSES} as `['status' => …]` (see the class docblock).
     *
     * @param  array<string, mixed>  $body  JSON body (grant_type, client_id, device_code, …)
     * @return array<string, mixed> wire data (typically token_type, access_token, expires_in)
     */
    public function issueToken(HttpRequestContext $request, array $body): array;

    /**
     * Start a device-code flow.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed> device_code, user_code, verification_uri, expires_in, interval (>= 10)
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
