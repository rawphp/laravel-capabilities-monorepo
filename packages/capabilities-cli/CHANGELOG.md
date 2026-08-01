# Changelog

All notable changes to `rawphp/capabilities-cli` (Go binary `capabilities`) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Added

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
