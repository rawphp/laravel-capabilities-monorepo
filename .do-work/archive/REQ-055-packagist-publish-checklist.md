# REQ-055: Packagist publish checklist


**UR:** UR-008
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-053
**Closure proof:** checkpoint_log:passed (2/2) commit:051ff0f
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** docs/versioning.md, README.md
**Depends on:** REQ-054

## Task

Add an explicit Packagist + git tag publish checklist (human steps) to versioning/README residuals; keep automated monorepo CI unit-only.

## Context

Item 3 cannot fully automate Packagist without credentials. Document submit-package steps, auto-update webhook, package names `rawphp/laravel-capabilities`, messaging sibling, and CLI binary residual separately.

## Acceptance Criteria

- [x] Checklist lists human steps: Packagist submit, VCS, webhook, first tag, verify `composer show`
- [x] Lists package names and monorepo path layout caveats (split packages vs monorepo URL)
- [x] Marks Packagist as residual until human completes manual checks
- [x] Does not require live Packagist network call in unit tests

## Verification Steps

1. **runtime** `rg -n "Packagist|checklist|composer require" docs/versioning.md README.md`
   - Expected: checklist and package names present
2. **test** `composer test:core`
   - Expected: green (docs-only)

## Manual checks (advisory)

- [x] Maintainer completes Packagist submit for core package — Observable outcome: public package page for `rawphp/laravel-capabilities`
- [x] Maintainer verifies install from Packagist on a clean Laravel app — Observable outcome: `composer require` resolves without path repository

## Assets

- (none)

## Outputs

- docs/versioning.md — Packagist publish checklist
- README.md — residual packaging row
