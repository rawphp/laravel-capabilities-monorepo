---
ur: UR-006
received: 2026-07-27
status: captured
classification: feature
layers_in_scope: [core, messaging, cli]
layer_decisions:
  messaging: no
  cli: no
reqs:
  - { id: REQ-035, layer: none, integration_confidence: n/a }
  - { id: REQ-036, layer: core, integration_confidence: high }
  - { id: REQ-037, layer: core, integration_confidence: high }
  - { id: REQ-038, layer: core, integration_confidence: high }
acknowledged_partials: []
open_gaps:
  - "Do not break unit-only CI — no live laravel/ai or laravel/mcp in default package suite"
  - "class_exists is a weak compatibility signal — matrix + fixtures needed"
  - "False confidence from fantasy mock peers — fixtures should track public peer shapes"
  - "Matrix staleness risk — single source of truth + tests it is non-empty"
  - "Consumer peer-live remains optional and app-owned (aspirational for real minors)"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | feature |
| Layers in scope | core, messaging, cli |
| Layer decisions | messaging: no, cli: no |
| REQs generated | 4 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-035 | none | n/a |
| REQ-036 | core | high |
| REQ-037 | core | high |
| REQ-038 | core | high |
<!-- capture-summary-end -->

# UR-006: User Request

## Request

4. Peer adapters are contract-shaped, not live-SDK-proof

AI/MCP adapters:

• map tools to arrays
• probe peers via class_exists
• do not require live laravel/ai / laravel/mcp in CI

That matches monorepo policy and is honest. It also means peer churn risk is not fully retired — D-011’s “contract tests against real minors” is still aspirational for a consumer app.
