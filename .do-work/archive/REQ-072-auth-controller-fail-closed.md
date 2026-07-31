# REQ-072: AuthController fail-closed without real issuer


**UR:** UR-012
**Status:** done
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** AuthTokenIssuer fail-closed; no placeholder tokens. filter=Auth 492 passed. commit:90d6cff
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Adapters/Http/AuthController.php, packages/laravel-capabilities/src/Http/RouteTable.php, packages/laravel-capabilities/src/Contracts/AuthTokenIssuer.php, packages/laravel-capabilities/src/Support/ErrorCodeMap.php, packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/tests/Unit/Http/AuthTokenIssuerFailClosedTest.php, packages/laravel-capabilities/tests/Unit/Http/AuthControllerMatrixTest.php, packages/laravel-capabilities/tests/Unit/Http/CapabilityApiTest.php, packages/laravel-capabilities/tests/Unit/Http/IlluminateHttpBridgeTest.php, packages/laravel-capabilities/tests/Unit/Http/MiddlewareMatrixTest.php, packages/laravel-capabilities/tests/Unit/Surfaces/HttpAdapterTest.php, packages/laravel-capabilities/tests/Fixtures/HttpHelpers.php
**Depends on:** REQ-071

## Task

Stop issuing placeholder tokens from client body. Auth token/device/oauth endpoints fail closed (501/not_configured or not_found) unless host binds a real AuthTokenIssuer (L-002). Split middleware so token issuance is not behind sanctum if still registered.

## Context

AuthController echoes access_token or 'issued-by-host'; device returns placeholders. Footgun on product HTTP API.

## Acceptance Criteria

- [x] Without bound issuer, token/device/oauth endpoints return not available / not configured (no fake bearer)
- [x] Never accept client-supplied access_token as issued credential
- [x] When issuer interface is bound, token flow uses it (interface + stub for unit tests)
- [x] Unit tests cover unbound fail-closed and bound happy path with fake issuer
- [x] Routes/middleware do not imply real auth when unbound

## Verification Steps

1. **test** `composer test:core -- --filter=Auth`
   - Expected: AuthController unit tests pass including fail-closed
2. **runtime** `rg -n "issued-by-host|device-code-placeholder|access_token" packages/laravel-capabilities/src/Adapters/Http/AuthController.php`
   - Expected: no placeholder issuance path in production code path

## Integration

**Reachability:** RouteTable auth routes → AuthController
**Data dependencies:** token request body (wire only)
**Service dependencies:** optional AuthTokenIssuer binding; HttpResponse

## Outputs

- packages/laravel-capabilities/src/Contracts/AuthTokenIssuer.php
- packages/laravel-capabilities/src/Adapters/Http/AuthController.php
