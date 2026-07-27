# REQ-044: Assert parity DX

**UR:** UR-007
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities/src/Registry/CapabilityRegistry.php packages/laravel-capabilities/src/Facades/Capability.php packages/laravel-capabilities/src/Support packages/laravel-capabilities/tests/Unit/TestingHelpers/ParityAndSnapshotsTest.php packages/laravel-capabilities/tests/Unit/TestingHelpers/HelperSurfaceTest.php packages/laravel-capabilities/tests/Fixtures
**Depends on:**

## Task

Implement real `assertParity` per D-020: given capability name, valid (or deny-triggering) input, listed surfaces, and optional assert callback — invoke each surface path through the registry/adapter unit paths with mocks/fakes and require the same success/deny **class** (not identical payload shape unless specified). Fail if one surface succeeds and another denies, or if a surface is unknown/disabled incorrectly. Match spec-shaped options (`input`, `surfaces`, `assert`) while remaining unit-testable (no HTTP feature suite, no live laravel/ai|mcp).

## Context

Spec D-020 example invokes http/registry/ai adapter paths with shared assert. Current `assertParity(?array $surfaces = null): bool` only validates non-empty surface strings and returns true. Empty-arg helpers and inventory greens over-state DX. Monorepo policy: adapter paths via mocks/fakes, not full request lifecycle. Prefer success/deny class comparison via `CapabilityResult` (reuse assertion exception style from result helpers).

## Acceptance Criteria

- [ ] `assertParity` accepts capability name + options array shaped like D-020 (`input`, `surfaces`, `assert` callback) — keep BC or document breaking change if empty-arg presence API is removed
- [ ] For each listed surface, invoke goes through a real registry surface path (e.g. `invokeForSurface` / equivalent) with provided input and controlled actor/tenant options
- [ ] Success class vs deny/failure class is compared across surfaces; mismatch throws with surface names and result classes
- [ ] Optional `assert` callback runs on successful results (or documented contract for deny-path parity)
- [ ] Unit tests cover: same success across ≥2 surfaces; same deny across ≥2 surfaces; mismatch fails; invalid surface list fails
- [ ] Presence-only `assertParity()` tests rewritten to exercise real behaviour (or dedicated “invalid usage” tests if empty call is rejected)
- [ ] No feature tests, no DB, no live peer packages required in default suite

## Verification Steps

1. **test** `composer test:core -- --filter=assertParity 2>&1 | tail -80`
   - Expected: assertParity behavioural unit tests pass
2. **test** `composer test:core -- --filter=TestingHelpers 2>&1 | tail -80`
   - Expected: TestingHelpers suite green
3. **runtime** `rg -n "function assertParity" -A 40 packages/laravel-capabilities/src/Registry/CapabilityRegistry.php | head -50`
   - Expected: body invokes surfaces / compares result classes (not only `return true` after surface name validation)

## Integration

**Reachability:** Consumer Pest/PHPUnit tests call `Capability::assertParity(...)` / registry method after registering the capability under test

**Data dependencies:** Registered capability definition; `CapabilityResult` success/failure codes

**Service dependencies:** `CapabilityRegistry::invoke` / surface invoke helpers; test harness fixtures (`CatalogHelpers` or equivalent)
