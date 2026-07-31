# REQ-068: CLI release go test and pin Go version

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.22569
**Claimed at:** 2026-07-31T08:52:52Z
**Heartbeat:** 2026-07-31T08:52:52Z
<!-- claimed-end -->

**UR:** UR-012
**Status:** in-progress
**Created:** 2026-07-31
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** packages/capabilities-cli/.github/workflows/release.yml, packages/capabilities-cli/go.mod
**Depends on:**

## Task

Run `go test ./...` before GoReleaser in the package-owned release workflow, and pin setup-go to go.mod (X-003, X-012).

## Context

CLI release builds multi-arch binaries without tests; uses go-version: stable while module is go 1.22.

## Acceptance Criteria

- [ ] release.yml runs `go test ./...` (or equivalent) before GoReleaser; non-zero test fails the job
- [ ] setup-go uses `go-version-file: go.mod` (or explicit 1.22.x matching go.mod), not floating `stable` alone

## Verification Steps

1. **runtime** `grep -nE 'go test|go-version|GoReleaser|goreleaser' packages/capabilities-cli/.github/workflows/release.yml`
   - Expected: go test appears before goreleaser; go-version-file or pinned version present
2. **test** `cd packages/capabilities-cli && go test ./...`
   - Expected: suite passes (baseline still green)

## Integration

Omit — CI only.
