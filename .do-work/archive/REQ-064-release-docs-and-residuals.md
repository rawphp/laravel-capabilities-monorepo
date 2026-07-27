# REQ-064: Release docs and residual updates


**UR:** UR-011
**Status:** done
**Created:** 2026-07-28
**Layer:** cli
**Entry point:**
**Terminal state:**
**Parent:** REQ-059
**Closure proof:** checkpoint_log:passed commit:0c2e42f steps:3/3 AC1-AC6 passed
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** packages/capabilities-cli/README.md packages/capabilities-cli/dist/README.md packages/capabilities-cli/docs/user-guide.md packages/capabilities-cli/CHANGELOG.md docs/versioning.md README.md
**Depends on:** REQ-062, REQ-063

## Task

Update consumer-facing and monorepo residual docs to reflect automated multi-arch GitHub Releases on the CLI child repo after monorepo tag + split. Keep package docs self-contained (no relative links into monorepo-only paths from package README). Adjust the “build from source until binary releases exist” status wording; note that **signed** downloads still require secrets (secret-gated) so residual may remain partially open for “signed” until secrets are installed in production.

## Context

`docs/versioning.md` §7 CLI binary residual and table row “Signed/downloadable capabilities CLI binary.” CLI README status banner still says build from source. After automation lands, download path is GitHub Releases on `rawphp/capabilities-cli`.

## Acceptance Criteria

- [x] CLI package README documents: monorepo tag `v*` → split → GitHub Release with multi-arch assets; install/download pointer to child repo Releases
- [x] `dist/README.md` points at GoReleaser + CI instead of “until automated release packaging lands” as the only path
- [x] User guide install section mentions release binaries when available
- [x] Monorepo `docs/versioning.md` updates CLI residual: automated unsigned (or secret-gated signed) release path exists; signed residual only if secrets not yet configured (wording accurate, not over-claiming)
- [x] CHANGELOG `[Unreleased]` notes release automation
- [x] No new relative monorepo links from `packages/capabilities-cli/README.md` into `../../docs/`

## Verification Steps

1. **runtime** `rg -n "GitHub Release|goreleaser|v\\*|build from source" packages/capabilities-cli/README.md packages/capabilities-cli/dist/README.md docs/versioning.md | head -40`
   - Expected: release automation described; residual language consistent with secret-gated signing
2. **runtime** `! rg -n "\\]\\(\\.\\./\\.\\./docs/" packages/capabilities-cli/README.md packages/capabilities-cli/docs/`
   - Expected: no relative links into monorepo-only docs from package docs
3. **test** `cd packages/capabilities-cli && go test ./... -count=1`
   - Expected: green (docs-only change should not break tests)

## Integration

**Reachability:** Humans reading package README / monorepo versioning docs after clone or on GitHub.

**Data dependencies:** Behaviour shipped by REQ-061–063 (release workflow + signing doc).

**Service dependencies:** None at runtime; documentation only.

## Assets

## Outputs

- packages/capabilities-cli/README.md — Release flow docs
- packages/capabilities-cli/dist/README.md — Primary CI release path
- packages/capabilities-cli/docs/user-guide.md — Install GitHub Release binaries
- packages/capabilities-cli/CHANGELOG.md — Unreleased automation notes
- docs/versioning.md — Residual CLI path updated
- README.md — Packaging row + CLI install pointer updated

