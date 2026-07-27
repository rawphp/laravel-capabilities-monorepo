# REQ-007: Approval state machine and resume

**UR:** UR-001
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:** needs_approval stage + approve/deny/resume APIs
**Terminal state:** Pending approvals do not call run(); approve path executes exactly once with crash recovery; Approval/* inventory tests pass.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Approval packages/laravel-capabilities/tests/Unit/Approval
**Depends on:** REQ-005

## Task

Implement approval SM (D-006): pending storage, who may approve, TTL/expiry, staleness revalidation, exactly-once execution, resume lease, notifiers interface, crash recovery. Replace Approval/* todos with real unit tests using in-memory store from REQ-002.

## Context

Approvals are not fire-and-forget. Single execution + crash recovery.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] needsApproval true stores pending and does not call run
- [ ] Exactly-once algorithm and resume/lease matrix scenarios pass
- [ ] Crash recovery scenarios pass
- [ ] Who-may-approve / expiry / staleness matrices pass
- [ ] No DB in tests

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
