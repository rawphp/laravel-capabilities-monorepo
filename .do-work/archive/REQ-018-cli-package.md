# REQ-018: Go capabilities CLI client


**UR:** UR-001
**Status:** done
**Created:** 2026-07-27
**Layer:** cli
**Entry point:** capabilities CLI binary: auth, catalog, run, optional MCP stdio
**Terminal state:** composer test:cli / go test ./... passes; CLI is HTTP client only (caller: cli); local schema validate UX only.
**Parent:** 
**Closure proof:** checkpoint_log:passed commit:5bf0f4a composer test:cli exit 0
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/capabilities-cli/cmd/capabilities, packages/capabilities-cli/internal/api, packages/capabilities-cli/internal/auth, packages/capabilities-cli/internal/catalog, packages/capabilities-cli/internal/run, packages/capabilities-cli/internal/mcpstdio, packages/capabilities-cli/go.mod, packages/capabilities-cli/go.sum, packages/capabilities-cli/README.md, packages/capabilities-cli/dist
**Depends on:** REQ-011

## Task

Implement Go CLI (D-016): auth/keychain, catalog cache, run with idempotency key, exit codes, local JSON Schema validation, optional MCP stdio. Unit-test with httptest/mocks — no live network by default. Never embed domain logic; never trust as authorization (server re-validates).

## Context

Product CLI is remote HTTP client to single capability API. Binary name capabilities.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [x] internal/api auth catalog run mcpstdio cmd tests pass
- [x] Local schema validation + Idempotency-Key behaviour covered
- [x] No domain business logic in Go
- [x] go test ./... exits 0 with acceptable coverage for implemented packages

- [x] HTTP 4xx/5xx and schema validation failures map to non-zero exit codes without embedding domain logic

## Verification Steps

1. **test** `composer test:cli 2>&1 | tail -40`
   - Expected: Exit 0

## Integration

**Reachability:** cmd/capabilities main; user installs binary

**Data dependencies:** Cached catalog JSON; keychain tokens

**Service dependencies:** HTTP capability API from core package

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist


## Outputs

- packages/capabilities-cli — Go product CLI
