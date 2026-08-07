# capabilities CLI

> **Package:** `rawphp/capabilities-cli`  
> **Language:** Go (D-016)  
> **Binary:** `capabilities`  
> **Status:** 0.x pre-stable — install from [GitHub Releases](https://github.com/rawphp/capabilities-cli/releases) on tagged builds; source build still supported  
> **Repo:** [github.com/rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli) (mirrored from the monorepo on push)

Downloadable client for end users and local agents. Auth + catalog + run against a remote Laravel app’s **same** HTTP capability API (D-009). **No domain `run()` on the laptop.** Product MCP is server-side (`laravel/mcp`); this CLI is HTTP-only.

## Scope (this package)

| | |
|---|---|
| **Is** | Go binary `capabilities`: multi-profile auth, catalog, run, client-side schema checks, auto idempotency keys — HTTP client for the remote capability API |
| **Is not** | A second backend or domain runtime; Artisan / in-server ops CLI; PHP Composer package; an MCP stdio server (use server product MCP); chat bots or AI turn engine |

Server-side install is [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) (and optional siblings). This repo is the **laptop client** only.

## Install

Installs the latest release binary **for your user** into `~/.local/bin` (no sudo). macOS assets may be **Developer ID signed and notarized**.

### One-liner (macOS / Linux)

```bash
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash
```

Then ensure user bin is on your `PATH` (if `capabilities` is not found):

```bash
export PATH="$HOME/.local/bin:$PATH"
# optional: add that line to ~/.zshrc or ~/.bashrc
```

Verify:

```bash
capabilities version
```

### Pin a version or install dir

```bash
# pin release
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | VERSION=0.4.0 bash

# custom user-global dir (still no sudo)
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | CAPABILITIES_INSTALL_DIR="$HOME/bin" bash
```

### Manual (macOS / Linux)

```bash
VERSION=0.4.0   # or latest from the Releases page
OS=$(uname -s | tr '[:upper:]' '[:lower:]')   # darwin | linux
ARCH=$(uname -m)
case "$ARCH" in x86_64) ARCH=amd64;; aarch64|arm64) ARCH=arm64;; esac

curl -fsSL "https://github.com/rawphp/capabilities-cli/releases/download/v${VERSION}/capabilities_${VERSION}_${OS}_${ARCH}.tar.gz" \
  | tar -xz -C /tmp capabilities
mkdir -p ~/.local/bin
install -m 755 /tmp/capabilities ~/.local/bin/capabilities
export PATH="$HOME/.local/bin:$PATH"
capabilities version
```

### Windows

1. Download `capabilities_*_windows_amd64.zip` (or `arm64`) from [Releases](https://github.com/rawphp/capabilities-cli/releases).
2. Extract `capabilities.exe` into a folder on your **user** `PATH` (e.g. `%USERPROFILE%\bin`).
3. Open a new terminal and run `capabilities version`.

Windows binaries are currently **unsigned** (Authenticode optional). Source build remains supported — see **Build & test** below. `capabilities self-update` is **darwin/linux only** — on Windows, download a new release zip manually.

### Self-update (macOS / Linux)

After the first install, upgrade to the **latest** release in place:

```bash
capabilities self-update
```

- **Latest only** (no version pin) — use `scripts/install.sh` with `VERSION=` to pin or reinstall.
- Requires GitHub Release **`checksums.txt`** (fail closed on missing/mismatch).
- Already up-to-date → exit **0**.
- Unwritable install path → fail closed; reinstall with `install.sh` / `CAPABILITIES_INSTALL_DIR` into a directory you own (default `~/.local/bin`).
- **darwin and linux only** (Windows: manual zip from Releases).

Full detail: [docs/user-guide.md](docs/user-guide.md#self-update-macos--linux).

## Documentation

| Doc | Description |
|-----|-------------|
| **[User guide](docs/user-guide.md)** | Full manual — install, auth, multi-profile, commands, invoke, exit codes |
| **[Docs index](docs/README.md)** | Map of all package docs |
| [Authentication & profiles](docs/authentication.md) | Multi-project login (`--profile`), storage, aliases |
| [Agents](docs/agents.md) | Machine envelopes, exit codes, HTTP discovery loop |
| [Changelog](CHANGELOG.md) | Release notes |

### Multiple projects (profiles)

One binary, many deployments — use a **named profile** per product:

```bash
capabilities auth login --profile=mesoprep --base-url=https://mesoprep.example.com --token=…
capabilities auth login --profile=yardpilot --base-url=https://yardpilot.example.com --token=…

capabilities catalog --profile=mesoprep
capabilities run <name> --profile=yardpilot --input='{}'
```

Details: [docs/authentication.md](docs/authentication.md).

### Releases

**Flow:** monorepo git tag `v*` → split workflow mirrors the tag into this package repo → package-owned [`.github/workflows/release.yml`](.github/workflows/release.yml) runs **GoReleaser** ([`.goreleaser.yml`](.goreleaser.yml)) → **GitHub Release** with multi-arch `capabilities` archives + `checksums.txt`.

| | |
|---|---|
| **Download** | [github.com/rawphp/capabilities-cli/releases](https://github.com/rawphp/capabilities-cli/releases) |
| **Matrix** | darwin / linux / windows × amd64 / arm64 |
| **Retag** | Re-push of the same `v*` tag **replaces** release assets |
| **Auth for unsigned publish** | Child-repo `GITHUB_TOKEN` (`contents: write`) only — no monorepo `SPLIT_GITHUB_TOKEN` |

Cross-compile / local matrix notes: [`docs/build-matrix.md`](docs/build-matrix.md).

**Platform signing** (macOS codesign/notarization, Windows Authenticode) is **secret-gated**: when secrets are absent the release still publishes **unsigned** assets with clear skip logs; when secrets are present, signing hooks run. Secret names and setup: [`docs/release-signing.md`](docs/release-signing.md). Never commit private keys or certificates.

| Doc | Where |
|---|---|
| User docs (index) | [docs/README.md](docs/README.md) |
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| Auth & multi-profile | [docs/authentication.md](docs/authentication.md) |
| Agents (HTTP) | [docs/agents.md](docs/agents.md) |
| Release path (tag → GitHub Release) | [docs/release-path.md](docs/release-path.md) |
| Release signing (secret-gated) | [docs/release-signing.md](docs/release-signing.md) |
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
  api/              # HTTP client
docs/build-matrix.md # cross-compile / ldflags; CI uses GoReleaser (see Releases)
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

Cross-compile targets: darwin/linux/windows × amd64/arm64 (see `docs/build-matrix.md`).
