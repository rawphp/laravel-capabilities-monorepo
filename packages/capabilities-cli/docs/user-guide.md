# Product CLI: capabilities (Go)

> Ships with the **capabilities-cli** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Downloadable client for end users and local agents. Authenticates to a deployment, lists the capability catalog, validates input locally, and invokes capabilities over the app’s **same** HTTP API. Optional MCP stdio bridge for local agent runtimes.

**Module:** `github.com/rawphp/capabilities-cli`  
**Binary name:** `capabilities`  
**Language:** Go 1.22+ (D-016)  
**Status:** 0.x pre-stable; prefer release binaries when available, or build from source  
**Repo:** [github.com/rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli)

**No domain `run()` on the laptop.** The server always re-validates and authorizes.

## Install

### GitHub Release binaries (recommended when tagged)

After a monorepo `v*` tag is split into this package remote, CI publishes multi-arch
`capabilities` archives to
[GitHub Releases](https://github.com/rawphp/capabilities-cli/releases)
(darwin / linux / windows × amd64 / arm64, plus `checksums.txt`).

1. Open the latest (or desired) release on that page.
2. Download the archive for your OS/arch.
3. Extract the `capabilities` binary, place it on your `PATH`, and run `capabilities version`.

Assets may be **unsigned** unless platform-signing secrets are configured on the
package repo (see [`release-signing.md`](release-signing.md)). Source build remains
fully supported either way.

### Build from source

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

## Before you start

- A running Laravel app with `rawphp/laravel-capabilities` HTTP surface enabled and reachable
- Credentials the app accepts for CLI (server derives caller `cli` from token abilities / auth — see core `clients.token_abilities`)
- Go toolchain only if building from source (not required for release binaries)

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
capabilities catalog [--json|--flat] [--no-cache] [--refresh] [--profile=NAME]
```

Fetches capabilities from `GET /capabilities` (via the HTTP client).

| Mode | Audience | Output |
|---|---|---|
| *(default)* | Humans | Domain index — domains + verb counts + next steps |
| `--flat` | Humans | Flat `name → domain verb` lines (previous default) |
| `--json` | Agents | Machine envelope; rows may include client-side synthesis fields: |

With `--json`, rows may include:

| Field | Meaning |
|---|---|
| `cli.domain` / `cli.verb` | Routing metadata from the server (when fully set) |
| `mapped_command` | Client-derived `"domain verb"` after synth index build |
| `mapping_error` | Client suppressed synthesis (e.g. collision) — use `run <name>` |

### Agent quickstart (domain/verb synthesis)

After auth, agents can discover and invoke without reading prose:

```bash
capabilities catalog --json                  # discover names + cli.domain/cli.verb (when set)
capabilities <domain> --help                 # list verbs under a catalog domain
capabilities <domain> <verb> --help --json   # capability_help envelope (fields[], schemas)
capabilities <domain> <verb> --flag=value    # scalar flags (from input schema)
# or full JSON / hybrid:
capabilities <domain> <verb> --input='{"...":"..."}'
```

Parse **stdout** as a JSON envelope. Branch on **exit code**. Optional `--human` writes a short summary to **stderr** only — it never replaces the stdout envelope.

Reserved meta-commands always win over domain tokens of the same name:
`auth` · `catalog` · `describe` · `run` · `mcp` · `approvals` · `version` · `help`

Unmapped capabilities (no `cli` metadata and no mechanical `domain.verb` / `domain/verb` name) stay available via `run` / `describe` only.

### `describe`

```bash
capabilities describe <name> [--json] [--no-cache] [--profile=NAME]
```

Fetches JSON Schema / description for one capability.

### `run` and synthesized `<domain> <verb>`

```bash
capabilities run <name> [scalar flags | --input=JSON | --input-file=PATH] [shared flags]
capabilities <domain> <verb> [same]
```

Scalar flags and JSON are **equal** first-class inputs. Merge rule: base = `--input` / `--input-file` (or `{}`), then each scalar flag overwrites that key (**flag wins**). Object/array fields are **json-only** (no flag). Unknown flags and json-only flags → exit **2**.

Shared invoke flags:

| Flag | Role |
|---|---|
| `--input=JSON` | Inline JSON body |
| `--input-file=PATH` | Read JSON from file |
| `--idempotency-key=KEY` | Manual key (default: new UUID) |
| `--retry-last` | Reuse last Idempotency-Key after network failure |
| `--no-cache` | Re-fetch schema |
| `--json` | Legacy alias — stdout is always the machine envelope |
| `--human` | Human summary on **stderr** only |
| `--tenant=ID` | Tenant **hint** only — not authoritative scope |
| `--profile=NAME` | Auth profile |
| `--base-url=URL` | Base URL override |

Flow (single path for flags, JSON, and hybrid): merge → load schema → local JSON Schema validate → ensure Idempotency-Key → `POST /capabilities/{canonicalName}`. No second mutation path.

Empty invoke with an all-optional schema may POST `{}`. Missing required fields → exit **2** (point at `--help`).

Examples (names/domains come from the remote catalog — never hard-coded product domains):

```bash
capabilities run <name> --input='{"...":"..."}'

capabilities <domain> <verb> --flag=value --human
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
  catalog/          # fetch + cache JSON Schema; client mapping enrich
  synth/            # domain/verb index from catalog
  helpfmt/          # human + machine schema help
  flagschema/       # scalar flags + merge with JSON
  run/              # validate locally → POST invoke
  mcpstdio/         # optional MCP stdio bridge
  api/              # HTTP client
dist/               # cross-compile notes; CI release uses GoReleaser
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
