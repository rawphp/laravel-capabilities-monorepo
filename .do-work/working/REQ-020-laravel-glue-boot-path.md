# REQ-020: Laravel glue boot path

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.76987
**Claimed at:** 2026-07-27T04:54:27Z
**Heartbeat:** 2026-07-27T04:54:27Z
<!-- claimed-end -->

**UR:** UR-002
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Composer package auto-discovery loads `Rawphp\Capabilities\CapabilitiesServiceProvider` into a host Laravel app (`packages/laravel-capabilities/composer.json` extra.laravel.providers)
**Terminal state:** With default-on surfaces, host boot registers the HTTP capability route tree from `RouteTable`, auto-discovers `app/Capabilities` (or config path) into the registry, constructs container bindings from full `config/capabilities.php` (surfaces, audit, approval, clients/drivers), and registers real Artisan ops commands from `ArtisanCommandTable`; disabled surfaces register zero artifacts (SURF-003)
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/routes/capabilities.php packages/laravel-capabilities/src/Boot packages/laravel-capabilities/tests/Unit/Boot
**Depends on:** REQ-021, REQ-022, REQ-023, REQ-024

## Task

Path-unit for complete Laravel package glue. Child REQs implement the four missing provider behaviours; this REQ defines reachability and closure only — no extra domain logic beyond what children ship.

## Context

Brief: thin service provider merges config and binds a few defaults but does not wire HTTP routes, discovery, full-config construction, or real Artisan commands. UR-001 already delivered pure tables (`RouteTable`, `ArtisanCommandTable`, `SurfaceRegistrar`, `RegistrationPlan`, `AttributeDiscoverer`). This path applies those tables inside the Laravel lifecycle without reimplementing domain.

## Acceptance Criteria

- [ ] Child REQs REQ-021–REQ-024 are done and their verification steps pass
- [ ] `CapabilitiesServiceProvider::registrationPlan()` / boot plan still reports routes, commands, and surfaces consistent with config flags (disabled surface ⇒ empty artifacts)
- [ ] No `tests/Feature` suite and no DB-required tests added for this path
- [ ] Messaging/Telegram routes are not registered from core provider (D-007)

## Verification Steps

1. **test** `composer test:core -- --filter=ServiceProvider 2>&1 | tail -50`
   - Expected: provider/boot glue unit tests pass (or filter matches provider-related Boot tests added by children)
2. **test** `composer test:core -- --filter='Boot|RouteTable|ArtisanCommand|Discovery' 2>&1 | tail -60`
   - Expected: related unit suites pass; no feature/DB failures required

## Manual checks (advisory)

- [ ] In a throwaway host Laravel app with the package path-required, `php artisan route:list` shows capability HTTP routes when `surfaces.http.enabled` is true, and none when false — Observable outcome: route names/URIs match `RouteTable` prefix
- [ ] Host app with a sample `app/Capabilities/*` class is listed by catalog/list after boot — Observable outcome: discovered capability name appears without manual register()

## Integration

**Reachability:** Laravel package auto-discovery → `CapabilitiesServiceProvider` (`packages/laravel-capabilities/composer.json` + `src/CapabilitiesServiceProvider.php`)

**Data dependencies:** `packages/laravel-capabilities/config/capabilities.php` surfaces/audit/approval/path keys

**Service dependencies:** Existing pure tables and registry — `Http\RouteTable`, `Adapters\Artisan\ArtisanCommandTable`, `Discovery\AttributeDiscoverer`, `Registry\CapabilityRegistry`, `Boot\RegistrationPlan`

## Assets

- docs/spec.md — package layout, D-009, D-017, SURF-003
- .do-work/user-requests/UR-002/ideate.md — glue vs pure-tables guidance
