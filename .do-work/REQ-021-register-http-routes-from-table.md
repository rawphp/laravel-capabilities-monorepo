# REQ-021: Register HTTP routes from RouteTable

**UR:** UR-002
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-020
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/routes/capabilities.php packages/laravel-capabilities/src/Http/RouteTable.php packages/laravel-capabilities/src/Boot/SurfaceRegistrar.php packages/laravel-capabilities/tests/Unit/Http packages/laravel-capabilities/tests/Unit/Boot
**Depends on:**

## Task

Wire the capability HTTP tree into Laravel’s route lifecycle from pure `RouteTable` definitions when `surfaces.http.enabled` is true; register nothing when disabled. Prefer a single source of truth (`RouteTable`) — either `loadRoutesFrom` a real routes file that maps the table, or provider-side registration from the table (not a hand-duplicated URI list).

## Context

Brief: routes exist as `RouteTable` (and `routes/capabilities.php` currently returns table data, not Illuminate `Route::` registrations). Provider does not `loadRoutesFrom`. D-009: one HTTP API tree for catalog/invoke/auth/approvals; CLI is a remote client of these routes.

Reuse: `RouteTable::routes()`, `SurfaceRegistrar` HTTP artifacts, existing HTTP controllers under `Adapters/Http`.

## Acceptance Criteria

- [ ] When `surfaces.http.enabled` is true, provider boot registers every route key from `RouteTable::actionKeys()` with method/uri/name/middleware matching the table
- [ ] When `surfaces.http.enabled` is false, zero capability HTTP routes are registered
- [ ] Registration uses `RouteTable` as the sole definition of paths/actions (no second hand-copied URI matrix)
- [ ] Unit tests cover enabled/disabled mapping without a full HTTP feature suite or real DB (fake/router spy or pure registrar)
- [ ] Messaging/Telegram routes are not part of this tree (D-007)

## Verification Steps

1. **test** `composer test:core -- --filter=RouteTable 2>&1 | tail -40`
   - Expected: RouteTable + registration unit tests pass
2. **test** `composer test:core -- --filter='Http|ServiceProvider|SurfaceRegistrar' 2>&1 | tail -50`
   - Expected: related unit tests pass; disabled surface registers empty route list

## Integration

**Reachability:** `CapabilitiesServiceProvider::boot()` → Laravel router (or package route registrar) using `Http\RouteTable`

**Data dependencies:** `config('capabilities.surfaces.http')` prefix/middleware/enabled

**Service dependencies:** `Http\RouteTable`, `Adapters\Http\CapabilityController`, `ApprovalController`, `AuthController`, `Boot\SurfaceRegistrar`

## Assets

- packages/laravel-capabilities/routes/capabilities.php — existing table-backed stub
