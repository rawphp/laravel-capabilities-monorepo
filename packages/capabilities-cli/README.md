# capabilities CLI

> **Package:** `rawphp/capabilities-cli`  
> **Language:** Go (D-016)  
> **Binary:** `capabilities`

Downloadable client for end users and local agents. Auth + catalog + run + optional MCP stdio against a remote Laravel app’s **same** HTTP capability API (D-009). **No domain `run()` on the laptop.**

## Layout

```text
cmd/capabilities/   # main
internal/
  auth/             # keychain token store
  catalog/          # fetch + cache JSON Schema
  run/              # validate locally → POST invoke
  mcpstdio/         # optional MCP stdio bridge
  api/              # HTTP client
dist/               # goreleaser binaries + install.sh + brew formula
```
