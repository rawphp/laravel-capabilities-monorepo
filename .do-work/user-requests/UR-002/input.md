---
ur: UR-002
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  messaging: no
  cli: no
reqs:
  - { id: REQ-020, layer: none, integration_confidence: n/a }
  - { id: REQ-021, layer: core, integration_confidence: high }
  - { id: REQ-022, layer: core, integration_confidence: high }
  - { id: REQ-023, layer: core, integration_confidence: high }
  - { id: REQ-024, layer: core, integration_confidence: high }
acknowledged_partials: []
open_gaps:
  - "Host-app install is the real success criterion — package currently leaves routes/discovery/Artisan unwired"
  - "UR-001 left pure tables by design; this UR must apply them in the provider, not rewrite domain"
  - "Default bindings are demo-grade (in-memory stores) — full config-driven construction needs driver selection"
  - "Unit-only policy must hold — no feature/DB tests while adding real Illuminate registration"
  - "Artisan ops must stay distinct from product CLI and always invoke through the registry"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | messaging: no, cli: no |
| REQs generated | 5 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-020 | none | n/a |
| REQ-021 | core | high |
| REQ-022 | core | high |
| REQ-023 | core | high |
| REQ-024 | core | high |
<!-- capture-summary-end -->

# UR-002: User Request

## Request

1. Thin service provider / incomplete Laravel glue

Provider:

• merges config
• binds registry, in-memory idempotency, default scope, metrics

It does not (from what I saw):

• loadRoutesFrom the capability HTTP tree
• auto-discover app/Capabilities
• construct the registry from full config/capabilities.php (surfaces, audit, approval, clients)
• register real Artisan commands beyond pure tables

Routes exist as a RouteTable definition for unit tests, not as a ready Laravel route file lifecycle.
