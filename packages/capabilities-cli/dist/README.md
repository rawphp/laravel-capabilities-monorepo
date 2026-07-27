# Cross-compile targets

Release artifacts for the `capabilities` binary land here (e.g. via goreleaser).
Until automated release packaging lands, build locally with Go’s standard
cross-compile flags.

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

Single static binary per target — no Node/PHP runtime required on the user machine (D-016).
