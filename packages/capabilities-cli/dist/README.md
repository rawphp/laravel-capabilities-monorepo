# Cross-compile targets

**Primary release path (CI):** monorepo tag `v*` → split mirrors into
`rawphp/capabilities-cli` → package workflow [`.github/workflows/release.yml`](../.github/workflows/release.yml)
runs **GoReleaser** ([`.goreleaser.yml`](../.goreleaser.yml), schema **v2**) and
publishes multi-arch archives + `checksums.txt` to a
[GitHub Release](https://github.com/rawphp/capabilities-cli/releases).

Manual / ad-hoc builds without GoReleaser remain available for contributors
(matrix and examples below). They are **not** the only distribution path.

## Supported GOOS / GOARCH matrix

| OS (`GOOS`) | Arch (`GOARCH`) | Notes |
|-------------|-----------------|-------|
| `darwin`    | `amd64`         | Intel Mac |
| `darwin`    | `arm64`         | Apple Silicon |
| `linux`     | `amd64`         | Common servers / CI |
| `linux`     | `arm64`         | ARM servers / Raspberry Pi-class |
| `windows`   | `amd64`         | Windows x86_64 |
| `windows`   | `arm64`         | Windows ARM |

## Example builds

```bash
# from packages/capabilities-cli
GOOS=darwin  GOARCH=arm64 go build -o dist/capabilities-darwin-arm64  ./cmd/capabilities
GOOS=darwin  GOARCH=amd64 go build -o dist/capabilities-darwin-amd64  ./cmd/capabilities
GOOS=linux   GOARCH=amd64 go build -o dist/capabilities-linux-amd64   ./cmd/capabilities
GOOS=linux   GOARCH=arm64 go build -o dist/capabilities-linux-arm64   ./cmd/capabilities
GOOS=windows GOARCH=amd64 go build -o dist/capabilities-windows-amd64.exe ./cmd/capabilities
GOOS=windows GOARCH=arm64 go build -o dist/capabilities-windows-arm64.exe ./cmd/capabilities
```

## Version embedding (`-ldflags`)

The CLI reports its version from package-level `var Version` in `cmd/capabilities`
(`package main`). Release builds **must** inject the release version at link time
so `capabilities version` matches the git tag (strip the leading `v` from tags
like `v0.2.0` → `0.2.0`).

**ldflags key (exact symbol path for GoReleaser / manual builds):**

```text
-X main.Version=<version-without-leading-v>
```

Example:

```bash
# from packages/capabilities-cli
go build -ldflags "-X main.Version=0.2.0" -o dist/capabilities ./cmd/capabilities
./dist/capabilities version
# → capabilities 0.2.0
```

Without `-ldflags`, the binary keeps the source default in `Version` (dev string).

## GoReleaser (release path)

Config: `packages/capabilities-cli/.goreleaser.yml` (self-contained after split).

| Item | Value |
|------|--------|
| Schema | GoReleaser **v2** (`version: 2` in YAML) |
| Main | `./cmd/capabilities` |
| Binary | `capabilities` |
| Matrix | darwin/linux/windows × amd64/arm64 |
| Version ldflags | `-X main.Version={{.Version}}` (tag `v1.2.3` → `1.2.3`) |
| Checksums | `checksums.txt` (sha256) |
| Signing | Secret-gated (macOS + Windows); **unsigned** assets still publish when secrets are missing — see [`docs/release-signing.md`](../docs/release-signing.md) |

```bash
# from packages/capabilities-cli (requires goreleaser v2.x on PATH)
goreleaser check
goreleaser release --snapshot --clean --skip=publish
```

Single static binary per target — no Node/PHP runtime required on the user machine (D-016).
