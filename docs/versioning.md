# Versioning and packaging

How Laravel Capabilities packages are versioned and how consumers install them **today**. Coordinated monorepo `v*` tags and CLI GitHub Releases may already exist; this does **not** claim Packagist listings or a stable **1.x** public API.

## Packages, remotes, changelogs

| Package | Monorepo path | Public package repo | Artifact | Changelog |
|---|---|---|---|---|
| Core bus | `packages/laravel-capabilities` | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) | Composer `rawphp/laravel-capabilities` | [CHANGELOG](../packages/laravel-capabilities/CHANGELOG.md) |
| Messaging | `packages/laravel-capabilities-messaging` | [rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) | Composer `rawphp/laravel-capabilities-messaging` | [CHANGELOG](../packages/laravel-capabilities-messaging/CHANGELOG.md) |
| AI turns | `packages/laravel-capabilities-ai` | [rawphp/laravel-capabilities-ai](https://github.com/rawphp/laravel-capabilities-ai) | Composer `rawphp/laravel-capabilities-ai` | [CHANGELOG](../packages/laravel-capabilities-ai/CHANGELOG.md) |
| Product CLI | `packages/capabilities-cli` | [rawphp/capabilities-cli](https://github.com/rawphp/capabilities-cli) | Go module + binary `capabilities` | [CHANGELOG](../packages/capabilities-cli/CHANGELOG.md) |

Release notes live **per package**, Keep a Changelog style, with an `[Unreleased]` section and a pre-stable `0.x` note until the first tagged release.

**Umbrella:** this monorepo is not an install target. Product boundaries per package: root [README — Scope](../README.md#scope-product-boundary).

## Monorepo → four package remotes (on push)

Source of truth for day-to-day development is this monorepo. Publication of package trees is automated:

| Trigger | What happens |
|---|---|
| Push to monorepo `main` | [`.github/workflows/split-packages.yml`](../.github/workflows/split-packages.yml) rsyncs each `packages/<name>/` tree into the matching public repo’s `main` (package root becomes repo root) |
| Push monorepo tag `v*` | Same workflow force-updates that tag on **each** package remote (for Packagist / releases) |
| Manual | `workflow_dispatch` on the same workflow |

### Test gate (split blocked until green)

Split / package-remote publish is **gated on green monorepo unit tests**. The split workflow’s `split` job `needs:` a reusable call to [`.github/workflows/tests.yml`](../.github/workflows/tests.yml) (PHP 8.2 Pest via root `composer test` = core + messaging + AI, and `go test ./...` for the CLI). If any unit suite fails, package trees and tags are **not** mirrored.

| Surface | CI |
|---|---|
| PR + push to `main` | `tests.yml` runs unit suites standalone |
| Split (`main`, `v*` tags, `workflow_dispatch`) | Same suites via `workflow_call` before any rsync / tag force-push |
| Coverage floor / Packagist API | **Not** enforced here (unit exit codes only; Packagist remains human checklist) |

Tag concurrency uses `cancel-in-progress: false` for tags so a release mirror is not aborted mid-matrix (partial package remotes). Branch runs may still cancel superseded work.

**Implications:**

- Consumer VCS installs and Packagist submissions use the **package repos**, not `laravel-capabilities-monorepo`.
- Package `README.md`, `docs/user-guide.md`, and `CHANGELOG.md` must work standalone after split (no relative links into monorepo-only `docs/`).
- Monorepo design docs (`docs/spec.md`, tutorials, inventory) stay monorepo-only; link them with absolute monorepo URLs if a package doc needs them.

Setup (repo secrets / empty package remotes) is documented in the workflow file header.

## Local release command (maintainer)

**Sole gate + tag + push path:** [`scripts/release.sh`](../scripts/release.sh) (agent UX: `.claude/commands/release.md` / `/release`).

| Step | What it does |
|---|---|
| Preflight | `main`/`master` only, clean tree, fetch tags, `HEAD` vs `origin` rules |
| Version | `patch` / `minor` / `major` / explicit `vX.Y.Z` (first release: patch/minor → `v0.1.0`) |
| Optional `--squash` | Soft-reset BASE..HEAD into one clean commit (`-m` message), `git push --force-with-lease` branch. BASE = prior `v*` tag, or `origin/<branch>` when no tag yet |
| Gates | `composer test` (core + messaging Pest) + `composer test:cli` (`go test ./...`) — same suites as CI |
| Tag + push | Annotated monorepo `v*` tag → `git push origin refs/tags/…` → split workflow + CLI GoReleaser |

```bash
# Dry-run (gates only; no tag/push):
./scripts/release.sh --dry-run

# First public cut (example):
./scripts/release.sh --yes --squash -m "Pre-stable monorepo: core, messaging, CLI" v0.1.0

# Ship-path self-test (no full suites / no network):
bash scripts/lib/test-release.sh
```

Do **not** hand-roll a parallel tag path. Packagist submit remains the human checklist below; the script does not publish to Packagist.

## 0.x pre-stable expectations

- While major version is **0**, the public surface is **pre-stable**: breaking changes may land without a `1.0.0` major bump (SemVer allows this on 0.x).
- Prefer pinning a **git commit/tag** or a **path** checkout over floating `*@dev` in production apps.
- Unit-tested monorepo green ≠ “API frozen for production consumers.” Treat the inventory and package tests as the behavioural contract.
- `1.x` stability, Packagist versions, and signed CLI binaries are **future** — not asserted here.

## Install today

### Path repository (monorepo contributors / local symlink)

Root `composer.json` path-requires the PHP packages for local work. In an app next to a monorepo clone:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-capabilities-monorepo/packages/laravel-capabilities",
      "options": { "symlink": true }
    },
    {
      "type": "path",
      "url": "../laravel-capabilities-monorepo/packages/laravel-capabilities-messaging",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "*@dev",
    "rawphp/laravel-capabilities-messaging": "*@dev"
  }
}
```

Adjust `url` to your checkout layout.

### VCS repository (package remotes — preferred for app integrators)

Point Composer at the **split package repos** (updated on monorepo push):

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities"
    },
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities-messaging"
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "dev-main",
    "rawphp/laravel-capabilities-messaging": "dev-main"
  }
}
```

Do **not** VCS-require the monorepo URL expecting Composer to find nested package roots — Packagist and Composer expect `composer.json` at the repository root, which is true on the package remotes after split.

### Go CLI

```bash
# from monorepo
cd packages/capabilities-cli
# or clone https://github.com/rawphp/capabilities-cli
go test ./...
go build -o capabilities ./cmd/capabilities
```

Install from module path / built binary — not Composer. See the CLI package README.

## Composer version field and branch-alias

| Mechanism | Policy |
|---|---|
| `"version"` in package `composer.json` | **Not set.** Tags (when created) define versions for VCS/Packagist. |
| `extra.branch-alias` | **Set** on both PHP packages: `dev-main` → `0.x-dev`. |
| Git tags | Human-gated on the monorepo; mirrored to package remotes by the split workflow. |
| Packagist | **Not claimed** until human submit + first tag. Root README install snippets are the intended end-state. |

Messaging’s `require` on core uses `"rawphp/laravel-capabilities": "*"` so path/symlink monorepo resolution works; consumers should pin once they leave path install.

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

- Do **not** set a top-level `"version"` field in package `composer.json`.
- Do **not** alias `dev-main` to a concrete `0.Y.Z`.
- Go CLI has no Composer `branch-alias`; its version story is git tags + module path.

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
| **Scope** | One coordinated monorepo tag. The split workflow mirrors the same tag name onto each package remote. |
| **Not used (v0.x)** | Package-scoped tags such as `laravel-capabilities/v0.1.0`. |
| **Current line** | Monorepo already has coordinated `v*` tags (through at least `v0.4.0`); next cut is the next SemVer step, not a first-ever tag. |
| **Annotated preferred** | `git tag -a v0.Y.Z -m "…"` on the monorepo. |
| **Push** | Tag create/push on the monorepo are human-gated; package remotes receive the tag via CI. |

### CHANGELOG ↔ tag handoff (Keep a Changelog)

Per package `CHANGELOG.md`:

1. Keep a top **`## [Unreleased]`** section while work lands on `main`.
2. When cutting tag `v0.Y.Z`, **move** Unreleased bullets into a new dated section:

   ```markdown
   ## [0.Y.Z] - YYYY-MM-DD
   ```

   (section title uses **no** leading `v`; the git tag keeps the `v` prefix.)
3. Leave `## [Unreleased]` empty (or with only “Notes”) for the next cycle.
4. Keep the **`## [0.x] — pre-stable`** policy banner until `1.0.0` is intentional.
5. Update footer compare/release links only after the tag exists on the **package** remote.

## Packagist + git tag publish checklist (human steps)

**State:** Packagist publish remains a **residual** until a maintainer completes the manual checks below.
Automated package CI stays **unit-only** and must **not** call Packagist, create tags, or hold API tokens.

### Package names and publish surface

| Composer / artifact name | Monorepo path | Publish surface | VCS URL for Packagist |
|---|---|---|---|
| `rawphp/laravel-capabilities` | `packages/laravel-capabilities` | Packagist (Composer) | `https://github.com/rawphp/laravel-capabilities` |
| `rawphp/laravel-capabilities-messaging` | `packages/laravel-capabilities-messaging` | Packagist (Composer) | `https://github.com/rawphp/laravel-capabilities-messaging` |
| `rawphp/capabilities-cli` (Go / binary `capabilities`) | `packages/capabilities-cli` | **Not Packagist** | GitHub Releases / install docs |

**Monorepo path layout (already solved by split):**

- Submit **package repo** URLs to Packagist (composer.json at repo root after split). Do **not** submit the monorepo URL as the package VCS.
- Messaging depends on core — publish **core first**, then messaging.
- Do not invent a third Composer package name.

### Human checklist (maintainer)

1. **Prep**
   - [ ] `branch-alias` remains `dev-main` → `0.x-dev` on both PHP packages (no top-level `"version"`).
   - [ ] CHANGELOGs: move `[Unreleased]` into a dated `## [0.Y.Z]` section before the tag.
   - [ ] Confirm monorepo unit suites green (`composer test:core`, messaging as needed).
   - [ ] Confirm split workflow has mirrored `main` to package remotes (and `SPLIT_GITHUB_TOKEN` is set).

2. **Packagist submit (each PHP package)**
   - [ ] Log into [Packagist](https://packagist.org/) with the org/maintainer account that owns `rawphp/*`.
   - [ ] **Submit package** for `rawphp/laravel-capabilities` → package repo URL above.
   - [ ] **Submit package** for `rawphp/laravel-capabilities-messaging` the same way (after core is listed).
   - [ ] Confirm package names match `composer.json` `name` fields exactly.

3. **VCS linkage**
   - [ ] Packagist shows the correct package git remote and default branch (`main`).
   - [ ] Package repositories are public (or Packagist has access).

4. **Auto-update webhook**
   - [ ] On each **package** git host, add Packagist’s auto-update webhook (or GitHub integration).
   - [ ] Fire a test push or use Packagist “Update” once to confirm the hook works.

5. **First git tag**
   - [ ] Prefer `./scripts/release.sh --yes [--squash -m "…"] v0.Y.Z` (runs gates, annotated tag, push). First target often `v0.1.0`.
   - [ ] Manual fallback only if needed: `git tag -a v0.Y.Z -m "…"` then `git push origin v0.Y.Z` — still require green `composer test` + `composer test:cli` first.
   - [ ] Split workflow mirrors the tag to package remotes; do **not** treat tag create as automated by monorepo unit CI alone.

6. **Verify public install**
   - [ ] After Packagist indexes the tag: `composer show rawphp/laravel-capabilities` (and messaging) resolves a version.
   - [ ] On a **clean** Laravel app (no path repository): `composer require rawphp/laravel-capabilities` succeeds.
   - [ ] Only then update root README / consumer docs to drop “not on Packagist” residual wording for those packages.

7. **CLI binary distribution (not Packagist)**
   - Go CLI is **not** published via Packagist.
   - **Automated download path exists:** monorepo git tag `v*` → split mirrors into
     `rawphp/capabilities-cli` → package-owned GoReleaser workflow creates/updates a
     **GitHub Release** with multi-arch `capabilities` archives + checksums
     (unsigned by default; see package `docs/release-signing.md`).
   - **Signed** binaries remain residual **only while** child-repo platform-signing secrets
     are not configured (secret-gated soft path still publishes unsigned assets).
   - Source build / ad-hoc cross-compile stay documented for contributors; they are no longer
     the sole distribution path.

### Residual marker

| Surface | Residual until |
|---|---|
| Packagist listing for `rawphp/laravel-capabilities` | Human completes submit + webhook + first tag + `composer show` / clean `composer require` |
| Packagist listing for `rawphp/laravel-capabilities-messaging` | Same, after core (dependency order) |
| Downloadable `capabilities` CLI binary (unsigned multi-arch) | **Closed for automation** — GitHub Releases on `rawphp/capabilities-cli` after monorepo `v*` tag + split (GoReleaser). First public assets still need a human to cut/push a monorepo tag. |
| **Signed** `capabilities` CLI binary (macOS/Windows) | Child-repo signing secrets configured and verified on a real tag (secret-gated; unsigned publish still works without them) |

Until those human steps finish, **Packagist remains residual**. Root README consumer-readiness table must keep packaging as residual. Unit tests must not depend on Packagist availability.

## Durable persistence path (core)

Durable approval / idempotency in a host app is a **config + migrations** path — not Packagist-gated.

| Piece | Where |
|---|---|
| Default gateway for database drivers | `Rawphp\Capabilities\Persistence\QueryTableGateway` (Illuminate query builder) |
| Config | `approval.store`, `approval.connection`, `idempotency.driver`, `idempotency.connection` in published `config/capabilities.php` |
| Migrations tag | `php artisan vendor:publish --tag=capabilities-migrations` |
| Tables | `capabilities_approvals`, `capabilities_idempotency` (`MigrationCatalog`) |
| Host override | Bind `TableGateway` in `AppServiceProvider` (see [first-capability tutorial](tutorials/first-capability.md#durable-stores-approvals--idempotency)) |

Package defaults: `approval.store` = `database`; `idempotency.driver` = `database` (aligned with approval for multi-worker durability). Set either to `memory` only for single-process tests. Missing connection on a database driver fails closed (no silent `ArrayTableGateway`). Full host walkthrough: [first-capability tutorial](tutorials/first-capability.md). Package notes: [core package README](../packages/laravel-capabilities/README.md#durable-persistence-querytablegateway).

This does **not** mean packages are on Packagist — install remains path or package-repo VCS until the human Packagist checklist above is complete.

## What this prep work does **not** do

- No Packagist publish, API tokens, or `composer publish` automation.
- No git tags created solely by versioning docs (tags are cut by humans / `scripts/release.sh`).
- No secrets, deploy keys, or live network release steps in-repo (split uses a configured `SPLIT_GITHUB_TOKEN` secret only).
- No claim that packages are already on **Packagist** (Composer `composer require` without VCS/path remains residual).
- No monorepo unit test that hits `packagist.org` or requires `composer show` against the public registry.

**Tags vs Packagist:** coordinated monorepo `v*` tags and package-remote mirrors (plus CLI GitHub Releases) may already exist. That does **not** mean Packagist listings are complete. Complete the **Packagist + git tag publish checklist** before claiming public install via Packagist; fill dated CHANGELOG sections when cutting each tag.

## AI package residuals (honesty)

`rawphp/laravel-capabilities-ai` is a library: host must bind context/tool seams; Packagist submit remains a **human** checklist after first monorepo `v*` tag mirrors the package remote. See UR-018/021 for HTTP/queue surface work and package CHANGELOG for Unreleased notes.
