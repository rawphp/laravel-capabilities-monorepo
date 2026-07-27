# REQ-054: Release prep metadata

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-053
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/laravel-capabilities/composer.json, packages/laravel-capabilities-messaging/composer.json, packages/laravel-capabilities/CHANGELOG.md, packages/laravel-capabilities-messaging/CHANGELOG.md, packages/capabilities-cli/CHANGELOG.md, docs/versioning.md
**Depends on:**

## Task

Align package versioning metadata and changelogs for a pre-stable 0.x tag: branch-alias, version notes, Unreleased → 0.x.y section scaffolding, and tag naming convention documented in versioning.md.

## Context

Packages already have CHANGELOGs and versioning.md. This REQ prepares for tagging without performing the remote tag push. Go CLI versioning noted in docs only (cli layer implementation out of scope).

## Acceptance Criteria

- [ ] Core (and messaging if present) composer.json branch-alias / version fields consistent with 0.x-dev policy in docs/versioning.md
- [ ] CHANGELOGs ready for a first 0.x.y section structure (Unreleased kept or split per Keep a Changelog)
- [ ] versioning.md documents exact tag name pattern (e.g. `v0.1.0` / package-specific tags if monorepo)
- [ ] No false claim that packages are already on Packagist
- [ ] Messaging/cli code not required to change beyond changelog/metadata if not needed

## Verification Steps

1. **runtime** `rg -n "branch-alias|0.x" packages/laravel-capabilities/composer.json packages/laravel-capabilities-messaging/composer.json docs/versioning.md`
   - Expected: consistent 0.x story
2. **runtime** `test -f packages/laravel-capabilities/CHANGELOG.md`
   - Expected: changelog exists and mentions pre-stable if required

## Assets

- (none)
