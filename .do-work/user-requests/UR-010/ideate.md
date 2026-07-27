# Ideate — UR-010

**Reviewed:** 2026-07-28

## Explorer — Assumptions & Perspectives

- **Assumption: only `mcp` is broken.** The brief cites `capabilities mcp --help`, but `Execute` only treats `help`/`-h`/`--help` as a *first* argument. `cmdMcp` never short-circuits on `--help` and proceeds to auth + stdio bridge. Concrete scenario: a user who just installed the binary types the Unix-standard `capabilities mcp --help` and gets silence (exit 0 when already logged in, empty stdin ends the MCP loop) instead of usage text. Brief trigger: the example invocation.
- **Stakeholders beyond the human terminal user:** shell completion authors, README copy that shows `capabilities help mcp` only, MCP client install docs that may suggest `mcp --help`, and any agent that shells out with `--help` to discover flags. Fog: whether product docs promise subcommand `--help` for all commands or only root/`help mcp`.
- **Success criteria are underspecified.** Brief says "does not show help" — does not state exit code, stdout vs stderr, or whether `-h` must match. Concrete problem: a fix that prints help to stderr with non-zero exit would still "show" text but break scripts that treat exit 0 as success.

## Challenger — Risks & Edge Cases

- **Same bug class on other subcommands.** `cmdCatalog` / `cmdDescribe` / `cmdRun` / `cmdApprovals` also ignore trailing `--help` and hit auth or validation instead. Scenario: `capabilities run --help` today errors with "missing input" (after auth) rather than run help — fixing only `mcp` leaves inconsistent CLI UX. Trigger: pattern in `cli.go` after the top-level switch.
- **Flag order and mixed flags.** Users may pass `mcp --profile=x --help` or `mcp --help --profile=x`. If the check only looks at `args[0] == "--help"`, the first form still fails. Scenario: profile-specific help attempts hang or start the bridge.
- **Auth-before-help is a footgun.** `cmdMcp` calls `GuardAuth` before any help path. Scenario: brand-new user with no token never sees MCP usage; they see an auth error instead of "how do I start the bridge". Help must be free of credentials.
- **Stdio bridge false-success.** When logged in, `mcp --help` can exit 0 with no output (server Run on closed stdin). Scenario: CI smoke `capabilities mcp --help` passes green while help is broken — worst kind of regression.

## Connector — Links & Reuse

- **Existing help content:** `CommandHelp("mcp")` and `RootHelp()` already exist in `cmd/capabilities/help.go`; `capabilities help mcp` works. Fix is routing, not writing new copy.
- **Existing pattern:** `cmdAuth` already handles `case "help", "-h", "--help"`. Reuse that shape (or a small shared `wantsHelp(args)` + early return) across subcommands for consistency.
- **Tests:** `help_test.go` / `commands_test.go` assert `CommandHelp("mcp")` text but not `Execute([]string{"mcp", "--help"})`. New unit test via `CaptureExecute` belongs next to those. CLI layer only (`layers: [core, messaging, cli]` — this is pure `cli`).
- **Standing decisions:** no prior decision locks help UX; monorepo unit-only + mock boundaries still apply (no live MCP server needed).

## Summary

This is a CLI bug-fix: `mcp --help` never reaches `CommandHelp` and can silently succeed. The high-value fix is early help handling on the mcp command (no auth), with unit coverage for `Execute`. Strongly consider the same early-exit for other top-level commands that share the gap — either in one REQ or a narrow mcp-only REQ if you want minimal scope.
