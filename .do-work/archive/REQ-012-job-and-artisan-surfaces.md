# REQ-012: Job and Artisan adapter surfaces


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Queued job payload invoke + optional Artisan ops commands
**Terminal state:** Surfaces/Job* Job/* Artisan* unit tests pass with SystemActor; Artisan is ops-only not product CLI.
**Parent:** 
**Closure proof:** checkpoint_log:passed commit:8741381 Job:408 Artisan:23
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Adapters/JobSurface.php, packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCommandTable.php, packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCapabilityInvoker.php, packages/laravel-capabilities/src/Support/MissingArtisanActorException.php, packages/laravel-capabilities/src/Support/InvalidArtisanFlagsException.php, packages/laravel-capabilities/src/CapabilitiesServiceProvider.php, packages/laravel-capabilities/tests/Unit/Surfaces/JobAdapterTest.php, packages/laravel-capabilities/tests/Unit/Surfaces/ArtisanAdapterTest.php, packages/laravel-capabilities/tests/Unit/Surfaces/ArtisanFlagsTest.php
**Depends on:** REQ-006, REQ-010

## Task

Implement job adapter (payload contract, system allowlist, tenancy matrix) and optional Artisan adapter. Jobs never authorize as null user. Unit tests with mocks only.

## Context

D-002 SystemActor; product CLI is Go remote client not Artisan.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] Job adapter and Job/* matrices pass
- [x] Artisan adapter tests pass and do not claim product CLI role
- [x] Unauthorized/missing actor fails closed

## Verification Steps

1. **test** `composer test:core -- --filter=Job 2>&1 | tail -40`
   - Expected: Job tests pass
2. **test** `composer test:core -- --filter=Artisan 2>&1 | tail -30`
   - Expected: Artisan tests pass

## Integration

**Reachability:** Queue job classes + optional artisan commands registered by service provider

**Data dependencies:** Job payload contract fields

**Service dependencies:** CapabilityRegistry + SystemActor

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- Job + Artisan surfaces
