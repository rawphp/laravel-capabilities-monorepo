# REQ-011: HTTP capability API surface

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T02:06:52Z
**Heartbeat:** 2026-07-27T02:06:52Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** HTTP routes for catalog + invoke (single API for CLI too, D-009)
**Terminal state:** Http/* and Surfaces/Http* unit tests pass; controllers map to registry only.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/src/Http packages/laravel-capabilities/routes packages/laravel-capabilities/tests/Unit/Http packages/laravel-capabilities/tests/Unit/Surfaces/HttpAdapterTest.php packages/laravel-capabilities/tests/Unit/Surfaces/HttpInvokeFailurePointsTest.php
**Depends on:** REQ-010

## Task

Implement HTTP adapters/controllers for catalog and invoke using the single capability HTTP API (D-009). Unit-test controllers with mocked registry/request DTOs — no feature tests, no full HTTP kernel DB boot. Caller derived server-side.

## Context

CLI and HTTP share one API. No second invoke tree for CLI.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Http CapabilityApi and Routes matrix unit tests pass
- [ ] HttpAdapter invoke maps to registry; failures map to error envelopes
- [ ] No dual mutation path outside registry
- [ ] Unit tests mock registry; no RefreshDatabase

## Verification Steps

1. **test** `composer test:core -- --filter=Http 2>&1 | tail -50`
   - Expected: Http unit tests pass
2. **test** `composer test:core -- --filter=HttpAdapter 2>&1 | tail -30`
   - Expected: Http adapter tests pass

## Integration

**Reachability:** packages/laravel-capabilities/routes + Http controllers; product CLI is remote client

**Data dependencies:** JSON request/response envelopes

**Service dependencies:** CapabilityRegistry

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
