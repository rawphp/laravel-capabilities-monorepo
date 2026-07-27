---
ur: UR-011
received: 2026-07-28
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  core: no
  messaging: no
reqs:
  - { id: REQ-059, layer: none, integration_confidence: n/a }
  - { id: REQ-060, layer: cli, integration_confidence: high }
  - { id: REQ-061, layer: cli, integration_confidence: high }
  - { id: REQ-062, layer: cli, integration_confidence: high }
  - { id: REQ-063, layer: cli, integration_confidence: high }
  - { id: REQ-064, layer: cli, integration_confidence: high }
acknowledged_partials: []
---

<!-- capture-summary-start -->
## Capture summary (2026-07-28)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | core: no, messaging: no |
| REQs generated | 6 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-059 | none | n/a |
| REQ-060 | cli | high |
| REQ-061 | cli | high |
| REQ-062 | cli | high |
| REQ-063 | cli | high |
| REQ-064 | cli | high |
<!-- capture-summary-end -->

# UR-011: User Request

## Request

when we push a tag to the monorepo and it gets split to the child repos, the cli needs to be built and a release created in that child repo

## Clarifications

**Q:** What does “a release created in that child repo” mean, and which packages get it?
**A:** GitHub Release with multi-arch binary assets on `rawphp/capabilities-cli` only; build the `dist/README.md` GOOS×GOARCH matrix; PHP package remotes do not get CLI release automation; monorepo already mirrors `v*` tags — do not re-implement split. *(inferred, confirmed)*

**Q:** You said the release is created “in that child repo” after the monorepo tag is split. Where should the build + GitHub Release job live?
**A:** Package-owned workflow under `packages/capabilities-cli` (mirrors into `rawphp/capabilities-cli`) that runs on push of `v*` tags there after split.

**Q:** docs/versioning.md still lists “Signed/downloadable capabilities CLI binary” as residual. For this brief’s build + release, how far should signing go?
**A:** Full platform signing (macOS notarization / Windows Authenticode in scope for the release path).

**Q:** Full platform signing needs certs on the child repo. How should this UR treat credentials?
**A:** Scaffold + secret-gated: implement signing steps that run only when secrets exist; document required secrets. Soft path when secrets are missing (clear logs) rather than hard-failing the whole release.

**Q:** Split force-pushes monorepo tags onto the child. If a tag is retagged, what should the child release job do?
**A:** Update/replace the existing GitHub Release — re-run builds and overwrite assets + notes for the same tag.

**Q:** Should release builds inject the git tag into the binary version string?
**A:** Yes — ldflags from tag so `capabilities version` reports the release version (strip leading `v`).

**Q:** How should multi-arch builds + GitHub Releases be automated?
**A:** GoReleaser — add `.goreleaser.yml` + a tag-triggered workflow in the CLI package; signing hooks secret-gated.
