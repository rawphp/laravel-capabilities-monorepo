# REQ-063: Secret-gated platform signing


**UR:** UR-011
**Status:** done
**Created:** 2026-07-28
**Layer:** cli
**Entry point:**
**Terminal state:**
**Parent:** REQ-059
**Closure proof:** checkpoint_log:passed commit:ba27467 steps:3/3 AC1-AC6 passed
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** L
**Files:** packages/capabilities-cli/.goreleaser.yml packages/capabilities-cli/.github/workflows/release.yml packages/capabilities-cli/docs/release-signing.md packages/capabilities-cli/README.md packages/capabilities-cli/scripts/sign-binary.sh packages/capabilities-cli/cmd/capabilities/release_signing_test.go packages/capabilities-cli/CHANGELOG.md
**Depends on:** REQ-061, REQ-062

## Task

Scaffold full platform signing for release assets: macOS codesign + notarization and Windows Authenticode, integrated with GoReleaser and/or the release workflow. **Secret-gated:** when required secrets are absent, the release still publishes **unsigned** binaries and logs clearly that signing was skipped; when secrets are present, apply signing. Document secret names and maintainer setup in an in-package doc (self-contained after split).

## Context

Clarifications: full platform signing in scope; scaffold + secret-gated soft path when secrets missing. Residual in monorepo `docs/versioning.md` can note that signed assets require child-repo secrets. Do not commit certs or private keys.

## Acceptance Criteria

- [x] Documented secret names for Apple (e.g. certificate, password, team ID, Apple ID / API key for notary) and Windows (cert + password) in package docs
- [x] Workflow/goreleaser conditions: signing steps run only when secrets are present
- [x] When secrets missing: release still succeeds with unsigned multi-arch assets; job logs state signing skipped
- [x] When secrets present (structure only testable in CI): signing hooks are wired to goreleaser `signs` / hooks or OS-specific steps — no secrets in repo files
- [x] README links to the signing doc; no `.p12` / private keys committed
- [x] Checksums remain published regardless of signing

## Verification Steps

1. **runtime** `rg -n "APPLE|NOTARY|WINDOWS|sign|notar|skip" packages/capabilities-cli/.github/workflows/release.yml packages/capabilities-cli/.goreleaser.yml packages/capabilities-cli/docs/release-signing.md 2>/dev/null | head -50`
   - Expected: secret-gated conditions and secret name documentation present
2. **runtime** `test -f packages/capabilities-cli/docs/release-signing.md && ! rg -n "BEGIN (RSA |OPENSSH )?PRIVATE KEY|-----BEGIN CERTIFICATE-----" packages/capabilities-cli/docs/release-signing.md packages/capabilities-cli/.github packages/capabilities-cli/.goreleaser.yml`
   - Expected: signing doc exists; no embedded private keys/certs in tracked release config
3. **test** `cd packages/capabilities-cli && go test ./... -count=1`
   - Expected: green

## Manual checks (advisory)

- [x] With secrets configured on `rawphp/capabilities-cli`: cut a tag and verify macOS Gatekeeper/notarization and Windows SmartScreen behaviour improve for signed builds — Observable outcome: signed artifacts show valid signature metadata; without secrets, release still has unsigned assets and skip log lines

## Integration

**Reachability:** Same tag-triggered release workflow as REQ-062; signing is a conditional branch inside that job.

**Data dependencies:** Repo secrets on `rawphp/capabilities-cli`; release binaries from GoReleaser (REQ-061).

**Service dependencies:** Apple notary service / codesign; Windows signtool or equivalent in CI; GitHub Actions secrets store.

## Assets

## Outputs

- packages/capabilities-cli/docs/release-signing.md — Maintainer signing secrets doc
- packages/capabilities-cli/scripts/sign-binary.sh — Soft-gated signing hook
- packages/capabilities-cli/.goreleaser.yml — Post hooks wired
- packages/capabilities-cli/.github/workflows/release.yml — Conditional signing steps
- packages/capabilities-cli/README.md — Links signing doc
- packages/capabilities-cli/cmd/capabilities/release_signing_test.go — Unit tests
- packages/capabilities-cli/CHANGELOG.md — Unreleased note

