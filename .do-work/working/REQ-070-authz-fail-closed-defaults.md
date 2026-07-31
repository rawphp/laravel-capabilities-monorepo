# REQ-070: Fail-closed authorize and any_staff defaults

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.68388
**Claimed at:** 2026-07-31T08:45:19Z
**Heartbeat:** 2026-07-31T08:45:19Z
<!-- claimed-end -->

**UR:** UR-012
**Status:** in-progress
**Created:** 2026-07-31
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/Registry/CapabilityRegistry.php, packages/laravel-capabilities/src/Boot/ContainerBindings.php, packages/laravel-capabilities/src/Approval/ApprovalPolicy.php, packages/laravel-capabilities/config/capabilities.php, packages/laravel-capabilities/tests/Unit/**
**Depends on:**

## Task

Default registry authorizer to deny (not allow-all) when capability has no authorize(); require explicit host/withAuthorizer for allow; ApprovalPolicy any_staff without staffChecker denies; align idempotency default with durable approvals (L-003, L-013, L-009).

## Context

Governance claims fail open: StubAuthorizer::allow() default; any_staff treats every non-system user as staff; idempotency defaults memory while approvals default database.

## Acceptance Criteria

- [ ] CapabilityRegistry construction / makeRegistry defaults to deny when no per-capability authorize and no host authorizer override
- [ ] Existing tests that relied on allow-all are updated to set explicit allow authorizer or authorize callable
- [ ] ApprovalPolicy any_staff without staffChecker returns false for ordinary users
- [ ] Idempotency default driver is database when approval store is database (or production boot warns when memory under production — prefer aligned config defaults)
- [ ] Unit tests cover deny-missing-authorize and any_staff-without-checker

## Verification Steps

1. **test** `composer test:core`
   - Expected: full core suite green after default change
2. **runtime** `rg -n 'StubAuthorizer::allow|any_staff|idempotency' packages/laravel-capabilities/src/Registry/CapabilityRegistry.php packages/laravel-capabilities/src/Approval/ApprovalPolicy.php packages/laravel-capabilities/config/capabilities.php`
   - Expected: default is deny path; any_staff fail-closed; idempotency default not memory-vs-db mismatch

## Integration

**Reachability:** makeRegistry / CapabilityRegistry invoke pipeline authorize stage
**Data dependencies:** capability definitions authorize callables; approval policy config
**Service dependencies:** Authorizer contract, ApprovalPolicy, config/capabilities.php
