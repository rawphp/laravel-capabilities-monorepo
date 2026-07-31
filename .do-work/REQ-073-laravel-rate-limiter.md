# REQ-073: Shared Laravel cache rate limiter adapter

**UR:** UR-012
**Status:** backlog
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Support/**, packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/src/Contracts/RateLimiter.php, packages/laravel-capabilities/config/capabilities.php, packages/laravel-capabilities/tests/Unit/**
**Depends on:**

## Task

Bind a Laravel Cache/Redis RateLimiter adapter when rate_limits enabled; keep InMemoryRateLimiter for unit tests only (L-008). Config driver switch.

## Context

Config enables rate limits but makeRegistry always uses process-local InMemoryRateLimiter — false protection under multi-worker.

## Acceptance Criteria

- [ ] LaravelCacheRateLimiter (or equivalent) implements RateLimiter contract using Illuminate cache/rate limiter abstraction injectably
- [ ] ContainerBindings / makeRegistry selects adapter from config rate_limits.driver (memory|cache)
- [ ] Default for production-oriented config documented; tests still use memory
- [ ] Unit tests with fake cache store cover allow and deny after limit
- [ ] No live Redis required

## Verification Steps

1. **test** `composer test:core -- --filter=RateLimit`
   - Expected: rate limit unit tests pass including adapter if added
2. **runtime** `rg -n 'LaravelCacheRateLimiter|InMemoryRateLimiter|rate_limits' packages/laravel-capabilities/src packages/laravel-capabilities/config`
   - Expected: cache adapter exists; driver selection present

## Integration

**Reachability:** CapabilityRegistry stageRateLimit → RateLimiter
**Data dependencies:** rate_limits config keys
**Service dependencies:** RateLimiter contract; optional Illuminate Cache
