# REQ-022: Boot capability auto-discovery

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.52672
**Claimed at:** 2026-07-27T04:50:55Z
**Heartbeat:** 2026-07-27T04:50:55Z
<!-- claimed-end -->

**UR:** UR-002
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-020
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/src/Discovery/AttributeDiscoverer.php packages/laravel-capabilities/src/Discovery/DiscoveryPaths.php packages/laravel-capabilities/src/Registry/CapabilityRegistry.php packages/laravel-capabilities/config/capabilities.php packages/laravel-capabilities/tests/Unit/Discovery packages/laravel-capabilities/tests/Unit/Boot
**Depends on:** REQ-023

## Task

At package boot, auto-discover capability classes under the configured path (default `app/Capabilities` via `config('capabilities.path')`) using existing `AttributeDiscoverer` / D-017 rules and register them into the shared `CapabilityRegistry`. Missing directory is a no-op; duplicate names fail closed.

## Context

Brief: provider does not auto-discover `app/Capabilities`. Discovery already exists as pure code (`AttributeDiscoverer`, `DiscoveryPaths`, registry `discover` helpers) with unit fixtures (`tests/Fixtures/Outside/OutsideCapability.php`). Glue must call discovery once into the config-built registry (REQ-023).

## Acceptance Criteria

- [ ] Boot (or explicit provider method used by boot) runs discovery for configured path(s) into the singleton registry
- [ ] Default path follows `config/capabilities.php` `path` key (app Capabilities directory)
- [ ] Missing discovery path does not throw; yields zero new definitions
- [ ] Classes outside the path are not discovered (existing Outside fixture contract preserved)
- [ ] Duplicate capability names from discovery + fluent/attribute registration raise a clear boot/register exception (D-017 single map)
- [ ] Unit tests cover happy/missing-path/outside-path without feature tests or DB

## Verification Steps

1. **test** `composer test:core -- --filter=Discovery 2>&1 | tail -40`
   - Expected: discovery unit tests pass including boot-wiring coverage
2. **test** `composer test:core -- --filter=AttributeDiscoverer 2>&1 | tail -30`
   - Expected: path scoping and attribute rules still pass

## Integration

**Reachability:** `CapabilitiesServiceProvider::boot()` → `AttributeDiscoverer` / registry discovery

**Data dependencies:** `config('capabilities.path')`, PHP files under that path

**Service dependencies:** `Discovery\AttributeDiscoverer`, `Discovery\DiscoveryPaths`, `Registry\CapabilityRegistry` (config-built singleton from REQ-023)

## Assets

- docs/spec.md D-017 discovery
