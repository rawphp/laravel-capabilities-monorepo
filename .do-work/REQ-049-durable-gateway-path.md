# REQ-049: Durable TableGateway path

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Host enables `approval.store=database` and/or `idempotency.driver=database`, publishes/runs package migrations
**Terminal state:** Package provides a first-party Illuminate query/DB TableGateway wired into database drivers by default (ArrayTableGateway remains unit-test default); docs document optional 10-line host override — unit tests prove gateway contract without a live MySQL/Postgres
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/src/Persistence/, packages/laravel-capabilities/src/Boot/ContainerBindings.php, docs/tutorials/first-capability.md, docs/versioning.md
**Depends on:** REQ-050, REQ-051, REQ-052

## Task

Path-unit for UR-008 item 2: first-party durable TableGateway + host binding documentation.

## Context

UR-004 shipped `Database*Store` + migrations but default gateway is `ArrayTableGateway` even for `database` driver. Closing the adoption residual requires a real Illuminate-backed gateway or an honest required host binding — brief prefers first-party Eloquent/query.

## Acceptance Criteria

- [ ] Children REQ-050–052 done
- [ ] Database driver path no longer silently defaults to process-local ArrayTableGateway when a connection is available in Laravel container (unit-tested via fake connection/query)
- [ ] Host override documented and still unit-safe

## Verification Steps

1. **test** `composer test:core -- --filter=TableGateway`
   - Expected: gateway tests pass
2. **test** `composer test:core -- --filter=Persistence`
   - Expected: persistence suite green
3. **test** `composer test:core`
   - Expected: full core green

## Manual checks (advisory)

- [ ] In a real Laravel app with SQLite/MySQL, publish migrations, set database drivers, invoke mutating capability twice with same idempotency key — Observable outcome: second invoke replays stored outcome from DB after process restart

## Assets

- (none)
