# REQ-071: HTTP Illuminate Request/Response bridge

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.68388
**Claimed at:** 2026-07-31T08:45:19Z
**Heartbeat:** 2026-07-31T08:45:19Z
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
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Http/**, packages/laravel-capabilities/src/Adapters/Http/**, packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/tests/Unit/**
**Depends on:**

## Task

Ship Illuminate Request→HttpRequestContext and HttpResponse→Illuminate Response bridge so registered HTTP controllers work under Laravel kernel; map user/credentials from middleware, never client-claimed caller (L-001). Unit tests with request-like fixtures only.

## Context

Critical audit finding: controllers accept package DTOs Laravel cannot inject; default context is unauthenticated; non-Response returns break HTTP kernel.

## Acceptance Criteria

- [ ] Edge adapter maps Illuminate Request (or array fixture stand-in) to HttpRequestContext (user, headers, body, authKind/credential from server-derived auth)
- [ ] HttpResponse converts to Illuminate Response / Responsable with status, headers, JSON body
- [ ] Controllers used on RouteTable are invokable from Laravel-style resolution OR thin wrappers exist
- [ ] Unit tests cover mapping happy path + unauthenticated defaults; no Feature suite
- [ ] Client-claimed caller/tenant ignored per D-022

## Verification Steps

1. **test** `composer test:core -- --filter=Http`
   - Expected: HTTP adapter/bridge unit tests pass
2. **runtime** `rg -n 'fromIlluminate|toIlluminate|Responsable|IlluminateHttp' packages/laravel-capabilities/src`
   - Expected: bridge symbols exist and are referenced from service provider or controllers

## Integration

**Reachability:** CapabilitiesServiceProvider route registration → CapabilityController / AuthController
**Data dependencies:** HttpRequestContext, HttpResponse DTOs
**Service dependencies:** CapabilityRegistry catalog/invoke; HttpAuthGate
