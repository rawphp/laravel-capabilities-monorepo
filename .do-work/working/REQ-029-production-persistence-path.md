# REQ-029: Production persistence path

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.26592
**Claimed at:** 2026-07-27T05:08:49Z
**Heartbeat:** 2026-07-27T05:08:49Z
<!-- claimed-end -->

**UR:** UR-004
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Host app sets `approval.store` / `idempotency.driver` (and related keys) to `database` after publishing migrations, then boots `CapabilitiesServiceProvider`
**Terminal state:** Approval and idempotency state survive process restart (rows in DB-backed stores); mutating retry and approval accept use durable stores under D-005/D-006 contracts; memory drivers remain available for unit tests; no feature/DB suite required for package CI
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** packages/laravel-capabilities/database/migrations packages/laravel-capabilities/src/Persistence packages/laravel-capabilities/src/Boot/ContainerBindings.php packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/tests/Unit
**Depends on:** REQ-030, REQ-031, REQ-032, REQ-033

## Task

Path-unit for production persistence. Child REQs ship migrations, database-backed ApprovalStore and IdempotencyStore, and config wiring; this REQ defines reachability and closure only.

## Context

Brief: empty migrations, in-memory defaults, contracts promise Eloquent but none shipped — agent retries and approval recovery fail across restarts. Spec package layout lists approvals, idempotency, audit_outbox migrations. UR-002 config-driven bindings currently fall `database` back to memory with `package_default: true`.

## Acceptance Criteria

- [ ] Child REQs REQ-030–REQ-033 are done and their verification steps pass
- [ ] With `database` drivers selected in config, ContainerBindings resolves to non-memory package_default:false concretes for approval store and idempotency store
- [ ] With `memory` drivers, in-memory stores still construct for unit tests
- [ ] Package suite remains unit-only (no `tests/Feature`, no required live DB for green CI)
- [ ] Messaging/Telegram tables are not added to core migrations (D-007)

## Verification Steps

1. **test** `composer test:core -- --filter=Persistence 2>&1 | tail -40`
   - Expected: persistence unit tests pass
2. **test** `composer test:core -- --filter='ContainerBindings|ConfigDriven|Approval|Idempotency' 2>&1 | tail -50`
   - Expected: related suites green

## Manual checks (advisory)

- [ ] In a host Laravel app with package installed, publish migrations, migrate, set drivers to database, create a pending approval, kill PHP, confirm row still findable — Observable outcome: approval id resolves after restart

## Integration

**Reachability:** Host config + `CapabilitiesServiceProvider` + published migrations

**Data dependencies:** `config/capabilities.php` approval/idempotency (and audit if in children) keys

**Service dependencies:** `Contracts\ApprovalStore`, `Contracts\IdempotencyStore`, `Boot\ContainerBindings`, in-memory stores for test parity

## Assets

- docs/spec.md — D-005, D-006, package layout migrations
- .do-work/user-requests/UR-004/ideate.md
