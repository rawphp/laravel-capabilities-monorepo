---
ur: UR-006
closed_at: 2026-07-27T05:46:00Z
branch: main
path_units: 1
verdict_summary:
  closed: 1
overall: closed
---

# Closure report — UR-006

## REQ-035 — closed
- req: REQ-035
- entry_point: "Maintainer prepares a release that touches AI/MCP adapters or the peer support matrix; consumer app may optionally run peer-live checks against installed `laravel/ai` / `laravel/mcp`"
- terminal_state: "D-011 residual is non-aspirational: explicit support matrix drives probe defaults; frozen contract fixtures + adapter unit tests catch bridge drift without live SDKs in default CI; release-gate docs describe matrix/contract requirements and an optional consumer peer-live path; default `composer test:core` still never requires live `laravel/ai` or `laravel/mcp`"
- walk_kind: library
- action_taken: "On main: (1) PHP invoke PeerSupportMatrix::constraints(); (2) assert PeerContractFixtures.php present; (3) rg README for D-011 release-gate section + no-live-peers; (4) composer test:core --filter='PeerSupportMatrix|PeerContractFixtures|PeerReleaseGateDocs|PeerVersionProbe'; (5) composer.json does not require laravel/ai or laravel/mcp"
- observed_state: "Matrix non-empty for laravel/ai and laravel/mcp with ^0.1/^1.0 constraints; fixtures file present; README has '## Peer support / D-011 release gate' stating default CI does not install live peers + optional consumer peer-live + maintainer checklist; 33 related unit tests passed (198 assertions); package composer require lists peers only as suggests not require"
- verdict: closed
- evidence_ref: "closure-evidence/req-035-matrix.txt; closure-evidence/req-035-fixtures.txt; closure-evidence/req-035-readme.txt; closure-evidence/req-035-suite.txt"
