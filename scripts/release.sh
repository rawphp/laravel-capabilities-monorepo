#!/usr/bin/env bash
# Laravel Capabilities monorepo release: quality gates → annotated tag → push.
#
# Source of truth for day-to-day work is this monorepo. Publication path:
#   monorepo tag v*  →  .github/workflows/split-packages.yml
#     → mirrors packages/* to public remotes (core, messaging, CLI)
#     → capabilities-cli package-owned GoReleaser builds GitHub Release binaries
#
# Without --squash this script never force-pushes a branch. Real releases require
# HEAD == origin/$BRANCH (unless --squash rewrites the tip). Tag push alone is
# enough to trigger split; branch tip should still match the tagged commit.
#
# Usage:
#   scripts/release.sh [--dry-run] [--yes] [--skip-php] [--skip-cli]
#                      [--allow-empty-range] [--squash] [-m|--message MSG]
#                      [patch|minor|major|vX.Y.Z]
#
# Defaults: bump patch from the max of local+origin vX.Y.Z tags (or first-release
# table if none: patch/minor → v0.1.0, major → v1.0.0).
#
# Flow:
#   preflight (main/master + clean + fetch tags/branch + HEAD/origin rules) →
#   version resolve → plan (commits-since / empty-range refuse) →
#   (confirm if interactive) → optional --squash (soft-reset + force-with-lease) →
#   gates → porcelain re-check → tag → push tag (and branch if not already equal)
# Dry-run stops after gates (no squash rewrite, no tag/push). Confirm is never
# after gates. Real releases hard-fail if origin/$BRANCH is missing after fetch
# (dry-run may warn). Empty range (prior tag + 0 commits since) hard-fails
# unless --allow-empty-range. If tag push fails after create, the local tag is
# deleted so re-run is clean.
#
# --squash: collapse BASE..HEAD into one clean commit (default message
# "Release $NEW_TAG", override with -m/--message), then
# git push --force-with-lease origin $BRANCH so origin tip matches.
# BASE = prior v* tag when one exists; otherwise origin/$BRANCH (first-release /
# unpushed-stack squash). Never force-pushes tags.
#
# Quality gates (when not skipped; skip flags are dry-run only):
#   1. composer test          (Pest core + messaging)
#   2. composer test:cli      (go test ./... under packages/capabilities-cli)
#
# Matches CI: .github/workflows/tests.yml (split is blocked until these are green).

set -euo pipefail

DRY_RUN=0
ASSUME_YES=0
SKIP_PHP=0
SKIP_CLI=0
ALLOW_EMPTY_RANGE=0
SQUASH=0
SQUASH_MESSAGE=""
BUMP="patch"

