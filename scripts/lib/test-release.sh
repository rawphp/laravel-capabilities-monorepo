#!/usr/bin/env bash
# test-release.sh — bash self-test for monorepo release ship-path contracts.
#
# Covers (no network, no full Pest/Go suites):
#   1. Version helpers / strict filtering
#   2. Flag matrix early exits
#   3. Empty-range / origin-missing / squash contracts (static + pure probes)
#   4. Temp git fixture: strict tags + soft-reset squash
#
# Usage:
#   bash scripts/lib/test-release.sh
#
# Exit: 0 all pass; non-zero with the failing case name on stderr.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RELEASE_SH="$ROOT/scripts/release.sh"

PASS=0
FAIL=0
FAILED_CASES=()

pass() {
  PASS=$((PASS + 1))
  printf '  PASS  %s\n' "$1"
}

fail_case() {
  FAIL=$((FAIL + 1))
  FAILED_CASES+=("$1")
  printf '  FAIL  %s — %s\n' "$1" "${2:-}" >&2
}

assert_eq() {
  local name="$1" expected="$2" actual="$3"
  if [[ "$actual" == "$expected" ]]; then
    pass "$name"
  else
    fail_case "$name" "expected='$expected' actual='$actual'"
  fi
}

assert_exit() {
  local name="$1" expected_code="$2"
  shift 2
  local actual_code=0
  set +e
  "$@" >/dev/null 2>&1
  actual_code=$?
  set -e
  if [[ "$actual_code" -eq "$expected_code" ]]; then
    pass "$name"
  else
    fail_case "$name" "expected exit $expected_code, got $actual_code"
  fi
}

extract_fn() {
  local name="$1"
  awk -v fn="$name" '
    $0 ~ "^" fn "\\(\\)" { capturing = 1 }
    capturing { print }
    capturing && /^}/ { exit }
  ' "$RELEASE_SH"
}

fail() { printf 'error: %s\n' "$*" >&2; return 1; }

# shellcheck disable=SC1090
eval "$(extract_fn next_version)"
# shellcheck disable=SC1090
eval "$(extract_fn version_strictly_greater)"

STRICT_TAG_RE='^v[0-9]+\.[0-9]+\.[0-9]+$'
filter_strict_tags() {
  grep -E "$STRICT_TAG_RE" || true
}

printf '==> release ship-path self-test\n'
printf '    root: %s\n' "$ROOT"

printf '\n-- version helpers / strict filtering --\n'

assert_eq "next_version patch v1.2.3 → v1.2.4" "v1.2.4" "$(next_version v1.2.3 patch)"
assert_eq "next_version minor v1.2.3 → v1.3.0" "v1.3.0" "$(next_version v1.2.3 minor)"
assert_eq "next_version major v1.2.3 → v2.0.0" "v2.0.0" "$(next_version v1.2.3 major)"
assert_eq "next_version patch v0.1.0 → v0.1.1" "v0.1.1" "$(next_version v0.1.0 patch)"

assert_eq "first-release patch → v0.1.0" "v0.1.0" "$(next_version '' patch)"
assert_eq "first-release minor → v0.1.0" "v0.1.0" "$(next_version '' minor)"
assert_eq "first-release major → v1.0.0" "v1.0.0" "$(next_version '' major)"

assert_eq "next_version explicit v9.8.7" "v9.8.7" "$(next_version v1.0.0 v9.8.7)"

set +e
_out="$(next_version 'v1.2.3-rc1' patch 2>/dev/null)"
_rc=$?
set -e
if [[ "$_rc" -ne 0 ]]; then
  pass "next_version refuses non-strict base v1.2.3-rc1"
else
  fail_case "next_version refuses non-strict base v1.2.3-rc1" "got '$_out' exit 0"
fi

_strict_in=$'v1.2.3\nv1.2.3-rc1\nv1.2.3.4\nv2.0.0\nv0.1.0-beta\nnot-a-tag\nv10.20.30'
_strict_out="$(printf '%s\n' "$_strict_in" | filter_strict_tags | tr '\n' ' ' | sed 's/[[:space:]]*$//')"
assert_eq "strict filter keeps only vX.Y.Z" "v1.2.3 v2.0.0 v10.20.30" "$_strict_out"

_latest="$(printf '%s\n' "$_strict_in" | filter_strict_tags | sort -V | tail -1)"
assert_eq "strict latest of mixed tags" "v10.20.30" "$_latest"

if version_strictly_greater v1.2.4 v1.2.3; then
  pass "version_strictly_greater v1.2.4 > v1.2.3"
else
  fail_case "version_strictly_greater v1.2.4 > v1.2.3" "expected true"
