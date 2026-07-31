# REQ-069: Random approval IDs and atomic claimLease

**UR:** UR-012
**Status:** backlog
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php, packages/laravel-capabilities/src/Persistence/QueryTableGateway.php, packages/laravel-capabilities/tests/Unit/**
**Depends on:**

## Task

Stop sequential process-local approval IDs in DatabaseApprovalStore (use random/UUID like gateway); make claimLease atomic (single conditional update, no TOCTOU) (L-005, L-007). Unit tests only.

## Context

Audit: multi-worker collisions on approval-{n}; claimLease is check-then-act under concurrency risking dual run() (D-006).

## Acceptance Criteria

- [ ] DatabaseApprovalStore no longer pre-assigns approval-{n} sequential IDs for durable puts
- [ ] New approval IDs are unguessable random (or gateway resolveId) across store instances
- [ ] claimLease fails second concurrent claim while lease held (atomic update condition)
- [ ] Unit tests cover uniqueness across two store instances and lease claim race
- [ ] No feature/DB tests; mock/fake gateways only

## Verification Steps

1. **test** `composer test:core -- --filter=Approval`
   - Expected: related approval store/lease tests pass
2. **runtime** `rg -n 'approval-\{|sequence|claimLease' packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php`
   - Expected: no sequential approval-{n} assignment path for durable ids

## Integration

**Reachability:** CapabilityRegistry approval pipeline → DatabaseApprovalStore (packages/laravel-capabilities/src/Persistence/DatabaseApprovalStore.php)
**Data dependencies:** approvals table rows via QueryTableGateway
**Service dependencies:** ApprovalStore contract; Clock for lease expiry
