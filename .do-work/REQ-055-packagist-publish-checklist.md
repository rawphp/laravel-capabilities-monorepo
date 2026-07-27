# REQ-055: Packagist publish checklist

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-053
**Closure proof:**
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

- [ ] Checklist lists human steps: Packagist submit, VCS, webhook, first tag, verify `composer show`
- [ ] Lists package names and monorepo path layout caveats (split packages vs monorepo URL)
- [ ] Marks Packagist as residual until human completes manual checks
- [ ] Does not require live Packagist network call in unit tests

## Verification Steps

1. **runtime** `rg -n "Packagist|checklist|composer require" docs/versioning.md README.md`
   - Expected: checklist and package names present
2. **test** `composer test:core`
   - Expected: green (docs-only)

## Manual checks (advisory)

- [ ] Maintainer completes Packagist submit for core package — Observable outcome: public package page for `rawphp/laravel-capabilities`
- [ ] Maintainer verifies install from Packagist on a clean Laravel app — Observable outcome: `composer require` resolves without path repository

## Assets

- (none)
