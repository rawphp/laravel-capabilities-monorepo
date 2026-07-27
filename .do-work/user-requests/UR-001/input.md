---
ur: UR-001
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions: {}
open_gaps:
  - "Scale is ~5001 unit scenarios across 3 packages — need path-units + dependency order, not a flat bag of REQs."
  - "Empty ->todo() stubs are not yet SOT; implementing requires real asserts + production code (TDD per module)."
  - "Suite currently fatals on Pest redeclare (duplicate generated names) — harness must load before feature work."
  - "Shared core (registry/pipeline/contracts) is a footprint collision risk for parallel workers."
  - "95% unit coverage + unit-only policy applies even though brief is silent; do not game coverage or add feature/DB tests."
  - "Test updates on conflict must stay inventory/generator-aligned (or intentionally supersede) — no silent stub pruning."
reqs:
  - { id: REQ-001, layer: none, integration_confidence: n/a }
  - { id: REQ-002, layer: core, integration_confidence: high }
  - { id: REQ-003, layer: core, integration_confidence: high }
  - { id: REQ-004, layer: core, integration_confidence: high }
  - { id: REQ-005, layer: core, integration_confidence: high }
  - { id: REQ-006, layer: core, integration_confidence: high }
  - { id: REQ-007, layer: core, integration_confidence: high }
  - { id: REQ-008, layer: core, integration_confidence: high }
  - { id: REQ-009, layer: core, integration_confidence: high }
  - { id: REQ-010, layer: core, integration_confidence: high }
  - { id: REQ-011, layer: core, integration_confidence: high }
  - { id: REQ-012, layer: core, integration_confidence: high }
  - { id: REQ-013, layer: core, integration_confidence: high }
  - { id: REQ-014, layer: core, integration_confidence: high }
  - { id: REQ-015, layer: core, integration_confidence: high }
  - { id: REQ-016, layer: core, integration_confidence: high }
  - { id: REQ-017, layer: messaging, integration_confidence: high }
  - { id: REQ-018, layer: cli, integration_confidence: high }
  - { id: REQ-019, layer: none, integration_confidence: n/a }
acknowledged_partials: []
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | (none — all covered) |
| REQs generated | 19 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-001 | none | n/a |
| REQ-002 | core | high |
| REQ-003 | core | high |
| REQ-004 | core | high |
| REQ-005 | core | high |
| REQ-006 | core | high |
| REQ-007 | core | high |
| REQ-008 | core | high |
| REQ-009 | core | high |
| REQ-010 | core | high |
| REQ-011 | core | high |
| REQ-012 | core | high |
| REQ-013 | core | high |
| REQ-014 | core | high |
| REQ-015 | core | high |
| REQ-016 | core | high |
| REQ-017 | messaging | high |
| REQ-018 | cli | high |
| REQ-019 | none | n/a |
<!-- capture-summary-end -->

# UR-001: User Request

## Request

implement all the tests and business logic... all tests my pass - tests are source of truth for the packages - if there is any gaps or conflicts, read docs/spec.md for second source of direction and update the tests
