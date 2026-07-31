# REQ-066: Lockfile, Dependabot, Illuminate align

**UR:** UR-012
**Status:** backlog
**Created:** 2026-07-31
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** S
**Files:** .gitignore, composer.lock, .github/dependabot.yml, packages/laravel-capabilities/composer.json, packages/laravel-capabilities-messaging/composer.json, composer.json
**Depends on:**

## Task

Stop gitignoring monorepo composer.lock (commit it), add Dependabot for composer/gomod/github-actions, and align Illuminate version constraints across core, messaging, and monorepo root (X-006, X-009, X-007).

## Context

Root is type:project workspace but ignores lockfile. Core allows illuminate ^13; messaging/root only ^11|^12. No automated dependency updates.

## Acceptance Criteria

- [ ] `/composer.lock` is not ignored; composer.lock is tracked in git
- [ ] `.github/dependabot.yml` covers composer (root), gomod (`/packages/capabilities-cli`), and github-actions
- [ ] Illuminate constraints are identical across root + both PHP packages (either all include ^13 or none — document choice in commit message)
- [ ] `composer validate` succeeds after constraint change

## Verification Steps

1. **runtime** `grep -n 'composer.lock' .gitignore || true; git check-ignore -v composer.lock || echo 'not-ignored'`
   - Expected: composer.lock is not ignored
2. **runtime** `test -f .github/dependabot.yml && grep -E 'composer|gomod|github-actions' .github/dependabot.yml`
   - Expected: all three ecosystems present
3. **runtime** `python3 -c "import json; pkgs=['composer.json','packages/laravel-capabilities/composer.json','packages/laravel-capabilities-messaging/composer.json'];
print([json.load(open(p))['require'].get('illuminate/support') for p in pkgs])"`
   - Expected: support constraints match across packages that require it (or messaging matches core pattern)

## Integration

Omit — tooling/config only.
