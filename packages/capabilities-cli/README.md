# capabilities CLI

> **Package:** `rawphp/capabilities-cli`  
> **Language:** Go (D-016)  
> **Binary:** `capabilities`

Downloadable client for end users and local agents. Auth + catalog + run + optional MCP stdio against a remote Laravel app’s **same** HTTP capability API (D-009). **No domain `run()` on the laptop.**

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
