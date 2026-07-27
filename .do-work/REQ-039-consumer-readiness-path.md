# REQ-039: Consumer readiness path

**UR:** UR-007
**Status:** backlog
**Created:** 2026-07-27
**Layer:** none
**Entry point:** App integrator evaluates the monorepo for adoption — opens root README, package docs, and plans a first capability plus app CI parity/schema checks
**Terminal state:** Status and versioning are honest (monorepo/unit-complete vs Packagist-stable); release notes and packaging readiness exist; a first-capability tutorial closes; D-020 helpers enforce real multi-surface success/deny class and durable schema snapshots under unit-only policy — consumer can tell stable-API claims from REQ-driven monorepo reality
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** README.md docs/spec.md AGENTS.md docs packages/laravel-capabilities/src/Registry/CapabilityRegistry.php packages/laravel-capabilities/tests/Unit/TestingHelpers
**Depends on:** REQ-040 REQ-041 REQ-042 REQ-043 REQ-044 REQ-045

## Task

Path-unit for consumer docs/versioning readiness and full D-020 testing DX. Child REQs implement honest status/roadmap, packaging/CHANGELOG versioning, first-capability tutorial, real assertSchemaSnapshot, real assertParity, and D-020 consumer docs plus intentional test rewrites; this REQ defines reachability and closure only.

## Context

Brief items 5–6: README/spec still advertise future/unpublished status while roadmap phases look unit-implemented; packaging, release notes, and first-capability tutorial are missing; assertParity / empty-arg helpers mostly prove presence — not full D-020 DX. Ideate: couple docs honesty to helper behaviour; packaging = monorepo readiness not live Packagist publish.

## Acceptance Criteria

- [ ] Child REQs REQ-040–REQ-045 are done and their verification steps pass
- [ ] Root README no longer claims only “future package design” without a readiness residual matrix
- [ ] A first-capability tutorial exists and is linked from README
- [ ] Package CHANGELOG or equivalent release-notes surface exists for core (and other packages in-scope)
- [ ] `assertSchemaSnapshot` fails on durable snapshot drift (not only optional in-memory equality)
- [ ] `assertParity` compares success/deny class across listed surfaces via real registry/adapter invoke paths with mocks (not empty-arg `return true`)
- [ ] Default package CI remains unit-only (no feature/DB suite, no live peers required)

## Verification Steps

1. **test** `composer test:core -- --filter=TestingHelpers 2>&1 | tail -60`
   - Expected: D-020 / testing-helper unit tests pass
2. **runtime** `test -f README.md && test -f docs/tutorials/first-capability.md || test -f docs/first-capability.md; ls packages/*/CHANGELOG.md 2>/dev/null | head -5`
   - Expected: README present; first-capability doc path from REQ-042 exists; at least core CHANGELOG present when REQ-041 done

## Manual checks (advisory)

- [ ] As a fresh reader of README only, decide monorepo vs publish readiness in under two minutes — Observable outcome: status banner + residual table answer without reading AGENTS.md

## Integration

**Reachability:** Human integrator entry via monorepo root README; package tests via `CapabilityRegistry` / facade testing helpers

**Data dependencies:** Spec roadmap + D-020 section; package `composer.json` metadata

**Service dependencies:** `CapabilityRegistry::assertParity`, `assertSchemaSnapshot`, catalog/schema pipeline
