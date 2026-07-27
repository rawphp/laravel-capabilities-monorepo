# REQ-024: Register Artisan ops commands

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.65372
**Claimed at:** 2026-07-27T04:52:39Z
**Heartbeat:** 2026-07-27T04:52:39Z
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
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCommandTable.php packages/laravel-capabilities/src/Adapters/Artisan/ArtisanCapabilityInvoker.php packages/laravel-capabilities/tests/Unit/Surfaces packages/laravel-capabilities/tests/Unit/Boot
**Depends on:** REQ-023

## Task

Register real in-server Artisan ops commands from `ArtisanCommandTable` when `surfaces.artisan.enabled` is true (via `$this->commands(...)` or equivalent unit-testable registrar). Commands invoke the capability bus/registry — never a second mutation path. Keep role=ops / caller=artisan; do not confuse with product Go CLI.

## Context

Brief: provider registers no real Artisan commands beyond pure tables. `ArtisanCommandTable` and `ArtisanCapabilityInvoker` already define signatures and invoke semantics. Spec: Artisan is optional ops; product CLI is Go HTTP client (D-016).

## Acceptance Criteria

- [ ] When artisan surface enabled, provider registers every command class/signature from `ArtisanCommandTable::commands()`
- [ ] When artisan surface disabled, zero ops commands registered
- [ ] Registered commands use table signatures (e.g. `capability:run`) and ROLE remains `ops` (not product CLI)
- [ ] Invoke path goes through registry / invoker (same law as other surfaces) — no parallel domain `run()`
- [ ] Unit tests cover enabled/disabled registration lists and invoker contract without feature suite or DB
- [ ] Actor rules from existing Artisan adapter tests (acting-as / system) remain enforced

## Verification Steps

1. **test** `composer test:core -- --filter=Artisan 2>&1 | tail -40`
   - Expected: Artisan adapter + registration unit tests pass
2. **test** `composer test:core -- --filter=ServiceProvider 2>&1 | tail -30`
   - Expected: provider command-registration coverage passes when present

## Integration

**Reachability:** `CapabilitiesServiceProvider::boot()` when `runningInConsole` / artisan surface enabled → Artisan command registration

**Data dependencies:** `config('capabilities.surfaces.artisan')`

**Service dependencies:** `Adapters\Artisan\ArtisanCommandTable`, `ArtisanCapabilityInvoker`, `Registry\CapabilityRegistry` / bus from REQ-023

## Assets

- docs/spec.md — CLI vs Artisan distinction
