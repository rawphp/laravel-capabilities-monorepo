# REQ-041: Package versioning and changelogs


**UR:** UR-007
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:** checkpoint_log:passed (3/3 runtime) commit:0207fa6
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/composer.json packages/laravel-capabilities/CHANGELOG.md packages/laravel-capabilities-messaging/composer.json packages/laravel-capabilities-messaging/CHANGELOG.md packages/capabilities-cli/go.mod packages/capabilities-cli/CHANGELOG.md docs/versioning.md README.md
**Depends on:**

## Task

Establish monorepo packaging readiness without requiring live Packagist publish: per-package CHANGELOG (Keep a Changelog style or project-consistent), documented version policy (0.x pre-stable caveats, branch-alias or version field guidance), and README/docs pointer for how consumers install from path/VCS today. Do not push to Packagist or create git tags unless already project practice — this REQ is documentation + in-repo versioning artifacts only.

## Context

Brief: packaging and release notes missing. Ideate: packaging = monorepo readiness + versioning docs, not network publish. Packages already have composer.json / go.mod; no package CHANGELOGs at capture time. Messaging/cli CHANGELOG files may be minimal stubs if those packages are thin — still create consistent surfaces.

## Acceptance Criteria

- [x] `packages/laravel-capabilities/CHANGELOG.md` exists with at least an Unreleased or 0.x section describing current monorepo capability surface at a high level
- [x] Messaging and CLI packages have CHANGELOG.md stubs or initial entries so release-notes path is consistent across monorepo packages
- [x] `docs/versioning.md` (or equivalent section linked from README) states: 0.x pre-stable expectations, install-from-path/VCS, that Packagist publish is not claimed until done, and where CHANGELOGs live
- [x] Core `composer.json` remains valid JSON; any version/branch-alias addition is intentional and documented in versioning doc
- [x] Root README links to versioning doc and/or CHANGELOGs
- [x] No secrets, tokens, or live publish steps are introduced

## Verification Steps

1. **runtime** `test -f packages/laravel-capabilities/CHANGELOG.md && test -f packages/laravel-capabilities-messaging/CHANGELOG.md && test -f packages/capabilities-cli/CHANGELOG.md && test -f docs/versioning.md`
   - Expected: all four files exist
2. **runtime** `php -r 'json_decode(file_get_contents("packages/laravel-capabilities/composer.json")); echo json_last_error()===0?"ok\n":"bad\n";'`
   - Expected: `ok`
3. **runtime** `rg -n "CHANGELOG|versioning|0\\.x|path" README.md docs/versioning.md | head -30`
   - Expected: consumer-facing pointers present

## Outputs

- packages/laravel-capabilities/CHANGELOG.md — Core Keep a Changelog with Unreleased + 0.x monorepo surface notes
- packages/laravel-capabilities-messaging/CHANGELOG.md — Messaging package CHANGELOG stub
- packages/capabilities-cli/CHANGELOG.md — Go CLI CHANGELOG stub
- docs/versioning.md — 0.x policy, path/VCS install, Packagist honesty
- packages/laravel-capabilities/composer.json — branch-alias dev-main → 0.x-dev
- packages/laravel-capabilities-messaging/composer.json — branch-alias dev-main → 0.x-dev
- README.md — Links versioning doc and CHANGELOGs
