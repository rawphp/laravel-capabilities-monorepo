# Product CLI: capabilities (Go)

> Ships with the **capabilities-cli** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Downloadable client for end users and local agents. Authenticates to a deployment, lists the capability catalog, validates input locally, and invokes capabilities over the app’s **same** HTTP API. Optional MCP stdio bridge for local agent runtimes.

**Module:** `github.com/rawphp/capabilities-cli`  
**Binary name:** `capabilities`  
**Language:** Go 1.22+ (D-016)  
**Status:** 0.x pre-stable; build from source until signed binary releases exist  
**Repo:** [github.com/rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli)

**No domain `run()` on the laptop.** The server always re-validates and authorizes.

## Before you start

- Go toolchain for build/test
- A running Laravel app with `rawphp/laravel-capabilities` HTTP surface enabled and reachable
- Credentials the app accepts for CLI (server derives caller `cli` from token abilities / auth — see core `clients.token_abilities`)

## Build and test

```bash
# from this package root (monorepo: packages/capabilities-cli)
go test ./...
go build -o capabilities ./cmd/capabilities
```

Cross-compile targets (darwin/linux/windows × amd64/arm64): see [`dist/README.md`](../dist/README.md).

```bash
# example Apple Silicon
GOOS=darwin GOARCH=arm64 go build -o dist/capabilities-darwin-arm64 ./cmd/capabilities
```

Single static binary — no Node or PHP on the user machine.

## Principles

- HTTP client only; `caller: cli` is **server-derived** from credentials.
- Never spoof client-claimed caller headers (for example `X-Capabilities-Caller`).
- Local JSON Schema validation is UX; server is law.
- Every `run` sends `Idempotency-Key` (UUID unless `--idempotency-key` or `--retry-last`).
- No multi-language CLI matrix in v0.2 (Go only).
- Not Artisan — binary name is `capabilities`.

## Config and auth store

Default config root: `~/.config/capabilities` (overridable in tests; production CLI uses the user config dir).

Tokens live in the OS config/keychain-backed store for a **profile** (default profile name: `default`). Tokens are not printed to stdout by `auth status`.

## Commands

```text
capabilities <command> [flags]
```

Common flags:

| Flag | Role |
|---|---|
| `--profile=NAME` | Auth profile (default `default`) |
| `--base-url=URL` | Override deployment base URL |
| `--json` | Print structured JSON envelopes (D-018 style) |

### `auth`

```bash
capabilities auth login --base-url=URL [--token=PAT] [--code=OAUTH] [--profile=NAME]
capabilities auth logout [--profile=NAME]
capabilities auth status [--profile=NAME]
```

- `login` requires `--base-url`.
- With `--token`, stores a PAT-style token.
- With `--code`, OAuth code exchange against the API client.
- With neither, device-code login flow against the API.
- Successful login best-effort prefetches catalog schemas into the profile cache.

### `catalog`

```bash
capabilities catalog [--json] [--no-cache] [--refresh] [--profile=NAME]
```

Lists capabilities from `GET /capabilities` (via the HTTP client).

### `describe`

```bash
capabilities describe <name> [--json] [--no-cache] [--profile=NAME]
```

Fetches JSON Schema / description for one capability.

### `run`

```bash
capabilities run <name> --input=JSON|--input-file=PATH [flags]
```

Flags:

| Flag | Role |
|---|---|
| `--input=JSON` | Inline JSON body |
| `--input-file=PATH` | Read JSON from file |
| `--idempotency-key=KEY` | Manual key (default: new UUID) |
| `--retry-last` | Reuse last Idempotency-Key after network failure |
| `--no-cache` | Re-fetch schema |
| `--json` | Envelope on stdout |
| `--tenant=ID` | Tenant **hint** only — not authoritative scope |
| `--profile=NAME` | Auth profile |
| `--base-url=URL` | Base URL override |

Flow: load input → local schema validate (fail closed before network when schema available) → ensure idempotency key → `POST /capabilities/{name}`.

Example:

```bash
capabilities run create-invoice \
  --input='{"customer_id":42,"amount_cents":2500,"currency":"USD"}' \
  --json
```

### `mcp`

```bash
capabilities mcp [--profile=NAME] [--base-url=URL]
```

MCP **stdio** bridge: proxies `tools/list` and `tools/call` to the remote HTTP capability API using the stored CLI token. No local domain authorize/run.

### `approvals`

```bash
capabilities approvals accept <id>
capabilities approvals reject <id>
```

Accept or reject pending approvals via the HTTP approval routes.

### `version` / `help`

```bash
capabilities version
capabilities help
capabilities help run
```

## Exit codes (stable)

| Code | Meaning |
|---|---|
| 0 | Success |
| 1 | Internal error |
| 2 | `validation_failed` |
| 3 | Unauthenticated / forbidden |
| 4 | `approval_required` |
| 5 | Domain error / conflict / not_found / output_invalid |
| 6 | Rate limited |

## Layout (source)

```text
cmd/capabilities/   # main + command wiring
internal/
  auth/             # keychain / config-dir token store
  catalog/          # fetch + cache JSON Schema
  run/              # validate locally → POST invoke
  mcpstdio/         # optional MCP stdio bridge
  api/              # HTTP client
dist/               # cross-compile notes + future release artifacts
```

## How you know it worked

```bash
./capabilities version          # prints: capabilities 0.2.0 (version string may move with releases)
./capabilities auth login --base-url=https://app.example.com --token=***
./capabilities auth status      # logged_in=true, no token printed
./capabilities catalog
./capabilities run …            # exit 0 on success
```

## If something goes wrong

Troubleshooting (monorepo): [Product CLI](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md#product-cli).

| Symptom | Likely fix |
|---|---|
| `auth login requires --base-url` | Pass `--base-url` |
| Missing base URL on later commands | Re-login with base URL or pass `--base-url` |
| Exit 3 | Token/profile wrong; server rejected auth |
| Exit 2 before network | Local schema validation — fix JSON or refresh catalog |
| Exit 4 | Approval required — use `approvals accept/reject` or another notifier path |

## Related

- [Package README](../README.md)
- [CHANGELOG](../CHANGELOG.md)
- Core HTTP API: [laravel-capabilities user guide](https://github.com/rawphp/laravel-capabilities/blob/main/docs/user-guide.md#http-api-single-tree)
- Getting started (monorepo): [optional CLI](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/getting-started.md#5-optional-product-cli-on-the-user-machine)
- Concepts (monorepo): [Product CLI vs Artisan](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/concepts.md#product-cli-vs-artisan)
