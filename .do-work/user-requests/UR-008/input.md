---
ur: UR-008
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  messaging: no
  cli: no
reqs:
  - { id: REQ-046, layer: none, integration_confidence: n/a }
  - { id: REQ-047, layer: core, integration_confidence: high }
  - { id: REQ-048, layer: core, integration_confidence: high }
  - { id: REQ-049, layer: none, integration_confidence: n/a }
  - { id: REQ-050, layer: core, integration_confidence: high }
  - { id: REQ-051, layer: core, integration_confidence: high }
  - { id: REQ-052, layer: core, integration_confidence: high }
  - { id: REQ-053, layer: none, integration_confidence: n/a }
  - { id: REQ-054, layer: none, integration_confidence: n/a }
  - { id: REQ-055, layer: none, integration_confidence: n/a }
  - { id: REQ-056, layer: none, integration_confidence: n/a }
acknowledged_partials: []
open_gaps:
  - "Fully applies config is ambiguous about which keys beyond the four injects"
  - "App integrators vs package maintainers differ — Packagist alone does not close the gap"
  - "Default driver policy foggy if registry ignores configured database stores"
  - "Audit inject may mean writer, mode flags, or outbox — undefined"
  - "Registry and ApprovalManager double-binding risk under concurrent accept/invoke"
  - "Unit-only monorepo vs first-party Eloquent gateway CI proof"
  - "Packagist/tag is partly human/network gated — split prep vs publish"
  - "Document-only TableGateway option can re-open durable-store residual"
  - "Messaging/CLI layer scope for multi-package release unclear"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | messaging: no, cli: no |
| REQs generated | 11 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-046 | none | n/a |
| REQ-047 | core | high |
| REQ-048 | core | high |
| REQ-049 | none | n/a |
| REQ-050 | core | high |
| REQ-051 | core | high |
| REQ-052 | core | high |
| REQ-053 | none | n/a |
| REQ-054 | none | n/a |
| REQ-055 | none | n/a |
| REQ-056 | none | n/a |
<!-- capture-summary-end -->

# UR-008: User Request

## Request

Closing the 7 → 8.5 gap is mostly three items:

1. Registry factory fully applies config + injects approval/idempotency/audit/scope
2. First-party Eloquent/query TableGateway (or document a 10-line host binding as required)
3. Tagged 0.x release + Packagist (even pre-stable)
