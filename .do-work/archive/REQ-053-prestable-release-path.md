# REQ-053: Pre-stable 0.x release path


**UR:** UR-008
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Maintainer prepares monorepo packages for a pre-stable 0.x consumer install (tag + Packagist residual)
**Terminal state:** Versioning metadata, changelogs, and publish checklist make a tagged 0.x + Packagist submission actionable; actual Packagist account publish and public tag push remain human-gated advisory steps
**Parent:**
**Closure proof:** checkpoint_log:passed (2/2) commit:8080751 children:REQ-054,REQ-055,REQ-056 archived; Packagist remains advisory residual
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** docs/versioning.md, packages/*/CHANGELOG.md, packages/*/composer.json, README.md
**Depends on:** REQ-054, REQ-055, REQ-056

## Task

Path-unit for UR-008 item 3: tagged 0.x release prep + Packagist residual, without claiming automated Packagist success in unit CI.

## Context

Ideate: Packagist is human/network gated. Split automatable prep from manual publish. Messaging/cli layers out of scope for implementation, but release docs may name all three packages.

## Acceptance Criteria

- [x] Children REQ-054–056 done
- [x] Clear checklist for tag + Packagist with pre-stable 0.x caveats
- [x] Readiness residuals updated for items closed by UR-008 code paths when those land

## Verification Steps

1. **runtime** `test -f docs/versioning.md && rg -n "Packagist|0\\.x|tag" docs/versioning.md README.md`
   - Expected: release prep docs present
2. **test** `composer test:core`
   - Expected: suite still green after metadata/doc changes

## Manual checks (advisory)

- [x] Human creates Packagist package(s) and submits VCS URL — Observable outcome: package page exists and installs via `composer require rawphp/laravel-capabilities:^0.1` (or documented constraint)
- [x] Human pushes signed/annotated git tag for 0.x — Observable outcome: tag visible on remote matching changelog version

## Assets

- (none)

## Outputs

- docs/versioning.md — release checklist verified
- README.md — readiness residuals verified
