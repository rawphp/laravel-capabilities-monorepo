# REQ-042: First capability tutorial

**UR:** UR-007
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:**
**Terminal state:**
**Parent:** REQ-039
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** M
**Files:** docs/tutorials/first-capability.md README.md packages/laravel-capabilities/README.md
**Depends on:**

## Task

Write a single “first capability” tutorial for app integrators: install/path-require the core package, define one capability (prefer one discovery style — fluent `Capability::define` or `#[Capability]` — and mention the other), register/discover it, invoke via registry (and note HTTP surface if already present), and point to D-020 testing helpers for app CI. Keep scope minimal and honest about monorepo install. Link from root README.

## Context

Brief: first-capability tutorial missing. Ideate: pick one primary path to avoid thrash (fluent vs attribute). Spec + package already support attribute + fluent discovery (D-017). Unit-only monorepo — tutorial code samples need not be runnable feature tests; they should match real public APIs in `packages/laravel-capabilities/src`.

## Acceptance Criteria

- [ ] `docs/tutorials/first-capability.md` exists with end-to-end steps: install/path, define one capability with input/output schema, authorize/run skeleton, invoke via registry
- [ ] Tutorial picks one primary definition style and briefly cross-links the alternate style
- [ ] Tutorial states monorepo path-require (or VCS) install, not a false Packagist-only flow
- [ ] Tutorial links or section-points to testing helpers / D-020 (may note “see Testing helpers docs” if REQ-045 not yet merged — use stable heading anchors or package README)
- [ ] Root README links to the tutorial under a Getting started or Docs section
- [ ] Sample code uses real namespaces/classes (`Rawphp\Capabilities\…`) that exist in package source

## Verification Steps

1. **runtime** `test -f docs/tutorials/first-capability.md && rg -n "Capability|define|invoke|Rawphp\\\\Capabilities" docs/tutorials/first-capability.md | head -40`
   - Expected: tutorial present with real package API references
2. **runtime** `rg -n "first-capability|tutorials/first" README.md packages/laravel-capabilities/README.md | head -20`
   - Expected: at least root README links the tutorial
3. **runtime** `rg -n "class Capability|function define|namespace Rawphp\\\\Capabilities" packages/laravel-capabilities/src -g '*.php' | head -20`
   - Expected: cited symbols resolve in package source (spot-check)