usage() {
  cat <<'EOF'
Usage: scripts/release.sh [options] [patch|minor|major|vX.Y.Z]

Quality-gate a Laravel Capabilities monorepo release, create an annotated
semver tag, and push the tag to origin (triggers package split + CLI release).
With --squash, also rewrites BASE..HEAD into one clean commit and
force-with-lease pushes the branch so origin tip matches the tagged tree.

Options:
  --dry-run            Run preflight + gates; print the tag that would be created
                       (no squash rewrite, no tag, no push, no confirm prompt)
  --yes                Skip the interactive confirmation prompt (agent / CI path)
  --skip-php           Skip composer test (core + messaging) — ONLY with --dry-run
  --skip-cli           Skip composer test:cli — ONLY with --dry-run
  --allow-empty-range  Allow release when a prior tag exists and HEAD has zero
                       commits since that tag (default: hard refuse empty range)
  --squash             Soft-reset BASE..HEAD into one clean commit, then
                       git push --force-with-lease origin <branch> before gates.
                       BASE = latest v* tag, or origin/<branch> when no tag yet.
                       Never force-pushes tags.
  -m, --message MSG    Commit message for --squash (default: "Release <tag>")
  -h, --help           Show this help

Version argument:
  patch (default)  v0.1.0 → v0.1.1  (first release: v0.1.0)
  minor            v0.1.0 → v0.2.0  (first release: v0.1.0)
  major            v0.1.0 → v1.0.0  (first release: v1.0.0)
  vX.Y.Z           explicit tag — must be strictly greater than latest when
                   a latest tag exists (hard refuse; no override)

Examples:
  scripts/release.sh --dry-run
  scripts/release.sh --dry-run --skip-php --skip-cli
  scripts/release.sh minor
  scripts/release.sh --yes v0.1.0
  scripts/release.sh --yes --squash -m "First pre-stable monorepo release" v0.1.0
  scripts/release.sh --yes --squash patch
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --yes|-y) ASSUME_YES=1; shift ;;
    --skip-php) SKIP_PHP=1; shift ;;
    --skip-cli) SKIP_CLI=1; shift ;;
    --allow-empty-range) ALLOW_EMPTY_RANGE=1; shift ;;
    --squash) SQUASH=1; shift ;;
    -m|--message)
      if [[ $# -lt 2 || -z "${2:-}" ]]; then
        echo "error: $1 requires a non-empty message" >&2
        usage >&2
        exit 2
      fi
      SQUASH_MESSAGE="$2"
      shift 2
      ;;
    -h|--help) usage; exit 0 ;;
    patch|minor|major) BUMP="$1"; shift ;;
    v[0-9]*.[0-9]*.[0-9]*)
      if [[ ! "$1" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "error: invalid version argument: $1 (expected patch|minor|major|vX.Y.Z)" >&2
        usage >&2
        exit 2
      fi
      BUMP="$1"
      shift
      ;;
    *)
      echo "error: unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

if [[ -n "$SQUASH_MESSAGE" && "$SQUASH" -eq 0 ]]; then
  echo "error: -m/--message requires --squash" >&2
  exit 2
fi

# Skip flags are dry-run convenience only. Real releases always run all gates.
if [[ "$SKIP_PHP" -eq 1 && "$DRY_RUN" -eq 0 ]]; then
  echo "error: --skip-php is only allowed with --dry-run (real releases must run PHP gates)" >&2
  exit 1
fi
if [[ "$SKIP_CLI" -eq 1 && "$DRY_RUN" -eq 0 ]]; then
  echo "error: --skip-cli is only allowed with --dry-run (real releases must run CLI gates)" >&2
  exit 1
fi

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [[ -z "$ROOT" ]]; then
  echo "error: not inside a git repository" >&2
  exit 1
fi
cd "$ROOT"

log() { printf '==> %s\n' "$*"; }
fail() { printf 'error: %s\n' "$*" >&2; exit 1; }

# --- preflight ----------------------------------------------------------------

log "Preflight"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$BRANCH" != "main" && "$BRANCH" != "master" ]]; then
  fail "release from main/master only (current branch: $BRANCH)"
fi

if [[ -n "$(git status --porcelain)" ]]; then
  git status --short
  fail "working tree is dirty — commit or stash before releasing"
fi

log "Fetching tags and branch tip from origin"
if ! git fetch origin --tags --quiet; then
  fail "git fetch origin --tags failed — cannot resolve latest version safely"
fi
git fetch origin "$BRANCH" --quiet 2>/dev/null || true

HEAD_SHA="$(git rev-parse HEAD)"
HEAD_SHORT="$(git rev-parse --short HEAD)"
log "HEAD $HEAD_SHORT on $BRANCH"

# Without --squash, HEAD must already equal origin/$BRANCH so the tagged commit
# is what package remotes / consumers see after split. With --squash, history is
# rewritten and force-with-lease pushed so the tip matches before tagging.
if git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
  ORIGIN_SHA="$(git rev-parse "origin/$BRANCH")"
  if [[ "$ORIGIN_SHA" != "$HEAD_SHA" ]]; then
    if git merge-base --is-ancestor "$HEAD_SHA" "origin/$BRANCH"; then
      fail "HEAD ($HEAD_SHORT) is behind origin/$BRANCH — checkout latest or pick the tip to tag"
    fi
    if git merge-base --is-ancestor "origin/$BRANCH" "$HEAD_SHA"; then
      if [[ "$SQUASH" -eq 1 ]]; then
        git log --oneline "origin/$BRANCH..HEAD" | head -10
        log "HEAD is ahead of origin/$BRANCH — --squash will rewrite BASE..HEAD and force-with-lease push"
      else
        git log --oneline "origin/$BRANCH..HEAD" | head -10
        fail "HEAD is ahead of origin/$BRANCH — push the branch first (or re-run with --squash)"
      fi
    else
      fail "HEAD is not on origin/$BRANCH — cannot safely release"
    fi
  else
    log "origin/$BRANCH is at $HEAD_SHORT (branch tip matches tag base)"
  fi
else
  if [[ "$DRY_RUN" -eq 0 ]]; then
    fail "origin/$BRANCH not found after fetch — push the branch before a real release (or use --dry-run)"
  fi
  log "warning: origin/$BRANCH not found locally after fetch — ensure the branch is pushed before tagging"
fi

# --- version ------------------------------------------------------------------

# Max of local and origin strict vX.Y.Z tags (after successful fetch). Empty if none.
latest_tag() {
  local local_tag remote_tag
  local_tag="$(git tag -l 'v[0-9]*.[0-9]*.[0-9]*' \
    | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
    | sort -V \
    | tail -1 || true)"
  remote_tag="$(git ls-remote --tags --refs origin 'v*' 2>/dev/null \
    | awk '{print $2}' \
    | sed 's#refs/tags/##' \
    | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
    | sort -V \
    | tail -1 || true)"

  if [[ -z "$local_tag" && -z "$remote_tag" ]]; then
    printf ''
    return
  fi
  if [[ -z "$local_tag" ]]; then
    printf '%s' "$remote_tag"
    return
  fi
  if [[ -z "$remote_tag" ]]; then
    printf '%s' "$local_tag"
    return
  fi
  printf '%s\n%s\n' "$local_tag" "$remote_tag" | sort -V | tail -1
}

