# REQ-060: Embed version via ldflags

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.83526
**Claimed at:** 2026-07-27T20:36:53Z
**Heartbeat:** 2026-07-27T20:36:53Z
<!-- claimed-end -->

**UR:** UR-011
**Status:** in-progress
**Created:** 2026-07-28
**Layer:** cli
**Entry point:**
**Terminal state:**
**Parent:** REQ-059
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** S
**Files:** packages/capabilities-cli/cmd/capabilities/cli.go packages/capabilities-cli/cmd/capabilities/main_test.go packages/capabilities-cli/cmd/capabilities/commands_test.go packages/capabilities-cli/dist/README.md
**Depends on:**

## Task

Make `var Version` in the CLI overridable via Go `-ldflags` so release builds inject the git tag (strip leading `v`). Default remains a sensible dev string when not injected. Add unit tests that assert the default and document the ldflags pattern for GoReleaser.

## Context

Clarification: release builds inject the git tag into the binary version string. Today `packages/capabilities-cli/cmd/capabilities/cli.go` has `var Version = "0.2.0"`. CHANGELOG already notes binary embedding as a release step. GoReleaser will set ldflags in a later REQ.

## Acceptance Criteria

- [ ] `Version` is a package-level `var` (already) suitable for `-X main.Version=...` or the correct package path for the module
- [ ] `capabilities version` prints `capabilities <Version>` using that variable
- [ ] Unit test covers default Version non-empty and version command output contains Version
- [ ] `dist/README.md` (or build notes) documents the ldflags key used so GoReleaser can match it
- [ ] Default Version without ldflags still builds and tests pass (`go test ./...` under the CLI package)

## Verification Steps

1. **test** `cd packages/capabilities-cli && go test ./cmd/capabilities/ -count=1`
   - Expected: pass, including version-related tests
2. **runtime** `cd packages/capabilities-cli && go build -ldflags "-X main.Version=9.9.9-test" -o /tmp/capabilities-ver-test ./cmd/capabilities && /tmp/capabilities-ver-test version`
   - Expected: stdout contains `9.9.9-test` (adjust `-X` path if package path differs; document exact flag used)

## Integration

**Reachability:** User/CI runs `capabilities version` (`cmd/capabilities` `cmdVersion` path) or downloads a release binary built with ldflags from GoReleaser.

**Data dependencies:** Reads `Version` var in `packages/capabilities-cli/cmd/capabilities/cli.go`.

**Service dependencies:** None beyond the existing CLI command router in `cli.go` `Execute`.

## Assets
