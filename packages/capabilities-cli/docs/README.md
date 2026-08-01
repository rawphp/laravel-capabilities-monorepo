# capabilities CLI — documentation

User documentation for the downloadable **`capabilities`** binary
([`rawphp/capabilities-cli`](https://github.com/rawphp/capabilities-cli)).

The CLI is an **HTTP client** for a remote Laravel app that exposes the
`rawphp/laravel-capabilities` capability API. It does **not** run domain
business logic on your laptop.

## Docs map

| Doc | Audience | What it covers |
|-----|----------|----------------|
| [**User guide**](user-guide.md) | Everyone | Full manual — install through troubleshooting (start here) |
| [Authentication & profiles](authentication.md) | Multi-app users, agents | Login modes, **multi-project profiles**, storage layout, aliases |
| [Agents & MCP](agents.md) | Local agents, IDE bridges | Machine envelopes, exit codes, MCP stdio, discovery loop |
| [Installation](../README.md#install) | First-time install | One-liner + manual (also in user guide) |
| [Release path](release-path.md) | Maintainers | Tag → split → GitHub Release |
| [Release signing](release-signing.md) | Maintainers | Apple / Windows secrets |
| [Build matrix](build-matrix.md) | Maintainers | Cross-compile / ldflags |

## Five-minute path

```bash
# 1. Install (user-global, no sudo)
curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash
export PATH="$HOME/.local/bin:$PATH"

# 2. Login (one profile per product / deployment)
capabilities auth login --profile=myapp --base-url=https://app.example.com --token="$TOKEN"

# 3. Discover
capabilities catalog --profile=myapp

# 4. Invoke (names come from *your* server catalog)
capabilities run <capability.name> --profile=myapp --input='{}'
# or, when the catalog maps domain/verb:
# capabilities <domain> <verb> --profile=myapp --flag=value
```

## Package status

0.x pre-stable. Prefer [GitHub Release](https://github.com/rawphp/capabilities-cli/releases)
binaries; macOS assets may be Developer ID signed and notarized when secrets
are configured on the package repo.
