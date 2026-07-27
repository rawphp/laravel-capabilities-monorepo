# Changelog

All notable changes to `rawphp/capabilities-cli` (Go binary `capabilities`) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (see [docs/versioning.md](../../docs/versioning.md)).

## [Unreleased]

### Added

- Downloadable Go product CLI: auth, catalog, local JSON Schema validation (UX only),
  invoke via the server’s single HTTP capability API, optional MCP stdio bridge (D-016 / D-009).
- No embedded domain logic; server re-validates and derives `caller: cli` from credentials.

### Notes

- **Not a Packagist package** (Go module). Distributed as source / future binary artifacts under `dist/`.
- Module path: `github.com/rawphp/capabilities-cli` (see `go.mod`).
- Thin client; changelog entries stay high-level until tagged releases exist.
- Version marker for prep is the monorepo git tag pattern `v0.Y.Z` (see docs/versioning.md); binary embedding is a later release step.

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0, promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix. See docs/versioning.md → Git tag naming.
-->

## [0.x] — pre-stable monorepo

Pre-1.0 development line. CLI flags and wire assumptions may change without a major bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/laravel-capabilities
[0.x]: https://github.com/rawphp/laravel-capabilities
