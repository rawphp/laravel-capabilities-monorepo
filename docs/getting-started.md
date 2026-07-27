# Getting started

Install the monorepo packages into a Laravel app, define one capability, invoke it through the registry (and HTTP), then optionally add messaging and the product CLI.

**Audience:** app integrators.  
**Time:** path install + first capability is the primary path; messaging and CLI are optional follow-ons.  
**Status:** 0.x pre-stable — not Packagist-published. Prefer path or pinned VCS over floating `*@dev` in production apps.

## Before you start

- PHP **^8.2**, Laravel **11 or 12** (`illuminate/*` as required by the core package)
- A clone of this monorepo (or access to its package paths)
- Composer in your app
- Optional later: Go **1.22+** (CLI), `laravel/ai` / `laravel/mcp` (agent/MCP surfaces), Telegram bot credentials (messaging)

Related: [Concepts](concepts.md) · [versioning.md](versioning.md) · [First capability tutorial](tutorials/first-capability.md)

## 1. Install the core package (path repository)

In your Laravel app’s `composer.json`, path-require core from a monorepo checkout (adjust `url` to your layout):

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-capabilities-monorepo/packages/laravel-capabilities",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "*@dev"
  }
}
```

```bash
composer update rawphp/laravel-capabilities
```

VCS require (`type: vcs`) is also supported when you point at a package remote; day-to-day contributor path is **path** install from this monorepo. Full policy and branch-alias notes: [versioning.md](versioning.md).

Do **not** expect `composer require rawphp/laravel-capabilities` from public Packagist until that residual is closed (see root README readiness table).

### Boot

Auto-discovery loads `Rawphp\Capabilities\CapabilitiesServiceProvider`.

Publish config when you need overrides:

```bash
php artisan vendor:publish --tag=capabilities-config
```

Default capability discovery path: `app/Capabilities` (`config/capabilities.php` → `path`).

## 2. Define and invoke your first capability

Follow the full walkthrough:

→ **[First capability tutorial](tutorials/first-capability.md)**

Short version:

1. Define input/output DTOs extending `Rawphp\Capabilities\Support\CapabilityData`.
2. Register with fluent `Capability::define(...)->register($registry)` **or** a `#[Capability]` class implementing `DefinesCapability` under the discovery path.
3. Put domain mutation only in **one** `run()`.
4. Invoke via `CapabilityRegistry::invoke` / `Capability` facade — every surface ends here.
5. When HTTP is enabled (default), the same `run()` is available under the single capability HTTP API (prefix `capabilities`).

## 3. Optional: agent and MCP peers

Agent and MCP surfaces compose `laravel/ai` and `laravel/mcp`. They are **optional peers**, not hard requires of core.

- Enable/disable via `config/capabilities.php` → `surfaces.agent` / `surfaces.mcp`.
- Supported version constraints live in `PeerSupportMatrix` (mirrored under `peers.support`).
- If a surface is enabled and the peer is missing or incompatible: **fail** boot (default) or **soft-disable** with CRITICAL log, per `on_incompatible`. Never half-register tools.

Details: [Core package guide](../packages/laravel-capabilities/docs/README.md#peers-laravelai--laravelmcp) and core package README.

## 4. Optional: messaging (Telegram)

Conversation surfaces are a **sibling package**, not core.

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-capabilities-monorepo/packages/laravel-capabilities-messaging",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "rawphp/laravel-capabilities-messaging": "*@dev"
  }
}
```

```bash
composer update rawphp/laravel-capabilities-messaging
php artisan vendor:publish --tag=capabilities-messaging-config
```

Messaging implements conversation ingress and approval notify contracts. Chat does **not** call domain `run()` itself — tools go through the capability registry. Configure Telegram secrets and an **agent profile** before first bot traffic.

→ [Messaging package guide](../packages/laravel-capabilities-messaging/docs/README.md)

## 5. Optional: product CLI on the user machine

The product CLI is a **downloadable Go HTTP client** (`capabilities`), not Artisan. It talks to the app’s same capability HTTP API.

```bash
cd packages/capabilities-cli
go test ./...
go build -o capabilities ./cmd/capabilities
```

```bash
./capabilities auth login --base-url=https://your-app.example
./capabilities catalog
./capabilities run create-invoice --input='{"customer_id":1,"amount_cents":2500,"currency":"USD"}' --json
```

→ [CLI package guide](../packages/capabilities-cli/docs/README.md)

## How you know it worked

- Core package resolves in Composer; app boots without peer/surface exceptions you did not intend.
- A capability appears in registry/catalog and `invoke` returns a `CapabilityResult` you can assert on.
- Optional: `POST /capabilities/{name}` with auth reaches the same `run()`.
- Optional: CLI `catalog` lists capabilities after `auth login`.
- Optional: Telegram webhook route responds when messaging is enabled and secrets are set (secrets are checked on first use, not at boot).

## If something goes wrong

See [Troubleshooting](troubleshooting.md). Common early blockers:

| Symptom | Where to look |
|---|---|
| Packagist / `composer require` fails | Expected until publish — use path/VCS ([versioning.md](versioning.md)) |
| Boot exception mentioning `laravel/ai` or `laravel/mcp` | Disable surface or install compatible peer |
| Capability not discovered | Path `app/Capabilities`, provider registration, one define style per name |
| CLI “missing base URL” / auth exit | `capabilities auth login --base-url=...` |

## Related

- [Concepts](concepts.md)
- [Documentation index](README.md)
- [Root README](../README.md) — monorepo status and residuals
