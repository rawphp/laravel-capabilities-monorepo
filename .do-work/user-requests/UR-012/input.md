---
ur: UR-012
received: 2026-07-31
status: captured
classification: other-as-bug-fix
layers_in_scope: []
layer_decisions: {}
reqs:
  - { id: REQ-065, layer: none, integration_confidence: n/a }
  - { id: REQ-066, layer: none, integration_confidence: n/a }
  - { id: REQ-067, layer: none, integration_confidence: n/a }
  - { id: REQ-068, layer: none, integration_confidence: n/a }
  - { id: REQ-069, layer: core, integration_confidence: high }
  - { id: REQ-070, layer: core, integration_confidence: high }
  - { id: REQ-071, layer: core, integration_confidence: high }
  - { id: REQ-072, layer: core, integration_confidence: high }
  - { id: REQ-073, layer: core, integration_confidence: high }
  - { id: REQ-074, layer: messaging, integration_confidence: high }
acknowledged_partials: []
---

<!-- capture-summary-start -->
## Capture summary (2026-07-31)

| Item | Value |
|---|---|
| Classification | other-as-bug-fix |
| Layers in scope | (none — architecture fix / ops capture) |
| Layer decisions | (none) |
| REQs generated | 10 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-065 | none | n/a |
| REQ-066 | none | n/a |
| REQ-067 | none | n/a |
| REQ-068 | none | n/a |
| REQ-069 | core | high |
| REQ-070 | core | high |
| REQ-071 | core | high |
| REQ-072 | core | high |
| REQ-073 | core | high |
| REQ-074 | messaging | high |
<!-- capture-summary-end -->

## Request

/do-work run /architecture-analyst on this repo then fix issues discovered

Architecture audit report: `.architecture-analyst/2026-07-31-1835/report.md` (30 findings: 2 critical, 10 high, 14 medium, 4 low).

Fix architecture issues discovered by the audit on this monorepo (laravel-capabilities packages + Go CLI). Prioritize critical and high severity, then high-value quick wins. Stay within monorepo policy: unit tests only, no feature/DB tests, ≥95% coverage on package src, mock external boundaries, one capability run() path, messaging stays sibling package.

In scope (from report):
- Critical: L-001 HTTP Illuminate Request/Response bridge; L-004 Messaging provider binds + no FakeQueue default in production
- High: L-002 AuthController fail-closed without real issuer; L-003 Registry deny-by-default without authorize; L-005 DatabaseApprovalStore random IDs; L-008 shared cache rate limiter; X-001 monorepo test CI; X-002 gate split on green tests; X-003 go test before GoReleaser; X-004 MIT LICENSE files
- Quick wins: X-006 commit composer.lock; X-009 Dependabot; X-010 SECURITY.md; X-012 pin Go version; X-013 tag split cancel-in-progress; L-009 idempotency default align; L-013 any_staff fail closed without checker; L-007 atomic claimLease; X-007 Illuminate constraint align

Out of scope this UR (defer): L-006 durable messaging identity/thread DB stores (larger product); X-005 coverage min gate until baseline measured; L-011 CoverageGreen redesign; pure polish X-014–X-017.
