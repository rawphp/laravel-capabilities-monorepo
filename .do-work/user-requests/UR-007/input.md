---
ur: UR-007
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  messaging: no
  cli: no
reqs:
  - { id: REQ-039, layer: none, integration_confidence: n/a }
  - { id: REQ-040, layer: none, integration_confidence: n/a }
  - { id: REQ-041, layer: none, integration_confidence: n/a }
  - { id: REQ-042, layer: none, integration_confidence: n/a }
  - { id: REQ-043, layer: core, integration_confidence: high }
  - { id: REQ-044, layer: core, integration_confidence: high }
  - { id: REQ-045, layer: core, integration_confidence: high }
acknowledged_partials: []
open_gaps:
  - "Unit-green monorepo ≠ shippable release without versions/CHANGELOG/public API contract"
  - "Consumer persona and first-capability path (fluent vs attribute vs multi-surface) still undefined"
  - "assertParity/schema snapshot are presence-level vs full D-020 (invoke paths + durable snapshot files)"
  - "Docs must stay honest (pre-Packagist / partial D-020) rather than claim stable API"
  - "Packaging = monorepo readiness + versioning, not live Packagist publish unless approved"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | messaging: no, cli: no |
| REQs generated | 7 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-039 | none | n/a |
| REQ-040 | none | n/a |
| REQ-041 | none | n/a |
| REQ-042 | none | n/a |
| REQ-043 | core | high |
| REQ-044 | core | high |
| REQ-045 | core | high |
<!-- capture-summary-end -->

# UR-007: User Request

## Request

5. Docs and versioning lag the code

README/spec still advertise future/unpublished status. Roadmap phases (v0.1–v0.5) look largely implemented in unit form, but packaging, release notes, and a “first capability” tutorial are missing. As a consumer I cannot tell “stable API” from “REQ-driven monorepo.”

6. Some consumer helpers are thin

assertParity / empty-arg helpers mostly prove presence. Schema snapshot can check equality, but this is not yet the full D-020 DX the spec promised.
