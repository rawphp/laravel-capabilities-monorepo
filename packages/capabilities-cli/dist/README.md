# Cross-compile targets

Release artifacts for the `capabilities` binary are produced by **GoReleaser**
using the package-root config [`.goreleaser.yml`](../.goreleaser.yml) (GoReleaser
**v2** schema: `version: 2`). On `v*` tags in `rawphp/capabilities-cli` (after
monorepo split), the package release workflow runs GoReleaser and attaches
archives + `checksums.txt` to a GitHub Release.

For local / ad-hoc builds without GoReleaser, use Go’s standard cross-compile
flags below.

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
| Signing | Not in base config; secret-gated in a later release step |

```bash
# from packages/capabilities-cli (requires goreleaser v2.x on PATH)
goreleaser check
goreleaser release --snapshot --clean --skip=publish
```

Single static binary per target — no Node/PHP runtime required on the user machine (D-016).