next_version() {
  local current="$1" kind="$2"
  if [[ "$kind" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    printf '%s' "$kind"
    return
  fi

  if [[ -z "$current" ]]; then
    case "$kind" in
      major) printf 'v1.0.0' ;;
      minor) printf 'v0.1.0' ;;
      patch) printf 'v0.1.0' ;;
      *) fail "unknown bump: $kind" ;;
    esac
    return
  fi

  if [[ ! "$current" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    fail "latest tag is not strict vX.Y.Z: $current (cannot bump safely)"
  fi

  local major minor patch
  IFS=. read -r major minor patch <<<"${current#v}"
  case "$kind" in
    major) major=$((major + 1)); minor=0; patch=0 ;;
    minor) minor=$((minor + 1)); patch=0 ;;
    patch) patch=$((patch + 1)) ;;
    *) fail "unknown bump: $kind" ;;
  esac
  printf 'v%s.%s.%s' "$major" "$minor" "$patch"
}

# True (exit 0) when $1 is strictly greater than $2 (vX.Y.Z form).
version_strictly_greater() {
  local candidate="$1" base="$2"
  if [[ "$candidate" == "$base" ]]; then
    return 1
  fi
  local higher
  higher="$(printf '%s\n%s\n' "$base" "$candidate" | sort -V | tail -1)"
  [[ "$higher" == "$candidate" ]]
}

CURRENT_TAG="$(latest_tag)"
if [[ -z "$CURRENT_TAG" ]]; then
  log "No existing v* tags — first release"
else
  log "Latest tag (local+origin max): $CURRENT_TAG"
fi

