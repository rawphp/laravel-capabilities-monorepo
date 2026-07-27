# REQ-056: Update readiness residuals

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.40419
**Claimed at:** 2026-07-27T06:49:42Z
**Heartbeat:** 2026-07-27T06:49:42Z
<!-- claimed-end -->

**UR:** UR-008
**Status:** in-progress
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-053
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** README.md, docs/spec.md, packages/laravel-capabilities/README.md
**Depends on:** REQ-047, REQ-048, REQ-050, REQ-051, REQ-052, REQ-054, REQ-055

## Task

Update monorepo consumer readiness residuals and status banners to reflect closed gaps (config-wired registry, durable gateway path, release prep) and remaining Packagist human residual if still unpublished.

## Context

Root README Consumer readiness table is the honesty surface. After items 1–2 code and item 3 prep docs land, flip residual rows accurately — do not mark Packagist done without human publish.

## Acceptance Criteria

- [ ] Readiness table distinguishes: registry factory wired (done when code archived), durable gateway (done), Packagist (residual until human)
- [ ] Spec/README status lines still say pre-stable / not stable public API until tag+publish
- [ ] First-capability tutorial linked where relevant
- [ ] No marketing language claiming 8.5/10 adoption; residual-driven honesty only

## Verification Steps

1. **runtime** `rg -n "Consumer readiness|residual|makeRegistry|TableGateway|Packagist" README.md docs/spec.md packages/laravel-capabilities/README.md`
   - Expected: residuals match post-UR-008 reality
2. **test** `composer test:core`
   - Expected: green

## Assets

- (none)
