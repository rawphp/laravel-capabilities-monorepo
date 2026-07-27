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

## [0.x] — pre-stable monorepo

Pre-1.0 development line. CLI flags and wire assumptions may change without a major bump while on 0.x.

[Unreleased]: https://github.com/rawphp/laravel-capabilities
[0.x]: https://github.com/rawphp/laravel-capabilities