NEW_TAG="$(next_version "$CURRENT_TAG" "$BUMP")"
[[ "$NEW_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "invalid version: $NEW_TAG"

if [[ "$BUMP" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] && [[ -n "$CURRENT_TAG" ]]; then
  if ! version_strictly_greater "$NEW_TAG" "$CURRENT_TAG"; then
    fail "explicit tag $NEW_TAG is not strictly greater than latest $CURRENT_TAG (hard refuse; pick a higher version)"
  fi
fi

if git rev-parse -q --verify "refs/tags/$NEW_TAG" >/dev/null; then
  fail "tag already exists locally: $NEW_TAG"
fi
if git ls-remote --tags --refs origin "refs/tags/$NEW_TAG" 2>/dev/null | grep -q .; then
  fail "tag already exists on origin: $NEW_TAG"
fi

# --- plan (before confirm / gates) --------------------------------------------

if [[ "$SQUASH" -eq 1 && -z "$SQUASH_MESSAGE" ]]; then
  SQUASH_MESSAGE="Release $NEW_TAG"
fi

# Squash base: prior tag, or origin/$BRANCH for first-release / unpushed stack.
SQUASH_BASE=""
if [[ "$SQUASH" -eq 1 ]]; then
  if [[ -n "$CURRENT_TAG" ]]; then
    SQUASH_BASE="$CURRENT_TAG"
  elif git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
    SQUASH_BASE="origin/$BRANCH"
  else
    fail "--squash needs a base (prior v* tag or origin/$BRANCH) — none available"
  fi
fi

log "Release plan"
printf '  tag:     %s\n' "$NEW_TAG"
printf '  from:    %s\n' "${CURRENT_TAG:-none (first release)}"
printf '  commit:  %s (%s)\n' "$HEAD_SHORT" "$HEAD_SHA"
printf '  branch:  %s\n' "$BRANCH"
if [[ "$SQUASH" -eq 1 ]]; then
  printf '  remote:  origin (squash: force-with-lease branch, then tag)\n'
else
  printf '  remote:  origin (tag only — no branch push)\n'
fi
printf '  dry-run: %s\n' "$([[ "$DRY_RUN" -eq 1 ]] && echo yes || echo no)"
printf '  squash:  %s\n' "$([[ "$SQUASH" -eq 1 ]] && echo yes || echo no)"
if [[ "$SQUASH" -eq 1 ]]; then
  printf '  squash base:    %s\n' "$SQUASH_BASE"
  printf '  squash message: %s\n' "$SQUASH_MESSAGE"
fi
printf '  gates:\n'
if [[ "$SKIP_PHP" -eq 0 ]]; then
  printf '    - php: composer test (core + messaging Pest)\n'
else
  printf '    - php: SKIPPED (--skip-php, dry-run only)\n'
fi
if [[ "$SKIP_CLI" -eq 0 ]]; then
  printf '    - cli: composer test:cli (go test ./...)\n'
else
  printf '    - cli: SKIPPED (--skip-cli, dry-run only)\n'
fi
printf '  after tag: split-packages.yml → package remotes + CLI GitHub Release\n'

commit_count=0
RANGE_BASE="${CURRENT_TAG:-}"
if [[ -n "$RANGE_BASE" ]]; then
  commit_count="$(git rev-list --count "${RANGE_BASE}..HEAD" 2>/dev/null || echo 0)"
  printf '  commits since %s:\n' "$RANGE_BASE"
  local_log="$(git log --oneline "${RANGE_BASE}..HEAD" 2>/dev/null || true)"
  if [[ -z "$local_log" ]]; then
    printf '    (none — HEAD is at or behind %s)\n' "$RANGE_BASE"
  else
    printf '%s\n' "$local_log" | head -40 | sed 's/^/    /'
    if [[ "${commit_count}" -gt 40 ]]; then
      printf '    … (%s total)\n' "$commit_count"
    fi
  fi
  if [[ "${commit_count}" -eq 0 && "$ALLOW_EMPTY_RANGE" -eq 0 ]]; then
    fail "empty range: zero commits since ${RANGE_BASE} — refuse release (pass --allow-empty-range to override)"
  fi
else
  printf '  commits: no prior tag — recent history:\n'
  git log --oneline -20 | sed 's/^/    /' || true
  if [[ "$SQUASH" -eq 1 && -n "$SQUASH_BASE" ]]; then
    commit_count="$(git rev-list --count "${SQUASH_BASE}..HEAD" 2>/dev/null || echo 0)"
    printf '  commits since squash base %s: %s\n' "$SQUASH_BASE" "$commit_count"
  fi
fi

if [[ "$SQUASH" -eq 1 ]]; then
  if ! git merge-base --is-ancestor "$SQUASH_BASE" HEAD 2>/dev/null \
    && ! git rev-parse --verify "$SQUASH_BASE" >/dev/null 2>&1; then
    fail "--squash base $SQUASH_BASE is not resolvable"
  fi
  # origin/BRANCH is always an ancestor when HEAD is ahead; tag must be ancestor.
  if [[ "$SQUASH_BASE" == origin/* ]]; then
    if ! git merge-base --is-ancestor "$SQUASH_BASE" HEAD; then
      fail "--squash base $SQUASH_BASE is not an ancestor of HEAD"
    fi
  elif ! git merge-base --is-ancestor "$SQUASH_BASE" HEAD; then
    fail "--squash base $SQUASH_BASE is not an ancestor of HEAD"
  fi
  squash_count="$(git rev-list --count "${SQUASH_BASE}..HEAD" 2>/dev/null || echo 0)"
  if [[ "${squash_count}" -eq 0 ]]; then
    fail "--squash: zero commits since ${SQUASH_BASE} — nothing to squash"
  fi
  commit_count="$squash_count"
fi

# --- confirm (interactive, before gates; never for dry-run / --yes) -----------

if [[ "$DRY_RUN" -eq 0 && "$ASSUME_YES" -eq 0 ]]; then
  cat <<EOF

About to run quality gates and release:
  tag:    $NEW_TAG
  commit: $HEAD_SHA ($HEAD_SHORT)
  branch: $BRANCH
  remote: origin (tag → package split + CLI release)
EOF
  if [[ "$SQUASH" -eq 1 ]]; then
    cat <<EOF
  squash: YES — soft-reset ${SQUASH_BASE}..HEAD (${commit_count} commits) into one commit
  message: $SQUASH_MESSAGE
  branch push: git push --force-with-lease origin $BRANCH (history rewrite)

EOF
  else
    printf '\n'
  fi
  read -r -p "Proceed with gates and create/push $NEW_TAG? [y/N] " reply
  case "$reply" in
    y|Y|yes|YES) ;;
    *) fail "aborted" ;;
  esac
fi

# --- optional squash (before gates; tree unchanged, history rewritten) --------

if [[ "$SQUASH" -eq 1 ]]; then
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "Dry-run: would squash ${commit_count} commit(s) since $SQUASH_BASE into one commit"
    printf '  message: %s\n' "$SQUASH_MESSAGE"
    printf '  then:    git push --force-with-lease origin %s\n' "$BRANCH"
  else
    if [[ "${commit_count}" -eq 1 ]]; then
      log "Squash: rewording single commit since $SQUASH_BASE"
    else
      log "Squash: collapsing ${commit_count} commits since $SQUASH_BASE into one"
    fi
    git reset --soft "$SQUASH_BASE"
    if git diff --cached --quiet; then
      fail "--squash produced an empty index after reset to $SQUASH_BASE — nothing to commit"
    fi
    git commit -m "$SQUASH_MESSAGE"
    HEAD_SHA="$(git rev-parse HEAD)"
    HEAD_SHORT="$(git rev-parse --short HEAD)"
    log "Squashed tip $HEAD_SHORT — force-with-lease push origin/$BRANCH"
    if ! git push --force-with-lease origin "refs/heads/$BRANCH"; then
      fail "git push --force-with-lease origin $BRANCH failed — fix remote and re-run (local squash commit is at $HEAD_SHORT)"
    fi
    git fetch origin "$BRANCH" --quiet 2>/dev/null || true
    if git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
      ORIGIN_SHA="$(git rev-parse "origin/$BRANCH")"
      if [[ "$ORIGIN_SHA" != "$HEAD_SHA" ]]; then
        fail "after squash push, HEAD ($HEAD_SHORT) != origin/$BRANCH — refuse to tag"
      fi
    fi
    log "origin/$BRANCH matches squashed tip $HEAD_SHORT"
  fi
fi

# --- quality gates ------------------------------------------------------------

run_php_gates() {
  log "PHP: composer test (core + messaging)"
  if ! command -v composer >/dev/null; then
    fail "composer not found on PATH"
  fi
  if [[ ! -x "$ROOT/vendor/bin/pest" ]]; then
    fail "vendor/bin/pest missing — run composer install at monorepo root"
  fi
  (
    cd "$ROOT"
    composer test
  )
}

run_cli_gates() {
  log "CLI: composer test:cli (go test ./...)"
  if ! command -v go >/dev/null; then
    fail "go not found on PATH (required for capabilities-cli gates)"
  fi
  if [[ ! -d "$ROOT/packages/capabilities-cli" ]]; then
    fail "packages/capabilities-cli missing"
  fi
  (
    cd "$ROOT"
    composer test:cli
  )
}

if [[ "$SKIP_PHP" -eq 0 ]]; then
  run_php_gates
else
  log "Skipping PHP gates (--skip-php, dry-run only)"
fi

if [[ "$SKIP_CLI" -eq 0 ]]; then
  run_cli_gates
else
  log "Skipping CLI gates (--skip-cli, dry-run only)"
fi

log "All quality gates passed"

# --- dry-run exit (no tag / push) ---------------------------------------------

if [[ "$DRY_RUN" -eq 1 ]]; then
  cat <<EOF

Dry run complete. Would create and push:
  tag:    $NEW_TAG
  commit: $HEAD_SHA ($HEAD_SHORT)
  branch: $BRANCH
  remote: origin (tag → split-packages + CLI GitHub Release)
EOF
  if [[ "$SQUASH" -eq 1 ]]; then
    cat <<EOF
  squash: would soft-reset ${SQUASH_BASE}..HEAD (${commit_count} commits) → one commit
  message: $SQUASH_MESSAGE
  branch: would git push --force-with-lease origin $BRANCH
EOF
  fi
  cat <<'EOF'

Re-run without --dry-run to release.
EOF
  exit 0
fi

# --- pre-tag dirty-tree re-check ----------------------------------------------

if [[ -n "$(git status --porcelain)" ]]; then
  git status --short
  fail "working tree became dirty during gates — refuse to tag"
fi

# Ensure origin tip still matches (non-squash path may have been equal all along).
if git rev-parse --verify "origin/$BRANCH" >/dev/null 2>&1; then
  ORIGIN_SHA="$(git rev-parse "origin/$BRANCH")"
  if [[ "$ORIGIN_SHA" != "$(git rev-parse HEAD)" ]]; then
    fail "HEAD != origin/$BRANCH after gates — push the branch (or use --squash) before tagging"
  fi
fi

# --- tag + push ---------------------------------------------------------------

MESSAGE="Release $NEW_TAG

Quality gates:
  - php: composer test (core + messaging Pest)
  - cli: composer test:cli (go test ./...)
Commit: $HEAD_SHA

Triggers: monorepo tag v* → split-packages.yml → package remotes
  + capabilities-cli GoReleaser GitHub Release (when mirrored)."

log "Creating annotated tag $NEW_TAG"
git tag -a "$NEW_TAG" -m "$MESSAGE"

log "Pushing tag to origin"
set +e
git push origin "refs/tags/$NEW_TAG"
push_rc=$?
set -e
if [[ "$push_rc" -ne 0 ]]; then
  log "Tag push failed — deleting local tag $NEW_TAG so re-run is clean"
  git tag -d "$NEW_TAG" || true
  fail "git push origin refs/tags/$NEW_TAG failed (exit $push_rc) — local tag removed"
fi

cat <<EOF

Released $NEW_TAG → origin
  commit: $HEAD_SHORT
  push:   tag $([[ "$SQUASH" -eq 1 ]] && echo "+ branch (force-with-lease squash)" || echo "only")

Next:
  - GitHub Actions: Tests (via split) → Split packages
  - Package remotes: rawphp/laravel-capabilities, -messaging, capabilities-cli
  - CLI binaries: rawphp/capabilities-cli Releases (GoReleaser after tag mirror)
  - Packagist: still human checklist (docs/versioning.md) — not automated here
EOF
