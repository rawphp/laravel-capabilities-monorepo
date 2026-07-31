# REQ-072: AuthController fail-closed without real issuer

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.22569
**Claimed at:** 2026-07-31T08:52:53Z
**Heartbeat:** 2026-07-31T08:52:53Z
<!-- claimed-end -->

**UR:** UR-012
**Status:** in-progress
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Adapters/Http/AuthController.php, packages/laravel-capabilities/src/Http/RouteTable.php, packages/laravel-capabilities/src/Contracts/**, packages/laravel-capabilities/tests/Unit/**
**Depends on:** REQ-071

## Task

Stop issuing placeholder tokens from client body. Auth token/device/oauth endpoints fail closed (501/not_configured or not_found) unless host binds a real AuthTokenIssuer (L-002). Split middleware so token issuance is not behind sanctum if still registered.

## Context

AuthController echoes access_token or 'issued-by-host'; device returns placeholders. Footgun on product HTTP API.

## Acceptance Criteria

- [ ] Without bound issuer, token/device/oauth endpoints return not available / not configured (no fake bearer)
- [ ] Never accept client-supplied access_token as issued credential
- [ ] When issuer interface is bound, token flow uses it (interface + stub for unit tests)
- [ ] Unit tests cover unbound fail-closed and bound happy path with fake issuer
- [ ] Routes/middleware do not imply real auth when unbound

## Verification Steps

1. **test** `composer test:core -- --filter=Auth`
   - Expected: AuthController unit tests pass including fail-closed
2. **runtime** `rg -n "issued-by-host|device-code-placeholder|access_token" packages/laravel-capabilities/src/Adapters/Http/AuthController.php`
   - Expected: no placeholder issuance path in production code path

## Integration

**Reachability:** RouteTable auth routes → AuthController
**Data dependencies:** token request body (wire only)
**Service dependencies:** optional AuthTokenIssuer binding; HttpResponse
