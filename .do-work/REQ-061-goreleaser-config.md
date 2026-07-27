# REQ-061: GoReleaser multi-arch config

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
**Files:** packages/capabilities-cli/.goreleaser.yml packages/capabilities-cli/dist/README.md packages/capabilities-cli/CHANGELOG.md
**Depends on:** REQ-060

## Task

Add a package-root `.goreleaser.yml` for `rawphp/capabilities-cli` that builds the `capabilities` binary for the matrix in `dist/README.md` (darwin/linux/windows × amd64/arm64), injects version via ldflags from the tag (strip `v` prefix), produces archives/checksums suitable for a GitHub Release, and leaves hooks/placeholders for secret-gated signing (implemented in REQ-063). Do not publish from monorepo PHP packages.

## Context

Clarifications: GoReleaser; multi-arch matrix from dist/README; version ldflags from tag. Package lives at `packages/capabilities-cli` and is mirrored self-contained to the child repo. `dist/README.md` already names goreleaser as the intended artifact path.

## Acceptance Criteria

- [ ] `packages/capabilities-cli/.goreleaser.yml` exists and builds `./cmd/capabilities` as binary name `capabilities`
- [ ] Targets include at least: darwin/amd64, darwin/arm64, linux/amd64, linux/arm64, windows/amd64, windows/arm64
- [ ] Version ldflags match REQ-060’s documented `-X` path; tag `v1.2.3` → version `1.2.3`
- [ ] Checksums (e.g. `checksums.txt` or goreleaser default) are produced
- [ ] Config is valid for goreleaser v2-style schema used in CI (document version pin in comments or workflow)
- [ ] No hard dependency on signing secrets in the base config (signing is secret-gated in REQ-063)

## Verification Steps

1. **build** If `goreleaser` is available: `cd packages/capabilities-cli && goreleaser check` (or `goreleaser release --snapshot --clean --skip=publish` with a reasonable timeout)
   - Expected: config validates / snapshot builds without requiring GitHub token publish
2. **runtime** `test -f packages/capabilities-cli/.goreleaser.yml && rg -n "darwin|linux|windows|ldflags|capabilities" packages/capabilities-cli/.goreleaser.yml`
   - Expected: matrix platforms and version ldflags present
3. **test** `cd packages/capabilities-cli && go test ./... -count=1`
   - Expected: suite still green (no regression from version package path)

## Integration

**Reachability:** Triggered by the package-owned release workflow (REQ-062) on `v*` tag push to `rawphp/capabilities-cli` after monorepo split.

**Data dependencies:** Tag name / git metadata for version; builds from `cmd/capabilities` using `Version` var (REQ-060).

**Service dependencies:** GoReleaser CLI in CI; GitHub Releases API via workflow (REQ-062).

## Assets
