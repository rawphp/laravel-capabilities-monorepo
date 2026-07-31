# REQ-065: Add MIT LICENSE and SECURITY.md

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
**Files:** LICENSE, SECURITY.md, packages/laravel-capabilities/LICENSE, packages/laravel-capabilities/SECURITY.md, packages/laravel-capabilities-messaging/LICENSE, packages/laravel-capabilities-messaging/SECURITY.md, packages/capabilities-cli/LICENSE, packages/capabilities-cli/SECURITY.md
**Depends on:**

## Task

Add standard MIT LICENSE files and SECURITY.md at monorepo root and each package tree (X-004, X-010) so split remotes ship license + vulnerability reporting path.

## Context

Architecture audit: all composer.json declare MIT but no LICENSE files exist. Packages handle authz/approvals/tokens — need SECURITY.md before public remotes.

## Acceptance Criteria

- [ ] MIT LICENSE present at monorepo root and under each of packages/laravel-capabilities, packages/laravel-capabilities-messaging, packages/capabilities-cli
- [ ] SECURITY.md at monorepo root and each package with private reporting guidance and 0.x support policy
- [ ] License text is standard MIT with copyright holder rawphp (or consistent project holder)

## Verification Steps

1. **runtime** `test -f LICENSE && test -f SECURITY.md && test -f packages/laravel-capabilities/LICENSE && test -f packages/laravel-capabilities-messaging/LICENSE && test -f packages/capabilities-cli/LICENSE && test -f packages/laravel-capabilities/SECURITY.md`
   - Expected: all files exist (exit 0)
2. **runtime** `head -1 LICENSE; grep -qi MIT LICENSE; grep -qi security SECURITY.md`
   - Expected: MIT license header/body present; SECURITY.md mentions reporting

## Integration

Omit — docs/meta only.
