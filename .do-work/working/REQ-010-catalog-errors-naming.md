# REQ-010: Catalog health errors naming deprecation

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T01:56:22Z
**Heartbeat:** 2026-07-27T01:56:22Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** Catalog list/health APIs and error envelopes from registry
**Terminal state:** Catalog/*, Errors/*, Naming/*, Profiles/* selection-related tests pass with stable error envelopes and catalog fields.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src packages/laravel-capabilities/tests/Unit/Catalog packages/laravel-capabilities/tests/Unit/Errors packages/laravel-capabilities/tests/Unit/Naming packages/laravel-capabilities/tests/Unit/Profiles packages/laravel-capabilities/tests/Unit/TestingHelpers
**Depends on:** REQ-005

## Task

Implement catalog listing/health, error envelope shapes and codes, deprecation lifecycle, agent/MCP profile composition (D-008 profiles/groups only), testing helpers. Flesh Catalog Errors Naming Profiles TestingHelpers inventory tests.

## Context

Do not dump full catalog into agent tools by default — profiles only. Error envelopes consistent across callers.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Catalog list field and health matrices pass
- [ ] Error envelope and code matrices pass for key stages
- [ ] Profile composition / max tools / meta tools / discoverability pass
- [ ] Deprecation lifecycle tests pass
- [ ] Testing helpers surface tests pass

## Verification Steps

1. **test** `composer test:core -- --filter=Catalog 2>&1 | tail -40`
   - Expected: Catalog tests pass
2. **test** `composer test:core -- --filter=Errors 2>&1 | tail -40`
   - Expected: Errors tests pass
3. **test** `composer test:core -- --filter=Profiles 2>&1 | tail -40`
   - Expected: Profiles tests pass
4. **test** `composer test:core -- --filter=Naming 2>&1 | tail -30`
   - Expected: Naming tests pass

## Integration

**Reachability:** HTTP catalog routes; agent/MCP tool list builders; registry error returns

**Data dependencies:** Registered capability definitions + profiles config

**Service dependencies:** CapabilityRegistry, config/capabilities.php

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
