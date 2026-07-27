# REQ-034: Registry production clock default


**UR:** UR-005
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:** checkpoint:.do-work/runs/RUN-032.yml#REQ-034 commit:d295f1c tests:passed
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** packages/laravel-capabilities/src/Registry/CapabilityRegistry.php packages/laravel-capabilities/src/Boot/ContainerBindings.php packages/laravel-capabilities/tests/Unit/CoverageGreen/CoverageBoostTest.php packages/laravel-capabilities/tests/Unit/Boot/ConfigDrivenBindingsTest.php packages/laravel-capabilities/tests/Unit/Boot/LaravelGlueBootPathTest.php packages/laravel-capabilities/tests/Unit/Registry
**Depends on:**

## Task

Stop `CapabilityRegistry` from defaulting to a frozen `FixedClock('2026-07-27…')`. Production and any bare `new CapabilityRegistry()` must use wall-clock time via `SystemClock`. Tests that need determinism inject `FixedClock` explicitly (constructor arg or `withClock()`). Wire `ContainerBindings::makeRegistry` to pass `SystemClock` so the service-provider singleton cannot ship frozen time.

## Context

Brief: registry constructor defaults to FixedClock — correct for some tests, a footgun if the Laravel singleton is used as-is. `Contracts\Clock` PHPDoc already says production uses SystemClock; siblings (`ApprovalManager`, `IdempotencyGuard`, memory stores via `ContainerBindings`) already default to SystemClock. Boot path: `CapabilitiesServiceProvider` → `ContainerBindings::makeRegistry` → `new CapabilityRegistry` with no clock today.

Connector: reuse existing `withClock()` / `clock()`; SharedFakes and *Helpers already own FixedClock for unit tests. Do not introduce a second factory path.

Out of scope: binding a separate `Clock` container abstract unless already required by other REQs; database store drivers (UR-004); changing FixedClock itself.

## Acceptance Criteria

- [x] `CapabilityRegistry` constructor null-clock default is `SystemClock` (not `FixedClock` with a hard-coded date)
- [x] Source no longer hard-codes `2026-07-27T00:00:00Z` (or any frozen calendar date) as the registry default clock
- [x] `ContainerBindings::makeRegistry` constructs the registry with an explicit `SystemClock` (or equivalent wall-clock implementation)
- [x] Unit test(s) assert production path: `makeRegistry(...)` / default construction → `clock()` is `SystemClock`
- [x] Unit test(s) assert tests can still freeze time: construct or `withClock(new FixedClock(...))` → `clock()` is that FixedClock
- [x] Any existing assert that expected the default to be `FixedClock` (e.g. CoverageBoostTest) is updated to the new contract
- [x] Time-sensitive registry/pipeline unit tests still pass deterministically (inject FixedClock where they rely on frozen time)
- [x] `composer test:core` passes

## Verification Steps

1. **test** `composer test:core` (or package Pest equivalent for registry/boot clock tests)
   - Expected: exit 0; suite green including updated default-clock asserts
2. **test** Filter focused: registry/boot tests that mention clock, e.g. `./vendor/bin/pest packages/laravel-capabilities/tests/Unit --filter=clock` or the specific files touched
   - Expected: pass; production default is SystemClock; FixedClock only when injected
3. **runtime** `rg -n "FixedClock\\(new \\\\DateTimeImmutable\\('2026-07-27" packages/laravel-capabilities/src` (or equivalent)
   - Expected: zero matches in `src/` (frozen date default removed from production code)

## Manual checks (advisory)

(none — fully unit-testable)

## Outputs

- packages/laravel-capabilities/src/Registry/CapabilityRegistry.php — Null clock default is SystemClock
- packages/laravel-capabilities/src/Boot/ContainerBindings.php — makeRegistry passes SystemClock
- packages/laravel-capabilities/tests/Unit/Registry/RegistrySystemClockDefaultTest.php — production default + FixedClock inject tests
- packages/laravel-capabilities/tests/Unit/Boot/ConfigDrivenBindingsTest.php — makeRegistry asserts SystemClock
