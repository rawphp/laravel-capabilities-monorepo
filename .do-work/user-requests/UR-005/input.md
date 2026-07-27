---
ur: UR-005
received: 2026-07-27
status: captured
classification: bug-fix
layers_in_scope: []
layer_decisions: {}
reqs:
  - { id: REQ-034, layer: none, integration_confidence: n/a }
acknowledged_partials: []
open_gaps:
  - "Production path is ContainerBindings::makeRegistry → new CapabilityRegistry with no clock — boot singleton is the real consumer"
  - "Tests may rely on FixedClock constructor default; switching breaks time-dependent asserts unless FixedClock is injected"
  - "Sibling components already default to SystemClock; registry is the inconsistent outlier"
  - "Partial fix (constructor only) vs full fix (bind Clock + inject in makeRegistry) needs a capture decision"
  - "At least one test asserts default clock is FixedClock (CoverageBoostTest)"
---

<!-- capture-summary-start -->
## Capture summary (2026-07-27)

| Item | Value |
|---|---|
| Classification | bug-fix |
| Layers in scope | (none — bug-fix) |
| Layer decisions | (none — all covered) |
| REQs generated | 1 |

| REQ | Layer | Integration confidence |
|---|---|---|
| REQ-034 | none | n/a |
<!-- capture-summary-end -->

# UR-005: User Request

## Request

3. Clock default is a footgun

Registry constructor defaults to FixedClock('2026-07-27…'). Correct for tests; dangerous if the singleton is used as-is in apps. Production should bind SystemClock.
