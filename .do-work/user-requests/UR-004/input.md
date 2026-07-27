---
ur: UR-004
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  messaging: no
  cli: no
reqs:
  - { id: REQ-029, layer: none, integration_confidence: n/a }
  - { id: REQ-030, layer: core, integration_confidence: high }
  - { id: REQ-031, layer: core, integration_confidence: high }
  - { id: REQ-032, layer: core, integration_confidence: high }
  - { id: REQ-033, layer: core, integration_confidence: high }
acknowledged_partials: []
open_gaps:
  - "Survival across restart is the product bar — Eloquent is the promised strategy, not the only possible one"
  - "Unit-only policy: no feature/DB CI tests; mock DB boundary or contract-test with fakes"
  - "Spec lists three tables: approvals, idempotency, audit_outbox — brief stresses first two"
  - "UR-002 maps database → memory package_default; real drivers must replace that fallback"
  - "compareAndUpdate/claimLease and composite unique keys must hold under concurrency"
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
| REQ-029 | none | n/a |
| REQ-030 | core | high |
| REQ-031 | core | high |
| REQ-032 | core | high |
| REQ-033 | core | high |
<!-- capture-summary-end -->

# UR-004: User Request

## Request

2. No production persistence drivers

• Migrations folder empty
• Default stores: in-memory approval / idempotency
• Contracts say production may use Eloquent — implementations are not shipped

Without DB drivers, approvals and idempotency do not survive process restart. That is fine for unit tests; it is not fine for agent retries in production.
