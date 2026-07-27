# REQ-062: Package release workflow on tag

**UR:** UR-011
**Status:** backlog
**Created:** 2026-07-28
**Layer:** cli
**Entry point:**
**Terminal state:**
**Parent:** REQ-059
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** packages/capabilities-cli/.github/workflows/release.yml packages/capabilities-cli/README.md .github/workflows/split-packages.yml
**Depends on:** REQ-061

## Task

Add `packages/capabilities-cli/.github/workflows/release.yml` that runs on push of tags `v*`, checks out the package tree, installs Go + GoReleaser, and creates or **updates** the GitHub Release for that tag with built assets (clarification: replace on retag). Use permissions appropriate for `contents: write`. Document that monorepo split must already have mirrored the tag (existing `split-packages.yml`); do not build PHP packages here. Optionally add a one-line pointer in monorepo split workflow comments that CLI binary release is package-owned.

## Context

Package-owned workflow after split mirrors `v*` to `rawphp/capabilities-cli`. Split force-tags; release job must update/replace existing GitHub Release for the same tag rather than fail or skip. `SPLIT_GITHUB_TOKEN` is monorepo-only; child workflow uses `GITHUB_TOKEN` (or documented PAT if needed for force-update).

## Acceptance Criteria

- [ ] Workflow file lives under `packages/capabilities-cli/.github/workflows/` so it appears at repo root after split
- [ ] Triggers on `push` tags `v*` (and optionally `workflow_dispatch` for maintainers)
- [ ] Runs GoReleaser release (not monorepo path-based publish of PHP trees)
- [ ] Configured to update/replace an existing release for the same tag (e.g. goreleaser `release.mode: replace` / equivalent flags)
- [ ] Minimal permissions documented (`contents: write`); no monorepo secrets required in the child workflow for unsigned publish
- [ ] README (CLI package) mentions automated GitHub Releases on `v*` tags after monorepo split

## Verification Steps

1. **runtime** `test -f packages/capabilities-cli/.github/workflows/release.yml && rg -n "tags:|goreleaser|v\\*|contents:" packages/capabilities-cli/.github/workflows/release.yml`
   - Expected: tag trigger, goreleaser step, contents write permission present
2. **runtime** Validate YAML parses: `python3 -c "import yaml; yaml.safe_load(open('packages/capabilities-cli/.github/workflows/release.yml'))"`
   - Expected: exits 0
3. **test** `cd packages/capabilities-cli && go test ./... -count=1`
   - Expected: still green

## Manual checks (advisory)

- [ ] On a real or dry-run tag after merge: confirm Actions run on `rawphp/capabilities-cli` and Release assets appear — Observable outcome: GitHub Release for the tag lists multi-arch binaries; re-push of same tag updates assets without a permanent failed job

## Integration

**Reachability:** GitHub Actions on `rawphp/capabilities-cli` when monorepo split force-pushes `refs/tags/v*` (`.github/workflows/split-packages.yml` tag branch).

**Data dependencies:** Checked-out package source at the tag; `.goreleaser.yml` (REQ-061); `GITHUB_TOKEN` for release API.

**Service dependencies:** GoReleaser action or install; GitHub Releases API; does not call Laravel or monorepo HTTP APIs.

## Assets
