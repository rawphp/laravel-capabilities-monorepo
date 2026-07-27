---
ur: UR-010
received: 2026-07-28
status: captured
classification: bug-fix
layers_in_scope: []
layer_decisions: {}
reqs:
  - { id: REQ-058, layer: none, integration_confidence: n/a }
acknowledged_partials: []
open_gaps:
  - "Brief cites only mcp --help; same bug class likely on run/catalog/describe/approvals"
  - "Success criteria undefined (exit code, stdout vs stderr, -h parity)"
  - "Flag order (mcp --profile=x --help) and auth-before-help footguns"
  - "Silent exit 0 when logged in can hide the regression in smoke checks"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-28)

| Item | Value |
|---|---|
| Classification | bug-fix |
| Layers in scope | (none — bug-fix) |
| Layer decisions | (none — all covered) |
| REQs generated | 1 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-058 | none | n/a |
<!-- capture-summary-end -->

# UR-010: User Request

## Request

the cli does not show help when requested (capabilities mcp --help)
