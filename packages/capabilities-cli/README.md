# capabilities CLI

> **Package:** `rawphp/capabilities-cli`  
> **Language:** Go (D-016)  
> **Binary:** `capabilities`  
> **Status:** 0.x pre-stable — prefer GitHub Release binaries on `v*` tags; source build still supported  
> **Repo:** [github.com/rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli) (mirrored from the monorepo on push)

Downloadable client for end users and local agents. Auth + catalog + run + optional MCP stdio against a remote Laravel app’s **same** HTTP capability API (D-009). **No domain `run()` on the laptop.**

### Releases

Automated **GitHub Releases** (multi-arch `capabilities` binaries + checksums) run on this package repo when a monorepo tag matching `v*` is split/mirrored here. The package-owned workflow [`.github/workflows/release.yml`](.github/workflows/release.yml) runs **GoReleaser** (see [`.goreleaser.yml`](.goreleaser.yml)); retags **replace** the existing release assets. Unsigned publish needs only `GITHUB_TOKEN` (`contents: write`) — no monorepo `SPLIT_GITHUB_TOKEN`. Cross-compile notes: [`dist/README.md`](dist/README.md).

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| Changelog | [CHANGELOG.md](CHANGELOG.md) |
| Server HTTP API | [laravel-capabilities user guide](https://github.com/rawphp/laravel-capabilities/blob/main/docs/user-guide.md) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |

## Layout

```text
cmd/capabilities/   # main
internal/
  auth/             # keychain / config-dir token store
  catalog/          # fetch + cache JSON Schema
  run/              # validate locally → POST invoke
  mcpstdio/         # optional MCP stdio bridge
  api/              # HTTP client
dist/               # cross-compile notes + future goreleaser artifacts
```

## Principles

- HTTP client only (`caller: cli` is **server-derived** from credentials).
- Local JSON Schema validation is UX; server always re-validates.
- Every `run` sends `Idempotency-Key` (UUID unless `--idempotency-key` / `--retry-last`).
- Single static binary — no Node/PHP required on the user machine.
- No multi-language CLI matrix in v0.2 (Go only).

## Build & test

```bash
go test ./...
go build -o capabilities ./cmd/capabilities
```

Cross-compile targets: darwin/linux/windows × amd64/arm64 (see `dist/README.md`).
