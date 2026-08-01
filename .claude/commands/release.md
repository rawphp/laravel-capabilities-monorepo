---
description: Monorepo release — quality gates, semver tag, package split + CLI GitHub Release.
argument-hint: "[patch|minor|major|vX.Y.Z] [--dry-run] [--squash] [-m MSG] [--yes]"
---

# /release — Laravel Capabilities monorepo release

Create a release for the **laravel-capabilities monorepo**.

**Ship path:** local gates → optional `--squash` (clean commit + force-with-lease branch) → annotated `v*` tag → `git push` tag → GitHub Actions `split-packages.yml` mirrors `packages/*` to public remotes and the CLI GoReleaser path builds GitHub Release binaries.

**Sole gate implementation:** `scripts/release.sh` is the only executable source of truth for quality gates, versioning, squash, tag, and push. This command only orchestrates UX (confirm with the user, pass an explicit bump, invoke the script, report). Do not re-run gates by hand and do not invent a second gate recipe.

## Arguments

Parse `$ARGUMENTS` (may be empty). Tokens map to `scripts/release.sh` flags:

| Token | Meaning |
|---|---|
| `patch` / `minor` / `major` | Semver bump from latest `v*` tag |
| `vX.Y.Z` | Explicit tag — must be strictly greater than latest when a latest exists |
| `--dry-run` | Preflight + gates only; do not tag or push |
| `--skip-php` | Skip `composer test` — **only with `--dry-run`** |
| `--skip-cli` | Skip `composer test:cli` — **only with `--dry-run`** |
| `--yes` | Non-interactive after you have already confirmed with the user |
| `--squash` | Soft-reset BASE..HEAD into **one clean commit**, then `git push --force-with-lease` the branch, then gates/tag. BASE = prior `v*` tag, or `origin/<branch>` when no tag yet (first release). Rewrites branch history — only use when intended. |
| `-m` / `--message MSG` | Squash commit message (requires `--squash`). Default: `Release <tag>`. |

### Version policy (explicit bump only)

Bump is **explicit**. Pass `patch`, `minor`, `major`, or `vX.Y.Z`.

- When the bump is omitted, **`scripts/release.sh` defaults to `patch`**.
- **Do not** invent `minor` / `major` from commit messages or heuristics.
- First release (no `v*` tags): `patch`/`minor` → `v0.1.0`, `major` → `v1.0.0`.

State the chosen version (and that it was explicit or script-default patch) in one sentence, then proceed.

## Hard rules

1. **Never** use `git push --force` (script uses `--force-with-lease` only with `--squash`). Never delete remote tags.
2. **Never** skip failing quality gates. Fix or abort — gates are whatever `scripts/release.sh` runs.
3. **Never** create a tag on a dirty working tree.
4. **Never** release from a branch other than `main` or `master`.
5. Side effects (tag + push, or squash rewrite) need **explicit user confirmation** in this turn unless they already said e.g. "release patch now" / "ship it" / passed `--yes` after agreeing.
6. Prefer **`--squash -m "…"`** when history since the last tag (or unpushed stack) is noisy merge/wip commits — one clean release commit.

## Procedure

### 1. Preflight (read-only)

From the monorepo root:

```bash
git rev-parse --show-toplevel
git status -sb
git rev-parse --abbrev-ref HEAD
git fetch origin --tags
git tag -l 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -5
git log --oneline -20
```

If dirty or not on main/master: stop and report.

### 2. Confirm the plan

Tell the user:

- latest tag (or "none — first release")
- proposed new tag
- gates: `composer test` + `composer test:cli` (see `./scripts/release.sh --help`)
- that the tag push triggers **package split** + **CLI GitHub Release** (not Forge)
- whether `--squash` will rewrite history

If they have not already approved shipping, ask once and wait.

### 3. Run the release script

Always invoke the in-repo script:

```bash
# Dry-run (gates only):
./scripts/release.sh --dry-run [patch|minor|major|vX.Y.Z]
# optional dry-run-only: --skip-php --skip-cli

# After confirmation — first cut example:
./scripts/release.sh --yes --squash -m "Pre-stable monorepo: core bus, messaging, CLI" v0.1.0

# Subsequent patch:
./scripts/release.sh --yes --squash patch
```

### 4. Report

On success:

```
Released vX.Y.Z
  commit: <short-sha>
  push:   tag (+ branch if --squash)
  next:   Actions split-packages → package remotes + CLI Releases
```

On gate failure: paste failing output, stop, do not tag.

### 5. Optional follow-ups (do not block)

- Watch GitHub Actions: Tests + Split packages.
- Confirm `rawphp/capabilities-cli` Releases has assets after tag mirror.
- Packagist remains a **human** checklist (`docs/versioning.md`) — do not claim Packagist publish from this command.
- CHANGELOG `[Unreleased]` → dated section is maintainer polish; only edit if the user asked in the same breath.

## Quality gates

**Do not duplicate gate commands here.** Authoritative:

```bash
./scripts/release.sh --help
./scripts/release.sh --dry-run
bash scripts/lib/test-release.sh   # ship-path self-test (no full suite)
```

CI parity: `.github/workflows/tests.yml` (`composer test`, `go test` via `composer test:cli`).

## Out of scope

- Packagist submit / webhooks (human — `docs/versioning.md`).
- Child-repo branch protection / deploy keys.
- Platform code-signing secrets for CLI (optional soft path).