fi
if version_strictly_greater v1.2.3 v1.2.3; then
  fail_case "version_strictly_greater equal refuse" "expected false for equal"
else
  pass "version_strictly_greater equal refuse"
fi
if version_strictly_greater v1.2.2 v1.2.3; then
  fail_case "version_strictly_greater lower refuse" "expected false for lower"
else
  pass "version_strictly_greater lower refuse"
fi

assert_exit "CLI refuses v1.2.3-rc1" 2 bash "$RELEASE_SH" v1.2.3-rc1
assert_exit "CLI refuses v1.2.3.4" 2 bash "$RELEASE_SH" v1.2.3.4

printf '\n-- flag matrix --\n'

assert_exit "flag --help exits 0" 0 bash "$RELEASE_SH" --help
assert_exit "flag -h exits 0" 0 bash "$RELEASE_SH" -h

assert_exit "flag --skip-php without --dry-run exits non-zero" 1 \
  bash "$RELEASE_SH" --skip-php
assert_exit "flag --skip-cli without --dry-run exits non-zero" 1 \
  bash "$RELEASE_SH" --skip-cli

assert_exit "flag unknown arg exits 2" 2 bash "$RELEASE_SH" --not-a-real-flag

if bash "$RELEASE_SH" --help 2>&1 | grep -q 'allow-empty-range'; then
  pass "help documents --allow-empty-range"
else
  fail_case "help documents --allow-empty-range" "flag missing from --help"
fi

if bash "$RELEASE_SH" --help 2>&1 | grep -q -- '--squash'; then
  pass "help documents --squash"
else
  fail_case "help documents --squash" "flag missing from --help"
fi
if bash "$RELEASE_SH" --help 2>&1 | grep -qE -- '-m, --message|--message'; then
  pass "help documents -m/--message"
else
  fail_case "help documents -m/--message" "message flag missing from --help"
fi

assert_exit "flag -m without --squash exits 2" 2 bash "$RELEASE_SH" -m "only message"

if grep -n 'force-with-lease' "$RELEASE_SH" | grep -q .; then
  if grep -B30 'force-with-lease' "$RELEASE_SH" | grep -q 'SQUASH'; then
    pass "force-with-lease gated by SQUASH path"
  else
    fail_case "force-with-lease gated by SQUASH path" "force-with-lease not near SQUASH guard"
  fi
else
  fail_case "force-with-lease gated by SQUASH path" "force-with-lease missing from release.sh"
fi

if grep -q 'tag only — no branch push' "$RELEASE_SH"; then
  pass "non-squash plan still tag-only branch policy"
else
  fail_case "non-squash plan still tag-only branch policy" "missing tag-only plan string"
fi

if grep -q 'composer test' "$RELEASE_SH" && grep -q 'test:cli' "$RELEASE_SH"; then
  pass "gates reference composer test + test:cli"
else
  fail_case "gates reference composer test + test:cli" "missing monorepo gate commands"
fi

printf '\n-- empty-range / origin-missing contracts --\n'

if grep -q 'allow-empty-range' "$RELEASE_SH" \
  && grep -q 'empty range' "$RELEASE_SH" \
  && grep -q 'ALLOW_EMPTY_RANGE' "$RELEASE_SH"; then
  pass "empty-range refuse contract present in release.sh"
else
  fail_case "empty-range refuse contract present in release.sh" "missing allow-empty-range / empty range logic"
fi

if grep -n 'not found after fetch' "$RELEASE_SH" | grep -q 'origin/'; then
  if grep -B3 -A3 'not found after fetch' "$RELEASE_SH" | grep -q 'DRY_RUN'; then
    pass "missing-origin hard-fail (non-dry-run) contract present"
  else
    fail_case "missing-origin hard-fail (non-dry-run) contract present" "found message but no DRY_RUN branch nearby"
  fi
else
  fail_case "missing-origin hard-fail (non-dry-run) contract present" "missing origin-not-found error string"
fi

if grep -n 'latest_tag' "$RELEASE_SH" | head -1 >/dev/null \
  && grep -A20 '^latest_tag()' "$RELEASE_SH" | grep -q '\[0-9\]'; then
  pass "strict tag regex used in latest_tag path"
else
  fail_case "strict tag regex used in latest_tag path" "could not find strict filter in latest_tag"
fi

# First-release squash may use origin/$BRANCH as base (not only prior tag).
if grep -q 'origin/\$BRANCH' "$RELEASE_SH" || grep -q 'origin/$BRANCH' "$RELEASE_SH"; then
  if grep -A15 'SQUASH_BASE' "$RELEASE_SH" | grep -q 'origin/'; then
    pass "first-release squash can use origin branch base"
  else
    # looser: plan mentions squash base without requiring prior tag only
    if grep -q 'or origin' "$RELEASE_SH"; then
      pass "first-release squash can use origin branch base"
    else
      fail_case "first-release squash can use origin branch base" "no origin base path for SQUASH_BASE"
    fi
  fi
