# REQ-043: Schema snapshot DX


**UR:** UR-007
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:** checkpoint_log:passed commit:1fe2a65
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Registry/CapabilityRegistry.php, packages/laravel-capabilities/src/Facades/Capability.php, packages/laravel-capabilities/src/Support/SchemaSnapshot.php, packages/laravel-capabilities/src/Support/SchemaSnapshotException.php, packages/laravel-capabilities/tests/Unit/TestingHelpers/ParityAndSnapshotsTest.php, packages/laravel-capabilities/tests/Unit/TestingHelpers/HelperSurfaceTest.php
**Depends on:**

## Task

Thicken `assertSchemaSnapshot` to full D-020 consumer DX under unit-only policy: lock **input and output** schemas, support durable snapshot files (package convention + optional path argument), fail loudly on drift without intentional snapshot update, keep optional in-memory expected arrays for simple unit cases. Replace presence-only / partial equality behaviour. Intentionally rewrite package unit tests that only assert method_exists or weak passes.

## Context

Spec D-020: `Capability::assertSchemaSnapshot('create-invoice')` locks input_schema + output_schema JSON; app CI should run snapshots before release. Current implementation compares optional in-memory input schema only and returns true. UR-001 decision: tests SOT after intentional updates — rewrite green-but-thin tests, do not game coverage. No feature tests / no DB.

## Acceptance Criteria

- [x] `assertSchemaSnapshot($name, …)` validates both input and output schema against the locked snapshot
- [x] Durable snapshot path supported (e.g. file path argument and/or conventional directory) so CI can fail when schema changes without updating the snapshot file
- [x] Drift throws a clear exception (or PHPUnit/Pest-compatible failure) naming capability and which schema side mismatched
- [x] Matching snapshot returns successfully (true or void without throw — document one contract and stick to it)
- [x] Facade `Capability::assertSchemaSnapshot` remains usable if facade already exposes it (or is wired if missing)
- [x] Unit tests cover: pass on match, fail on input drift, fail on output drift, fail/create path for missing snapshot when file mode used
- [x] Presence-only tests in HelperSurfaceTest / ParityAndSnapshotsTest that claimed full D-020 behaviour are replaced with behavioural asserts
- [x] No `tests/Feature`, no RefreshDatabase, no live HTTP app boot for this REQ

## Verification Steps

1. **test** `composer test:core -- --filter=assertSchemaSnapshot 2>&1 | tail -80`
   - Expected: all assertSchemaSnapshot-related unit tests pass
2. **test** `composer test:core -- --filter=TestingHelpers 2>&1 | tail -80`
   - Expected: TestingHelpers suite green after intentional rewrites
3. **runtime** `rg -n "function assertSchemaSnapshot" packages/laravel-capabilities/src -n && rg -n "outputSchema|output_schema|snapshot" packages/laravel-capabilities/src/Registry/CapabilityRegistry.php | head -40`
   - Expected: implementation references output schema and/or snapshot file path logic

## Integration

**Reachability:** App/package tests call `CapabilityRegistry::assertSchemaSnapshot` or `Facades\Capability::assertSchemaSnapshot` after capabilities are registered

**Data dependencies:** `CapabilityDefinition` input/output JSON Schema from registry catalog

**Service dependencies:** `CapabilityRegistry::get`, schema export on definition (`inputSchema` / output schema accessors)

## Outputs

- packages/laravel-capabilities/src/Support/SchemaSnapshot.php — Pure D-020 snapshot load/compare helpers
- packages/laravel-capabilities/src/Support/SchemaSnapshotException.php — Mismatch exception naming capability and side
- packages/laravel-capabilities/src/Registry/CapabilityRegistry.php — assertSchemaSnapshot full D-020
- packages/laravel-capabilities/src/Facades/Capability.php — Facade signature updated
- packages/laravel-capabilities/tests/Unit/TestingHelpers/ParityAndSnapshotsTest.php — Behavioural snapshot tests
- packages/laravel-capabilities/tests/Unit/TestingHelpers/HelperSurfaceTest.php — Presence-only tests rewritten
