# Ideate — UR-005

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **Production path is `ContainerBindings::makeRegistry` → `new CapabilityRegistry` with no clock** — Service provider singleton uses that factory; omitting `?Clock` leaves the FixedClock default live for every surface (HTTP, Artisan, jobs). Brief names the constructor; the boot path is the real consumer that must change or the fix is incomplete.
- **Tests may rely on the FixedClock constructor default** — Many unit tests construct `new CapabilityRegistry` without `withClock()`. Switching the default to SystemClock will make time-dependent asserts (idempotency TTL, approval expiry, deprecation windows) flaky unless tests inject FixedClock or SharedFakes explicitly.
- **Contract docs already say production uses SystemClock** — `Contracts\Clock` PHPDoc states tests → FixedClock, production → SystemClock. The registry constructor currently contradicts that contract; alignment is documentation truth, not a new design.

## Challenger — Risks & Edge Cases

- **Frozen calendar day breaks real TTLs and leases** — With default `2026-07-27T00:00:00Z`, idempotency keys never expire relative to wall clock, approval leases never age out, and audit timestamps all stamp the same instant. Any app that binds the singleton as-is will look "healthy" in demos and wrong in production ops.
- **Changing only the default without binding Clock in the container** — If someone does `new CapabilityRegistry` outside the provider (custom host bootstrap, test doubles that mirror production poorly), a SystemClock default is safer but still better if `Clock` is a first-class container abstract that `makeRegistry` resolves. Partial fix (constructor only) vs full fix (bind + inject) should be decided in capture.
- **Sibling components already default to SystemClock** — `ApprovalManager`, `IdempotencyGuard`, `IdempotencyStore`, `AuditOutbox` default to SystemClock; only the registry defaults to FixedClock. Inconsistency means mixed clocks if managers are constructed separately from the registry's internal copies — edge case if host rebinds stores but not registry clock.
- **Hard-coded date in source is a landmine for "today" logic** — Any future feature that compares `now()` to "today" or timezone-local dates will silently use 2026-07-27 forever under the default.

## Connector — Links & Reuse

- **`withClock()` / `clock()` already exist** on `CapabilityRegistry` — Prefer production inject + test `withClock(new FixedClock(...))` over growing a second factory path; reuse the existing mutator for tests that need determinism.
- **`ContainerBindings` already injects `SystemClock` into memory stores** (`makeIdempotencyStore`, `makeApprovalManager`) — Pattern to mirror: production factories pass SystemClock; tests pass FixedClock via SharedFakes / helpers.
- **`tests/Support/SharedFakes` defaults to FixedClock** — Test harness already owns deterministic time; production code should not.
- **UR-004 persistence work** (database approval/idempotency stores) will depend on real clock for `expires_at` / lease recovery — fixing this footgun before or with those drivers avoids false-green unit tests that pass only because time is frozen.

## Summary

The bug is real: production registry construction falls through to a FixedClock frozen at 2026-07-27, while contracts and sibling components expect SystemClock. The safe fix is change the constructor default to SystemClock (or require explicit clock), wire SystemClock through `ContainerBindings::makeRegistry` / provider, and make unit tests inject FixedClock where time matters. Capture should keep this as a small core-layer bugfix with explicit test updates for any suite that assumed the frozen default.
