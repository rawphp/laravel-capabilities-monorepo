# Product CLI: `capabilities` (Go)

> Package: **`rawphp/capabilities-cli`** · Binary: **`capabilities`** · Language: Go 1.24+  
> Status: **0.x pre-stable** · Repo: [github.com/rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli)

Downloadable client for **humans and local agents**. Authenticates to a Laravel
deployment, lists the capability catalog, validates input locally, and invokes
capabilities over the app’s **same** HTTP API. Optional MCP stdio bridge.

**No domain `run()` on the laptop.** The server always re-validates and authorizes.

Docs index: [docs/README.md](README.md) · Deep dives: [authentication.md](authentication.md) · [agents.md](agents.md)

---

## Table of contents

1. [What this is (and is not)](#what-this-is-and-is-not)
2. [Installation](#installation)
3. [Quick start](#quick-start)
4. [Principles](#principles)
5. [Configuration & storage](#configuration--storage)
6. [Authentication](#authentication)
7. [Multiple projects (profiles)](#multiple-projects-profiles)
8. [Commands](#commands)
9. [Discovery (catalog & domain/verb)](#discovery-catalog--domainverb)
10. [Invoking capabilities](#invoking-capabilities)
11. [Agents & MCP](#agents--mcp)
12. [Exit codes](#exit-codes)
13. [Troubleshooting](#troubleshooting)
14. [Security](#security)
15. [Related](#related)

---

## What this is (and is not)

| This CLI **is** | This CLI is **not** |
|-----------------|---------------------|
| An HTTP client for the capability API | Artisan / `php artisan` |
| A local UX layer (schema validate, help, catalog) | A place to put domain business logic |
| Multi-profile (many deployments per laptop) | One login shared blindly across products |
| A single static Go binary | A Node/PHP dependency on the user machine |

Capability **names, domains, and verbs** come from **your** server’s catalog.
Examples in this guide use placeholders like `<name>` and `<domain> <verb>`.

---

## Installation

### User-global (recommended)

Installs into `~/.local/bin` (no sudo):

```bash
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash
export PATH="$HOME/.local/bin:$PATH"   # if needed
capabilities version
```

Pin version or install directory:

```bash
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | VERSION=0.1.7 bash
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | CAPABILITIES_INSTALL_DIR="$HOME/bin" bash
```

### Manual (macOS / Linux)

```bash
VERSION=0.1.7   # or latest from Releases
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
ARCH=$(uname -m); case "$ARCH" in x86_64) ARCH=amd64;; aarch64|arm64) ARCH=arm64;; esac

curl -fsSL "https://github.com/rawphp/capabilities-cli/releases/download/v${VERSION}/capabilities_${VERSION}_${OS}_${ARCH}.tar.gz" \
  | tar -xz -C /tmp capabilities
mkdir -p ~/.local/bin
install -m 755 /tmp/capabilities ~/.local/bin/capabilities
```

### Windows

1. Download `capabilities_*_windows_amd64.zip` (or `arm64`) from
   [Releases](https://github.com/rawphp/capabilities-cli/releases).
2. Extract `capabilities.exe` into a folder on your **user** `PATH`.
3. Run `capabilities version`.

Windows binaries may be unsigned unless Authenticode secrets are configured.
macOS release assets may be **Developer ID signed and notarized**.

### Build from source

```bash
# package root (monorepo: packages/capabilities-cli)
go test ./...
go build -o capabilities ./cmd/capabilities
```

Cross-compile: [build-matrix.md](build-matrix.md).

---

## Quick start

```bash
# 1. Login (stores token + base URL under a profile)
capabilities auth login \
  --base-url=https://app.example.com \
  --token="$TOKEN"
  # optional: --profile=myapp   (default profile name is "default")

# 2. Confirm (token is never printed)
capabilities auth status

# 3. Discover what this deployment exposes
capabilities catalog

# 4. Invoke (name from catalog — not invented here)
capabilities run <capability.name> --input='{}'

# Agents: machine map
capabilities catalog --json
```

---

## Principles

- HTTP client only; `caller: cli` is **server-derived** from credentials.
- Never spoof client-claimed caller headers (e.g. `X-Capabilities-Caller`).
- Local JSON Schema validation is UX; **server is law**.
- Every `run` sends `Idempotency-Key` (UUID unless `--idempotency-key` / `--retry-last`).
- Binary name is `capabilities` — not Artisan.
- No multi-language CLI matrix in v0.2 (Go only).

---

## Configuration & storage

Default config root: **`~/.config/capabilities`**.

```text
~/.config/capabilities/
  profiles/
    default/                 # or any --profile name
      token                  # 0600
      config.json            # { "base_url": "https://..." }
      schemas/               # cached catalog schemas
      last_run.json          # last Idempotency-Key
```

Tokens are **not** printed by `auth status`.

---

## Authentication

```bash
capabilities auth login --base-url=URL [--token=PAT] [--code=OAUTH] [--profile=NAME]
capabilities auth logout [--profile=NAME]
capabilities auth status [--profile=NAME]
```

| Login style | How |
|-------------|-----|
| PAT / API token | `--token=...` |
| OAuth code | `--code=...` |
| Device code | omit token and code (API-driven device flow) |

`login` always requires `--base-url`. Successful login best-effort prefetches
catalog schemas into that profile’s cache.

Full detail: **[authentication.md](authentication.md)**.

---

## Multiple projects (profiles)

**One install of `capabilities`, many products.**

Each Laravel app / environment should get its **own profile**. Profiles isolate
token, base URL, and schema cache.

### Setup once per product

```bash
capabilities auth login \
  --profile=mesoprep \
  --base-url=https://mesoprep.example.com \
  --token="$MESOPREP_TOKEN"

capabilities auth login \
  --profile=yardpilot \
  --base-url=https://yardpilot.example.com \
  --token="$YARDPILOT_TOKEN"
```

### Use the right profile every time

```bash
capabilities catalog --profile=mesoprep
capabilities run some.capability --profile=mesoprep --input='{}'

capabilities catalog --profile=yardpilot
capabilities <domain> <verb> --profile=yardpilot --flag=value
```

`--profile` works on auth, catalog, describe, run, domain/verb invoke, mcp, and
approvals. Default when omitted: **`default`**.

### Shell aliases

```bash
alias cap-meso='capabilities --profile=mesoprep'
alias cap-yard='capabilities --profile=yardpilot'
cap-meso catalog
```

### List profiles (today)

There is no `auth list` yet:

```bash
ls ~/.config/capabilities/profiles/
```

Deep dive: **[authentication.md](authentication.md#multiple-projects--multi-deployment-profiles)**.

---

## Commands

```text
capabilities <command> [flags]
capabilities <domain> <verb> [flags]
```

### Common flags

| Flag | Role |
|------|------|
| `--profile=NAME` | Auth profile (default `default`) |
| `--base-url=URL` | Override deployment base URL for this invocation |
| `--json` | Machine / structured envelopes where applicable |

### `auth`

See [Authentication](#authentication) and [authentication.md](authentication.md).

### `catalog`

```bash
capabilities catalog [--json|--flat] [--no-cache] [--refresh] [--profile=NAME]
```

Fetches from `GET /capabilities` via the HTTP client.

| Mode | Audience | Output |
|------|----------|--------|
| *(default)* | Humans | Domain index — domains + verb counts + next steps |
| `--flat` | Humans | Flat `name → domain verb` lines |
| `--json` | Agents | Machine envelope; may include `cli.domain` / `cli.verb`, `mapped_command`, `mapping_error` |

### `describe`

```bash
capabilities describe <name> [--json] [--no-cache] [--profile=NAME]
```

JSON Schema / description for one capability.

### `run` and `<domain> <verb>`

```bash
capabilities run <name> [flags]
capabilities <domain> <verb> [flags]
```

See [Invoking capabilities](#invoking-capabilities).

### `mcp`

```bash
capabilities mcp [--profile=NAME] [--base-url=URL]
```

MCP **stdio** bridge: proxies `tools/list` and `tools/call` to the remote API
using the stored CLI token. No local domain authorize/run.

### `approvals`

```bash
capabilities approvals accept <id> [--profile=NAME]
capabilities approvals reject <id> [--profile=NAME]
```

### `version` / `help`

```bash
capabilities version
capabilities help
capabilities help run
capabilities <domain> --help
capabilities <domain> <verb> --help [--json]
```

Reserved meta-commands always win over domain tokens of the same name:
`auth` · `catalog` · `describe` · `run` · `mcp` · `approvals` · `version` · `help`.

---

## Discovery (catalog & domain/verb)

After auth:

```bash
capabilities catalog                         # human domain index
capabilities catalog --json                  # agent map
capabilities <domain> --help                 # verbs under a domain
capabilities <domain> <verb> --help --json   # capability_help envelope
```

Unmapped capabilities (no usable `cli` metadata / name shape) remain available
via `run` / `describe` only.

---

## Invoking capabilities

```bash
capabilities run <name> \
  [--input=JSON | --input-file=PATH | scalar flags] \
  [--idempotency-key=KEY] [--retry-last] \
  [--no-cache] [--human] [--tenant=ID] \
  [--profile=NAME] [--base-url=URL]
```

### Input merge rules

1. Base body = `--input` / `--input-file` (or `{}`).
2. Each scalar flag overwrites that key (**flag wins**).
3. Object/array fields are **JSON-only** (no flag form).
4. Unknown flags or json-only fields as flags → exit **2**.
5. All-optional schema may POST `{}`.
6. Missing required fields → exit **2** (use `--help` for schema).

### Flow (single path)

merge → load schema → local JSON Schema validate → ensure Idempotency-Key →
`POST /capabilities/{canonicalName}`.

### Examples (placeholders)

```bash
capabilities run <name> --input='{"customer_id":1}' --profile=mesoprep

capabilities <domain> <verb> --customer_id=1 --human --profile=mesoprep

capabilities run <name> --input-file=./payload.json --retry-last
```

`--tenant=ID` is a **hint only** — not authoritative scope (server decides).

`--human` writes a short summary to **stderr**; stdout remains the machine path.

---

## Agents & MCP

Summary:

1. Always pass `--profile=` when more than one product is configured.
2. Discover with `catalog --json`; invoke with `run` or mapped domain/verb.
3. Parse **stdout**; branch on **exit code**.
4. MCP: `capabilities mcp --profile=…` as a stdio subprocess.

Full guide: **[agents.md](agents.md)**.

---

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Internal error |
| 2 | `validation_failed` |
| 3 | Unauthenticated / forbidden |
| 4 | `approval_required` |
| 5 | Domain error / conflict / not_found / output_invalid |
| 6 | Rate limited |

These codes are part of the CLI contract (stable for automation).

---

## Troubleshooting

| Symptom | Likely fix |
|---------|------------|
| `auth login requires --base-url` | Pass `--base-url` |
| Missing base URL on later commands | Re-login or pass `--base-url` |
| Exit 3 | Wrong/missing token or profile; re-login |
| Exit 2 before network | Local schema validation — fix JSON or refresh catalog (`--no-cache`) |
| Exit 4 | Approval required — `approvals accept/reject` |
| Wrong product’s data | You used the wrong `--profile` |
| `command not found: capabilities` | Install path not on `PATH` (`~/.local/bin`) |
| Gatekeeper / notarization (macOS) | Prefer release builds from GitHub; see [release-signing.md](release-signing.md) |

Monorepo troubleshooting (broader product):  
[Product CLI section](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md#product-cli).

---

## Security

- Store tokens only via `auth login` (profile store modes `0600` / dir `0700`).
- Never put PATs in agent prompts, git, or chat logs.
- Prefer device/OAuth flows where available for interactive use.
- Treat `~/.config/capabilities/profiles/` as secret material.
- MCP inherits the profile token — scope profiles tightly per product.

---

## Related

| Resource | Link |
|----------|------|
| Docs index | [README.md](README.md) |
| Auth & multi-profile | [authentication.md](authentication.md) |
| Agents & MCP | [agents.md](agents.md) |
| Package README | [../README.md](../README.md) |
| Changelog | [../CHANGELOG.md](../CHANGELOG.md) |
| Server HTTP API | [laravel-capabilities user guide](https://github.com/rawphp/laravel-capabilities/blob/main/docs/user-guide.md) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |
| Maintainer release path | [release-path.md](release-path.md) |
| Signing secrets | [release-signing.md](release-signing.md) |

### How you know it worked

```bash
capabilities version
capabilities auth login --base-url=https://app.example.com --token=***
capabilities auth status      # logged_in=true, no token printed
capabilities catalog
capabilities run …            # exit 0 on success
```
