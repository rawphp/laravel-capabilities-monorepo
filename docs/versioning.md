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
| Git tags | **Not created by packaging docs alone.** Tagging is a deliberate, human-gated release step (see **Git tag naming** below). |
| Packagist | **Not claimed.** `composer require rawphp/...` from the public registry is aspirational until packages are submitted and accepted. Root README install snippets remain the intended end-state; this doc is the honest “how to install now.” |

Messaging’s `require` on core uses `"rawphp/laravel-capabilities": "*"` so path/symlink monorepo
resolution works; consumers should still pin once they leave the monorepo.

### Branch-alias consistency (0.x-dev policy)

Both Composer packages **must** keep:

```json
"extra": {
  "branch-alias": {
    "dev-main": "0.x-dev"
  }
}
```

| Package | Path | Expected alias |
|---|---|---|
| Core | `packages/laravel-capabilities/composer.json` | `dev-main` → `0.x-dev` |
| Messaging | `packages/laravel-capabilities-messaging/composer.json` | `dev-main` → `0.x-dev` |

- Do **not** set a top-level `"version"` field in package `composer.json` while publishing from VCS/path.
- Do **not** alias `dev-main` to a concrete `0.Y.Z` (that freezes the line incorrectly); keep the open `0.x-dev` line alias until a major-line change is intentional.
- Go CLI has no Composer `branch-alias`; its version story is git tags + module path only (see tag naming).

## Git tag naming (exact pattern)

**Exact tag name pattern (monorepo-wide, coordinated):**

```text
v0.<minor>.<patch>
```

Examples: `v0.1.0`, `v0.1.1`, `v0.2.0`.

| Rule | Detail |
|---|---|
| **Prefix** | Leading lowercase `v` is required (`v0.1.0`, not `0.1.0`). |
| **SemVer body** | `0.Y.Z` only while pre-stable (major stays `0`). |
| **Scope** | **One coordinated monorepo tag** at the repository root for a release train. All three artifacts (core, messaging, CLI) share that tag when cut together. |
| **Not used (v0.x prep)** | Package-scoped tags such as `laravel-capabilities/v0.1.0` or `packages/laravel-capabilities@0.1.0` — out of scope for the first pre-stable line. Revisit only if packages split to separate remotes. |
| **First pre-stable target** | `v0.1.0` when a human deliberately cuts the first tag (not automated by this monorepo alone). |
| **Annotated preferred** | Prefer annotated tags: `git tag -a v0.1.0 -m "Pre-stable 0.1.0"`. |
| **Push / Packagist** | Tag **create and push** are human-gated. This document does **not** create tags or publish to Packagist. |

### CHANGELOG ↔ tag handoff (Keep a Changelog)

Per package `CHANGELOG.md`:

1. Keep a top **`## [Unreleased]`** section while work lands on `main`.
2. When cutting tag `v0.Y.Z`, **move** Unreleased bullets into a new dated section:

   ```markdown
   ## [0.Y.Z] - YYYY-MM-DD
   ```

   (section title uses **no** leading `v`; the git tag keeps the `v` prefix.)
3. Leave `## [Unreleased]` empty (or with only “Notes”) for the next cycle.
4. Keep the **`## [0.x] — pre-stable monorepo`** policy banner until `1.0.0` is intentional — it is **not** a substitute for a concrete `0.Y.Z` section.
5. Update footer compare/release links only after the tag exists on the remote; do not invent release URLs before the first push.

Go CLI versioning is documented here only (CLI layer implementation out of scope for prep REQs): the same monorepo tag `v0.Y.Z` is the release marker; binary embedding of that string is a later release step.

## What this prep work does **not** do

- No Packagist publish, API tokens, or `composer publish` automation.
- No git tags or GitHub Releases created solely for versioning readiness.
- No secrets, deploy keys, or live network release steps in-repo.
- No claim that packages are already on Packagist or that tags already exist on a public remote.

When a real release process lands, cut tags using the pattern above, fill dated CHANGELOG sections,
and only then claim public install via Packagist / binary distribution.
