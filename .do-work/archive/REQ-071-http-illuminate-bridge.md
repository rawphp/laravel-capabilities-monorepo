# REQ-071: HTTP Illuminate Request/Response bridge


**UR:** UR-012
**Status:** done
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** IlluminateHttpBridge + thin wrappers; filter=Http 719 passed (L-001). commit:34f77b0
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

- [x] Edge adapter maps Illuminate Request (or array fixture stand-in) to HttpRequestContext (user, headers, body, authKind/credential from server-derived auth)
- [x] HttpResponse converts to Illuminate Response / Responsable with status, headers, JSON body
- [x] Controllers used on RouteTable are invokable from Laravel-style resolution OR thin wrappers exist
- [x] Unit tests cover mapping happy path + unauthenticated defaults; no Feature suite
- [x] Client-claimed caller/tenant ignored per D-022

## Verification Steps

1. **test** `composer test:core -- --filter=Http`
   - Expected: HTTP adapter/bridge unit tests pass
2. **runtime** `rg -n 'fromIlluminate|toIlluminate|Responsable|IlluminateHttp' packages/laravel-capabilities/src`
   - Expected: bridge symbols exist and are referenced from service provider or controllers

## Integration

**Reachability:** CapabilitiesServiceProvider route registration → CapabilityController / AuthController
**Data dependencies:** HttpRequestContext, HttpResponse DTOs
**Service dependencies:** CapabilityRegistry catalog/invoke; HttpAuthGate

## Outputs

- packages/laravel-capabilities/src/Http/IlluminateHttpBridge.php
- packages/laravel-capabilities/src/Adapters/Http/IlluminateCapabilityController.php
- packages/laravel-capabilities/tests/Unit/Http/IlluminateHttpBridgeTest.php