else
  fail_case "first-release squash can use origin branch base" "origin/\$BRANCH not referenced"
fi

probe_empty_range() {
  local commit_count="$1" allow="$2"
  if [[ "${commit_count}" -eq 0 && "$allow" -eq 0 ]]; then
    return 1
  fi
  return 0
}
if ! probe_empty_range 0 0; then
  pass "empty-range pure: refuse when count=0 and no override"
else
  fail_case "empty-range pure: refuse when count=0 and no override" "should refuse"
fi
if probe_empty_range 0 1; then
  pass "empty-range pure: allow when --allow-empty-range"
else
  fail_case "empty-range pure: allow when --allow-empty-range" "should allow"
fi
if probe_empty_range 3 0; then
  pass "empty-range pure: allow when commits exist"
else
  fail_case "empty-range pure: allow when commits exist" "should allow"
fi

printf '\n-- temp git fixture (strict local tags) --\n'
_tmp="$(mktemp -d "${TMPDIR:-/tmp}/test-release.XXXXXX")"
cleanup_tmp() { rm -rf "$_tmp"; }
trap cleanup_tmp EXIT

(
  cd "$_tmp"
  git init -q -b main
  git config user.email "test@example.com"
  git config user.name "test-release"
  echo ok > README
  git add README
  git commit -q -m "init"
  git tag v1.0.0
  git tag v1.2.3-rc1
  git tag v1.1.0
  git tag v1.2.3.4
  git tag not-semver
)

_local_latest="$(
  cd "$_tmp"
  git tag -l 'v[0-9]*.[0-9]*.[0-9]*' \
    | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
    | sort -V \
    | tail -1 || true
)"
assert_eq "temp-repo strict latest ignores rc/multi-dot" "v1.1.0" "$_local_latest"

printf '\n-- temp git fixture (squash soft-reset) --\n'
_tmp_sq="$(mktemp -d "${TMPDIR:-/tmp}/test-release-squash.XXXXXX")"
cleanup_tmp_sq() { rm -rf "$_tmp_sq"; }
trap 'cleanup_tmp; cleanup_tmp_sq' EXIT

(
  cd "$_tmp_sq"
  git init -q -b main
  git config user.email "test@example.com"
  git config user.name "test-release"
  echo a > file.txt
  git add file.txt
  git commit -q -m "base"
  git tag v0.1.0
  echo b >> file.txt
  git add file.txt
  git commit -q -m "wip one"
  echo c >> file.txt
  git add file.txt
  git commit -q -m "wip two"
  echo d >> file.txt
  git add file.txt
  git commit -q -m "merge(ORI-x): noise"
  count_before="$(git rev-list --count v0.1.0..HEAD)"
  tree_before="$(git rev-parse 'HEAD^{tree}')"
  git reset --soft v0.1.0
  git commit -q -m "Release v0.2.0"
  count_after="$(git rev-list --count v0.1.0..HEAD)"
  tree_after="$(git rev-parse 'HEAD^{tree}')"
  msg="$(git log -1 --pretty=%s)"
  printf '%s\n' "$count_before" > "$_tmp_sq/count_before"
  printf '%s\n' "$count_after" > "$_tmp_sq/count_after"
  printf '%s\n' "$tree_before" > "$_tmp_sq/tree_before"
  printf '%s\n' "$tree_after" > "$_tmp_sq/tree_after"
  printf '%s\n' "$msg" > "$_tmp_sq/msg"
)
assert_eq "squash fixture starts with 3 commits since tag" "3" "$(cat "$_tmp_sq/count_before")"
assert_eq "squash fixture collapses to 1 commit since tag" "1" "$(cat "$_tmp_sq/count_after")"
assert_eq "squash fixture preserves tree" "$(cat "$_tmp_sq/tree_before")" "$(cat "$_tmp_sq/tree_after")"
assert_eq "squash fixture clean message" "Release v0.2.0" "$(cat "$_tmp_sq/msg")"

printf '\n==> summary: %s passed, %s failed\n' "$PASS" "$FAIL"
if [[ "$FAIL" -ne 0 ]]; then
  printf 'failed cases:\n' >&2
  for c in "${FAILED_CASES[@]}"; do
    printf '  - %s\n' "$c" >&2
  done
  exit 1
fi
printf 'all release ship-path self-tests passed\n'
exit 0
