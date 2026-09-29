# Architecture Audit — laravel-capabilities-monorepo (iteration 5)

**Generated:** 2026-09-30 · **Audited HEAD:** cb43279 · Full detail in `findings.json` / `findings-*.json`.

**5** findings: 0 critical, 2 high, 3 medium, 0 low

**Loop trend:** iter1 58 → iter2 28 → iter3 15 → iter4 5 → iter5 5

All iteration-4 fixes verified except M-302 (turn budget over-strict at defaults → M-401). M-301 was correct but exposed L-401 (approved run could execute in the requester's current tenant) and needed its BREAKING scope change documented (X-401).

| Id | Severity | Effort | Title | Fixed in |
|---|---|---|---|---|
| L-401 | high | small | Run approved executions in the row's tenant, not the requester's current one | 8565cf4 |
| M-401 | high | small | Stop the default 110s/120s budget from refusing normal multi-round AI turns | dd85c59 |
| C-401 | medium | trivial | Reject '/' in run capability names; `run approvals/<id>/accept` accepts | 184227f |
| L-402 | medium | trivial | Fail approver resolution closed on any ScopeResolver error, not only package ones | be02cb7 |
| X-401 | medium | trivial | Core CHANGELOG hides M-301's invoke tenant change; no BREAKING/upgrade note | dc20fc4 |

## Deferred (owner decisions)

Unchanged from iteration 4 (see ../2026-09-30-iter4/report.md), plus: a single long capability call between AI LLM rounds is not itself time-bounded (noted M-302 residual).
