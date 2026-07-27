# Versioning and packaging

Monorepo packaging readiness for **Laravel Capabilities**. This document describes how packages
are versioned and how consumers install them **today**. It does **not** claim that packages are
published on Packagist or that stable release tags already exist.

## Packages and changelogs

| Package | Path | Artifact | Changelog |
|---|---|---|---|
| Core bus | `packages/laravel-capabilities` | Composer `rawphp/laravel-capabilities` | [CHANGELOG.md](../packages/laravel-capabilities/CHANGELOG.md) |
| Messaging | `packages/laravel-capabilities-messaging` | Composer `rawphp/laravel-capabilities-messaging` | [CHANGELOG.md](../packages/laravel-capabilities-messaging/CHANGELOG.md) |
| Product CLI | `packages/capabilities-cli` | Go module + binary `capabilities` | [CHANGELOG.md](../packages/capabilities-cli/CHANGELOG.md) |

Release notes live **per package**, Keep a Changelog style, with an `[Unreleased]` section and a
pre-stable `0.x` note until the first tagged release.

## 0.x pre-stable expectations

- While major version is **0**, the public surface is **pre-stable**: breaking changes may land
  without a `1.0.0` major bump (SemVer allows this on 0.x).
- Prefer pinning a **git commit/tag** or a **path** checkout over floating `*@dev` in production apps.
- Unit-tested monorepo green ≠ “API frozen for production consumers.” Treat the inventory and
  package tests as the behavioural contract; docs lag is called out honestly in the root README.
- `1.x` stability, Packagist versions, and signed CLI binaries are **future** — not asserted here.

## Install today (path / VCS)

Packages are developed in this monorepo. Preferred consumer paths until Packagist publish:

### Path repository (local monorepo or vendored checkout)

Root `composer.json` already path-requires the PHP packages for local work. In an app:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-capabilities/packages/laravel-capabilities",
      "options": { "symlink": true }
    },
    {
      "type": "path",
      "url": "../laravel-capabilities/packages/laravel-capabilities-messaging",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "*@dev",
    "rawphp/laravel-capabilities-messaging": "*@dev"
  }
}
```

Adjust `url` to your checkout layout. Path installs use symlink options when present.

### VCS repository (git)

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities"
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "dev-main"
  }
}
```

Composer monorepo layouts may require a subtree or path mapping depending on how the remote
repository is structured. Until a dedicated package repository or Packagist listing exists,
**path** install from a monorepo clone is the supported day-to-day path for contributors.

### Go CLI

```bash
cd packages/capabilities-cli
go test ./...
go build -o capabilities ./cmd/capabilities
```

Install from module path / built binary — not Composer. See `packages/capabilities-cli/README.md`.

## Composer version field and branch-alias

| Mechanism | Policy in this monorepo |
|---|---|
| `"version"` in package `composer.json` | **Not set.** Composer ignores or forbids fixed versions for VCS-published packages; tags (when created) define versions. |
| `extra.branch-alias` | **Set intentionally** on both PHP packages: `dev-main` → `0.x-dev` so path/VCS consumers resolve a stable-looking 0.x constraint without claiming a Packagist release. |
| Git tags | **Not created by packaging docs alone.** Tagging is a deliberate release step outside this readiness work. |
| Packagist | **Not claimed.** `composer require rawphp/...` from the public registry is aspirational until packages are submitted and accepted. Root README install snippets remain the intended end-state; this doc is the honest “how to install now.” |

Messaging’s `require` on core uses `"rawphp/laravel-capabilities": "*"` so path/symlink monorepo
resolution works; consumers should still pin once they leave the monorepo.

## What this REQ does **not** do

- No Packagist publish, API tokens, or `composer publish` automation.
- No git tags or GitHub Releases created solely for versioning readiness.
- No secrets, deploy keys, or live network release steps in-repo.

When a real release process lands, update this document, cut tags, fill dated CHANGELOG sections,
and only then claim public install via Packagist / binary distribution.
