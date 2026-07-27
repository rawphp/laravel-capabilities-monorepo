# REQ-031: Database ApprovalStore

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.79622
**Claimed at:** 2026-07-27T05:04:27Z
**Heartbeat:** 2026-07-27T05:04:27Z
<!-- claimed-end -->

**UR:** UR-004
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-029
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/Persistence packages/laravel-capabilities/src/Support/InMemoryApprovalStore.php packages/laravel-capabilities/src/Contracts/ApprovalStore.php packages/laravel-capabilities/tests/Unit/Persistence packages/laravel-capabilities/tests/Unit/Approval
**Depends on:** REQ-030

## Task

Implement a production `ApprovalStore` backed by the approvals table (Eloquent model and/or query builder), implementing the full contract: put, find, update, compareAndUpdate, claimLease, findByStatus. Preserve exactly-once semantics of InMemoryApprovalStore under conditional updates and leases (D-006). Unit-test with mocked connection/query builder or injectable repository — no feature/DB suite.

## Context

`InMemoryApprovalStore` is the behavioural reference. Contracts already document Eloquent/DB for production. Approval crash recovery depends on durable rows + claimLease.

## Acceptance Criteria

- [ ] Class implements `Contracts\ApprovalStore` and lives under a clear Persistence (or Database) namespace
- [ ] put/find/update round-trip record shape compatible with ApprovalManager
- [ ] compareAndUpdate only mutates when status matches expected (no double-apply on race)
- [ ] claimLease respects free/expired lease and expected status (returns null when lease held)
- [ ] findByStatus returns matching rows
- [ ] Unit tests cover happy + race/mismatch + lease held paths with mocks/fakes (no live DB required)
- [ ] Does not break existing InMemoryApprovalStore tests

## Verification Steps

1. **test** `composer test:core -- --filter=DatabaseApproval 2>&1 | tail -40`
   - Expected: new store tests pass
2. **test** `composer test:core -- --filter=Approval 2>&1 | tail -50`
   - Expected: approval suite remains green

## Integration

**Reachability:** Bound when `approval.store=database` (REQ-033); ApprovalManager constructor injection

**Data dependencies:** approvals migration (REQ-030)

**Service dependencies:** `Contracts\ApprovalStore`, `Contracts\Clock`, `Approval\ApprovalManager`

## Assets

- packages/laravel-capabilities/src/Support/InMemoryApprovalStore.php
- docs/spec.md D-006
