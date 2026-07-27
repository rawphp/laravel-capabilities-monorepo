# REQ-007: Approval state machine and resume

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.49422
**Claimed at:** 2026-07-27T01:23:48Z
**Heartbeat:** 2026-07-27T01:41:31Z
<!-- claimed-end -->

**UR:** UR-001
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:** needs_approval stage + approve/deny/resume APIs
**Terminal state:** Pending approvals do not call run(); approve path executes exactly once with crash recovery; Approval/* inventory tests pass.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Approval packages/laravel-capabilities/src/Contracts/ApprovalStore.php packages/laravel-capabilities/src/Support/InMemoryApprovalStore.php packages/laravel-capabilities/src/Events/CapabilityApprovalDecided.php packages/laravel-capabilities/src/Events/CapabilityApprovalExecuted.php packages/laravel-capabilities/tests/Unit/Approval packages/laravel-capabilities/tests/Fixtures/ApprovalHelpers.php packages/laravel-capabilities/tests/bootstrap.php packages/laravel-capabilities/phpunit.xml packages/laravel-capabilities/tests/Pest.php
**Depends on:** REQ-005

## Task

Implement approval SM (D-006): pending storage, who may approve, TTL/expiry, staleness revalidation, exactly-once execution, resume lease, notifiers interface, crash recovery. Replace Approval/* todos with real unit tests using in-memory store from REQ-002.

## Context

Approvals are not fire-and-forget. Single execution + crash recovery.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] needsApproval true stores pending and does not call run
- [x] Exactly-once algorithm and resume/lease matrix scenarios pass
- [x] Crash recovery scenarios pass
- [x] Who-may-approve / expiry / staleness matrices pass
- [x] No DB in tests

- [x] Double-execute after approval and crash-recovery races never call run() twice (assert single execution from inventory scenarios)

## Verification Steps

1. **test** `composer test:core -- --filter=Approval 2>&1 | tail -60`
   - Expected: Approval unit tests pass

## Integration

**Reachability:** Registry needs_approval stage; HTTP/CLI approval decision endpoints if defined; messaging notifiers later

**Data dependencies:** Approval rows in ApprovalStore

**Service dependencies:** CapabilityRegistry, notifiers contract, audit

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
