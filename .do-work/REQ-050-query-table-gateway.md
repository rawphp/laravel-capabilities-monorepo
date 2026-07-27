# REQ-050: Illuminate query TableGateway

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-049
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Persistence/QueryTableGateway.php, packages/laravel-capabilities/src/Persistence/TableGateway.php, packages/laravel-capabilities/tests/Unit/Persistence/
**Depends on:**

## Task

Add a first-party `TableGateway` implementation backed by Illuminate Database query builder / connection (not Eloquent models required), unit-testable without a live external DB (in-memory SQLite or connection fake allowed only if monorepo policy permits pure unit; prefer injectable connection interface).

## Context

Interface: `packages/laravel-capabilities/src/Persistence/TableGateway.php`. Stores already use insert/find/replace/updateWhere/findWhere/upsert. Unit-only AGENTS.md: no RefreshDatabase feature suite; mock or sqlite `:memory:` if package already allows isolated connection in unit tests — prefer pure fake connection recording SQL if sqlite is discouraged. Check existing ProductionPersistencePathTest patterns.

## Acceptance Criteria

- [ ] `QueryTableGateway` (or equivalent name) implements full `TableGateway` contract for a named table
- [ ] Supports JSON/array column encoding consistent with `DatabaseApprovalStore` / `DatabaseIdempotencyStore` row shapes (or documents column map)
- [ ] `compareAndUpdate`-style flows via `updateWhere` remain atomic at gateway semantics level under unit fakes
- [ ] Unit tests cover insert, find, replace, updateWhere miss, findWhere, upsert without network MySQL/Postgres
- [ ] No dependency on messaging package
- [ ] Challenger: missing connection fails closed with clear exception, not silent ArrayTableGateway fallback inside this class

## Verification Steps

1. **test** `composer test:core -- --filter=QueryTableGateway`
   - Expected: new unit tests pass
2. **test** `composer test:core -- --filter=DatabaseApprovalStore`
   - Expected: existing store tests still pass with ArrayTableGateway
3. **test** `composer test:core`
   - Expected: suite green; package coverage still ≥95% for new code

## Integration

**Reachability:** constructed by `ContainerBindings` when database drivers selected (REQ-051) or manually `new QueryTableGateway($connection, 'capabilities_approvals')`.

**Data dependencies:** tables from migrations `capabilities_approvals`, `capabilities_idempotency` (MigrationCatalog names).

**Service dependencies:** `Illuminate\Database\ConnectionInterface` or package-local connection abstraction; `TableGateway` interface; used by `DatabaseApprovalStore` / `DatabaseIdempotencyStore`.
