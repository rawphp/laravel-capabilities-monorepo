# Changelog

All notable changes to `rawphp/capabilities-cli` (Go binary `capabilities`) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Added

- **`auth status --json`** — D-018 envelope with `profile`, `base_url`, `logged_in` (never the token).
- **`auth list` / `auth profiles`** — list stored profiles (name, base_url, logged_in; never tokens); `--json` envelope.
- **`catalog --include-schemas`** — list/JSON with `input_schema` / `output_schema` in one round-trip for agents.
- **`run <name> --help`** — schema-first capability help (fields + pass mode), same idea as domain/verb `--help`.
- **Leading global flags** — `--profile`, `--base-url`, and presentation flags (`--json`, `--human`,
  `--flat`, `--include-schemas`, `--no-cache`, `--refresh`) may appear before the subcommand
  (e.g. `capabilities --profile=P --json catalog`).
- **Typo hints** on unknown domain/command (e.g. `catalg` → `did you mean: catalog`).

### Changed (0.x agent/script contract)

- **Unauthenticated domain/unknown argv** — exit **3** with `not authenticated` (was exit **5**
  “unknown domain”, which hid the need to login). Authenticated unknown domain remains exit **5**.
- **Domain catalog load uses active `--profile`** (no longer always loads with `default`).

- **Root command exit code** — bare `capabilities` (no subcommand) prints usage and
  exits **0** (was exit **2** / `validation_failed`). Update scripts that treated a
  bare invoke as failure. **Help/usage paths are success.**
- **`approvals` without action** — prints usage and exits **0** (was exit **2**); does
  **not** require auth. **`approvals accept|reject` without `<id>`** — clear error exit
  **2** (no silent full help).
- **MCP `tools/call` errors** — `error.data` uses D-018 **wire keys** (`code`, `message`,
  `violations`, `http_status`, `cli_exit`, `approval_id`, `retryable`, `request_id`) —
  not Go field names (`Code`, `HTTPStatus`). Raw HTTP body is **not** embedded. Agents
  must parse snake_case keys only.
- **`run --human` stderr** — short one-line summary (e.g. `ok get_today_meals date=…`).
  **Breaking for anyone parsing `--human` stderr for full `data=` JSON** — machine path
  remains stdout envelope only; do not parse human stderr.
- **`describe` not-found** — machine error envelope on **stdout** (parity with domain
  not_found) plus short stderr line.
- **MCP stdio bridge** — `tools/list` requests catalog with `include_schemas=1`; tools
  always include a non-null `inputSchema` object (empty object when the server omits
  schema). MCP `notifications/*` (e.g. `initialized`) are ignored without a JSON-RPC
  error reply.
- **`auth login|logout|status --help`** — help wins before flag requirements or logout side effects (was requiring `--base-url` / logging out).
- **Local validation stderr** — includes field summary, e.g. `local schema validation failed (date: is required)`.
- **Local `ValidateLocal` string formats** — portable JSON Schema `format` checks
  run fail-closed **before network** (exit **2**): `date`, `date-time`/`datetime`,
  `time`, `email`, `uri`/`url`, `uuid`. Field-level stderr messages (e.g.
  `invalid date format (expected YYYY-MM-DD)`). Local validation is type, required,
  structure, **and** this format subset — not type/required only. Unknown formats
  are not enforced locally. The subset may false-reject values the server would
  accept; the server still re-validates (D-004).
- **HTTP error messages** — non-JSON/HTML API error bodies are summarized in the
  user-facing message instead of dumping full HTML (raw body still available on the
  structured error).
- **Login profile safety** — failed device/browser login no longer overwrites an
  existing profile `base_url`; empty-token PAT login no longer writes `base_url` before
  reject.
- **Internal split** — CLI command handlers moved from monolithic `cmd/capabilities/cli.go`
  into focused files (`cmd_auth.go`, `cmd_catalog.go`, `cmd_domain.go`, `cmd_run.go`,
  `cmd_mcp.go`).

### Added (docs / install / release path)

- Complete user documentation set: `docs/README.md` index, expanded
  `docs/user-guide.md`, `docs/authentication.md` (multi-project **profiles**),
  `docs/agents.md` (envelopes, exit codes, MCP). README links Install + docs.
- User-global install: `scripts/install.sh` + README / user-guide one-liner
  (`curl … | bash`) installs the latest (or `VERSION=`) GitHub Release binary into
  `~/.local/bin` (override with `CAPABILITIES_INSTALL_DIR`); no sudo.
- Downloadable Go product CLI: auth, catalog, local JSON Schema validation (UX only),
  invoke via the server’s single HTTP capability API, optional MCP stdio bridge (D-016 / D-009).
- No embedded domain logic; server re-validates and derives `caller: cli` from credentials.
- Package-root `.goreleaser.yml` (GoReleaser v2): multi-arch `capabilities` binary
  (darwin/linux/windows × amd64/arm64), `-X main.Version={{.Version}}` (strip `v` from
  tag), `checksums.txt`; secret-gated platform signing via `scripts/sign-binary.sh`.
- Secret-gated macOS codesign/notarization + Windows Authenticode scaffold
  (workflow conditions + soft-skip hooks). See `docs/release-signing.md`. When secrets
  are absent, releases still publish unsigned multi-arch assets + checksums.
- **Release automation path:** monorepo git tag `v*` → package split/mirror → child-repo
  GitHub Release on `rawphp/capabilities-cli` (`.github/workflows/release.yml` + GoReleaser).
  Install/download pointer and residual wording updated in package README, `docs/build-matrix.md`,
  and user guide.
- Maintainer path map: `docs/release-path.md` (entry monorepo `v*` tag → terminal GitHub
  Release; package-owned only — no PHP-remote release jobs).

### Notes

- **Not a Packagist package** (Go module). Preferred consumer install is a **GitHub Release**
  binary from the mirrored package remote; source build and ad-hoc cross-compile remain
  documented (`dist/` matrix + ldflags).
- Module path: `github.com/rawphp/capabilities-cli` (see `go.mod`).
- Version marker is the monorepo git tag pattern `v0.Y.Z` (mirrored to this package remote);
  release builds inject the tag-without-`v` via ldflags (see `docs/build-matrix.md`).
- This package tree is mirrored from the monorepo to `github.com/rawphp/capabilities-cli` on push.
- Platform **signing** still depends on secrets configured on the child repo; without them
  releases publish unsigned multi-arch assets (not a hard release failure).

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0 (mirrored to this package remote), promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix.
-->

## [0.x] — pre-stable

Pre-1.0 development line. CLI flags and wire assumptions may change without a major bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/capabilities-cli
[0.x]: https://github.com/rawphp/capabilities-cli
