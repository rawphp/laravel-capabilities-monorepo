# Ideate — UR-011

**Reviewed:** 2026-07-28

## Explorer — Assumptions & Perspectives

- **Who owns the release job is undefined.** The brief says “when split happens, build CLI and create a release in the child repo,” but does not say whether the monorepo workflow posts artifacts into `rawphp/capabilities-cli`, or a workflow *in* that package (after the mirrored tag arrives) builds and publishes. Scenario: implement monorepo-side release while maintainers expect package-repo-owned CI (self-contained after split, AGENTS.md policy) — wrong home for secrets and docs. Trigger: “release created in that child repo.”
- **“A release” usually means a GitHub Release with downloadable assets, not only a tag.** Tags are already mirrored by `split-packages.yml`; binaries and release notes are missing. Scenario: ship only another tag with no assets — consumers still “build from source.” Trigger: “cli needs to be built and a release created.”
- **Platform matrix is assumed, not stated.** `dist/README.md` already documents darwin/linux/windows × amd64/arm64. Scenario: CI builds only `linux/amd64` and Mac/Windows users cannot download a matching binary. Trigger: “the cli needs to be built.”
- **Signing and notarization are out of the brief but named as residual.** `docs/versioning.md` residual is “Signed/downloadable `capabilities` CLI binary.” Scenario: ship unsigned GH Releases and later treat “signed” as done — residual stays open. Trigger: product status / residual wording vs this UR’s scope.
- **PHP package remotes must not get CLI release behaviour.** Split matrix includes two Composer packages. Scenario: a shared post-split hook tries to “release” PHP trees and fails or publishes junk. Trigger: monorepo tag push splits *all* packages.

## Challenger — Risks & Edge Cases

- **Race / ordering between split and release.** If the monorepo builds from its tree while the child release expects the mirrored tag SHA, a failed or delayed split leaves a release pointing at stale/missing content on the package remote. Scenario: monorepo job creates a GitHub Release on the child before tag push finishes — API 404 or wrong ref. Trigger: “when … it gets split … release created in that child repo.”
- **Force-pushed tags.** Split uses `git tag -f` and force-pushes tags to package remotes. Scenario: retag `v0.1.0` rebuilds over an existing GitHub Release; soft-fail vs overwrite policy undefined — duplicate assets or CI skip. Trigger: existing `split-packages.yml` tag force path.
- **Secrets and permissions.** Creating releases on `rawphp/capabilities-cli` needs write contents (and often `id-token` if OIDC later). Scenario: only `SPLIT_GITHUB_TOKEN` exists with Contents R/W but release workflow lives in monorepo without `packages: write` on the child — job fails at ship time. Trigger: child-repo release creation.
- **Idempotency and re-runs.** Workflow re-run after partial upload can leave half assets. Scenario: linux assets uploaded, windows step fails; re-run creates duplicate or fails “already exists.” Trigger: production release CI reality.
- **Version string in binary.** User guide still implies a fixed version print; CHANGELOG notes “binary embedding is a later release step.” Scenario: release `v0.1.0` assets report wrong version — support confusion. Trigger: “built” product binary without `-ldflags` version inject (if still hard-coded).
- **Scope creep into monorepo-only release of CLI without split.** Building only from monorepo CI on tag without a package-repo workflow breaks the “package docs/workflows self-contained after split” ship model. Scenario: public `capabilities-cli` clone has no release workflow; only monorepo can cut binaries. Trigger: AGENTS.md split self-containment.

## Connector — Links & Reuse

- **Existing split already delivers tags to `rawphp/capabilities-cli`.** `.github/workflows/split-packages.yml` on monorepo `tags: ['v*']` force-mirrors tags. Release automation should *consume* that mirrored tag (workflow_dispatch or `on: push: tags` in the package tree) rather than reimplement split.
- **`packages/capabilities-cli/dist/README.md` already names goreleaser + the GOOS/GOARCH matrix.** Prefer goreleaser (or equivalent matrix build) that matches that doc so capture doesn’t invent a second matrix.
- **UR-008 decision: Packagist/tag publish is human-gated; no package credentials beyond configured split token in monorepo CI.** This UR is the separate “CLI binary residual” track called out in `docs/versioning.md` §7 — not Packagist. Keep release secrets scoped to the CLI package remote (or a dedicated release token), not PHP packages.
- **CHANGELOG Keep-a-Changelog + monorepo tag pattern `v0.Y.Z`** already defined; GitHub Release body should pull from the dated CHANGELOG section when present, not invent a parallel notes source.

## Summary

The monorepo already mirrors `v*` tags into `rawphp/capabilities-cli`; the gap is **build multi-arch binaries and publish a GitHub Release with assets on that child remote**, ideally via a **package-owned** workflow that runs when the mirrored tag lands (self-contained after split). Clarify signing as out-of-scope residual unless the brief expands, avoid force-tag/idempotency footguns, and do not attach release jobs to the PHP package matrix rows.
