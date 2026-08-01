# Release path (tag → GitHub Release)

End-to-end path for shipping multi-arch `capabilities` binaries after a monorepo
release tag. This document is the **path-unit map** for maintainers; build and
signing mechanics live in sibling docs and package-root automation files.

## Entry

Maintainer pushes a monorepo git tag matching `v*` (for example `v0.2.0`).

The monorepo **split** workflow already mirrors that tag into this package
remote (`rawphp/capabilities-cli`). Split is **not** re-implemented here — do
not add monorepo jobs that create CLI releases on PHP package remotes.

## Terminal

`rawphp/capabilities-cli` has a **GitHub Release** for that tag with multi-arch
`capabilities` archives (darwin/linux/windows × amd64/arm64) plus checksums.
When platform-signing secrets are present, assets may be signed; when absent,
unsigned publish still succeeds (**secret-gated** soft path). A downloaded
binary reports the release version via `capabilities version` (tag without
leading `v`, injected at build via ldflags).

## Steps (package-owned)

| Step | What | Where |
|------|------|--------|
| 1 | Version string overridable with `-X main.Version=…` | `cmd/capabilities` + `docs/build-matrix.md` |
| 2 | Multi-arch build, archives, checksums | package-root [`.goreleaser.yml`](../.goreleaser.yml) |
| 3 | Tag trigger `v*`, GoReleaser release, **replace** on retag | [`.github/workflows/release.yml`](../.github/workflows/release.yml) |
| 4 | Platform signing when secrets exist; skip + log otherwise | [`release-signing.md`](release-signing.md), `scripts/sign-binary.sh` |

After split, this tree is the child repo root: workflow and GoReleaser paths
above appear at repo root on `rawphp/capabilities-cli`.

## Non-goals

- No second CLI release pipeline in monorepo PHP packages or split YAML.
- No Artisan product CLI; this is the downloadable Go client only.
- No committed certificates or private keys.

## Related

- Install / download pointer: package [README](../README.md) § Releases
- Signing secrets: [`release-signing.md`](release-signing.md)
- Cross-compile matrix notes: [`build-matrix.md`](build-matrix.md)
