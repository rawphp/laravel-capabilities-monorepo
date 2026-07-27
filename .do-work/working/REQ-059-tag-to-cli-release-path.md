# REQ-059: Tag-to-CLI release path

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.83526
**Claimed at:** 2026-07-27T21:03:10Z
**Heartbeat:** 2026-07-27T21:03:10Z
<!-- claimed-end -->

**UR:** UR-011
**Status:** in-progress
**Created:** 2026-07-28
**Layer:** none
**Entry point:** Maintainer pushes monorepo git tag matching `v*` (split workflow mirrors the tag to `rawphp/capabilities-cli`)
**Terminal state:** `rawphp/capabilities-cli` has a GitHub Release for that tag with multi-arch `capabilities` binaries (platform signing applied when secrets are present); `capabilities version` on a release asset matches the tag (without leading `v`)
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/capabilities-cli/.goreleaser.yml packages/capabilities-cli/.github/workflows/release.yml packages/capabilities-cli/cmd/capabilities/cli.go docs/versioning.md packages/capabilities-cli/README.md packages/capabilities-cli/dist/README.md
**Depends on:** REQ-060, REQ-061, REQ-062, REQ-063, REQ-064

## Task

Define and close the end-to-end path: monorepo `v*` tag → existing split mirrors tag to `rawphp/capabilities-cli` → package-owned GoReleaser workflow builds multi-arch binaries, optionally signs when secrets exist, and creates/updates the GitHub Release on that child repo. This path-unit owns closure semantics; children implement version embed, goreleaser, workflow, signing scaffold, and docs.

## Context

Brief: when a monorepo tag is pushed and split to child repos, the CLI must be built and a release created on the child. Clarifications: package-owned workflow on mirrored `v*` tags; GoReleaser; full platform signing secret-gated; replace release on retag; ldflags version. Existing split is `.github/workflows/split-packages.yml` (do not re-implement). Residual track in `docs/versioning.md` § CLI binary.

## Acceptance Criteria

- [ ] Path entry and terminal are documented in this REQ and satisfied by child REQs (no monorepo job that creates CLI releases on PHP package remotes)
- [ ] Child REQs cover version embed, GoReleaser config, tag-triggered release workflow (update-on-retag), secret-gated platform signing, and docs/residual updates
- [ ] No second mutation path: release automation lives under `packages/capabilities-cli` so it is self-contained after split

## Verification Steps

1. **test** Confirm all child REQ files for UR-011 exist in backlog/archive with `**UR:** UR-011` and parent/depends links consistent with this path.
   - Expected: REQ-060..064 present; this REQ lists them under Depends on; children reference Parent REQ-059 where applicable
2. **runtime** `test -f packages/capabilities-cli/.goreleaser.yml && test -f packages/capabilities-cli/.github/workflows/release.yml`
   - Expected: both files exist after children land (path terminal prerequisites on disk)

## Manual checks (advisory)

- [ ] After children land: push a monorepo tag `v0.x.y` (or dry-run on a test tag) and confirm split mirrors it, then child workflow produces/updates a GitHub Release with assets — Observable outcome: release page on `rawphp/capabilities-cli` lists darwin/linux/windows amd64+arm64 binaries; version string matches tag when downloaded and run where possible

## Assets
