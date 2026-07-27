# REQ-058: Fix subcommand --help flags

<!-- claimed-start -->
**Claimed by:** Toms-MacBook-Pro.local.57067
**Claimed at:** 2026-07-27T20:22:27Z
**Heartbeat:** 2026-07-27T20:22:27Z
<!-- claimed-end -->

**UR:** UR-010
**Status:** in-progress
**Created:** 2026-07-28
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** packages/capabilities-cli/cmd/capabilities/cli.go, packages/capabilities-cli/cmd/capabilities/cli_integration_test.go
**Depends on:**

## Task

Make top-level CLI subcommands print their existing `CommandHelp` text and exit 0 when the user passes `-h` or `--help` anywhere in the subcommand args, **before** auth, network, or side-effecting work.

Primary report: `capabilities mcp --help` currently does not show help (with a stored token it can exit 0 with empty stdout while starting/exiting the MCP stdio bridge). Extend the same early-exit pattern used by `cmdAuth` to `mcp`, and to the other top-level commands that ignore trailing help flags (`catalog`, `describe`, `run`, `approvals`) so behaviour is consistent.

Reuse `CommandHelp("<cmd>")` from `help.go` — do not invent a second help source.

## Context

User brief (UR-010): "the cli does not show help when requested (capabilities mcp --help)".

Root cause in `cmd/capabilities/cli.go`: `Execute` only treats `help`/`-h`/`--help` as the **first** arg. `cmdMcp` calls `GuardAuth` then `mcpstdio.Run` without scanning for help. `capabilities help mcp` already works.

Ideate Connector: mirror `cmdAuth`'s `case "help", "-h", "--help"` (or a small shared `wantsHelp(args)` helper). Unit-test via `CaptureExecute` — no live MCP server or network.

Scope decision (this UR): fix the shared footgun for all top-level subcommands missing help, not only mcp, in one small commit.

## Acceptance Criteria

- [ ] `CaptureExecute([]string{"mcp", "--help"}, …)` returns exit code 0, stdout contains mcp usage text from `CommandHelp("mcp")` (e.g. "MCP stdio" / "capabilities mcp"), and does **not** require a token or call auth/network
- [ ] `CaptureExecute([]string{"mcp", "-h"}, …)` behaves the same
- [ ] `CaptureExecute([]string{"mcp", "--profile=default", "--help"}, …)` still prints mcp help (help wins regardless of flag order)
- [ ] With a fake logged-in config root, `mcp --help` still prints help and does **not** start the MCP bridge (no empty success path)
- [ ] `catalog --help`, `describe --help`, `run --help`, and `approvals --help` print their respective `CommandHelp` and exit 0 without auth/network
- [ ] Existing `capabilities help mcp` / root `--help` behaviour remains unchanged
- [ ] `go test ./cmd/capabilities/` passes

## Verification Steps

1. **test** From `packages/capabilities-cli`, run `go test ./cmd/capabilities/ -count=1`
   - Expected: all tests pass, including new subcommand `--help` cases

2. **runtime** From `packages/capabilities-cli`, build and run:
   `go build -o /tmp/capabilities-ur010 ./cmd/capabilities && /tmp/capabilities-ur010 mcp --help`
   - Expected: exit 0; stdout includes mcp usage (e.g. "mcp — MCP stdio bridge" or "capabilities mcp"); stderr empty of auth errors

3. **runtime** `/tmp/capabilities-ur010 run --help` and `/tmp/capabilities-ur010 catalog --help`
   - Expected: each prints its command help and exits 0 without requiring login or network
