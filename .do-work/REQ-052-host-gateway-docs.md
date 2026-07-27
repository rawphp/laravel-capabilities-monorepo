# REQ-052: Host TableGateway binding docs

**UR:** UR-008
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-049
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 1
**Size:** S
**Files:** docs/tutorials/first-capability.md, docs/versioning.md, packages/laravel-capabilities/README.md, README.md
**Depends on:** REQ-050, REQ-051

## Task

Document the first-party QueryTableGateway as the package default for database drivers and provide a short (~10-line) host override binding example for custom gateways.

## Context

Brief item 2: "First-party Eloquent/query TableGateway (or document a 10-line host binding as required)." Ship both: first-party default (REQ-050/051) plus documented override. Keep residuals honest until code lands — update after implementation.

## Acceptance Criteria

- [ ] First-capability tutorial (or versioning) explains database driver + migrations + default gateway
- [ ] Includes a ~10-line host `AppServiceProvider` binding example for custom `TableGateway`
- [ ] Core package README and/or monorepo readiness residuals note durable persistence path
- [ ] Does not claim Packagist publish as done
- [ ] Docs match actual class/config keys after REQ-050/051

## Verification Steps

1. **runtime** `rg -n "TableGateway|QueryTableGateway|approval.store" docs/tutorials/first-capability.md docs/versioning.md packages/laravel-capabilities/README.md README.md`
   - Expected: docs reference real symbols and config keys
2. **test** `composer test:core -- --filter=ContainerBindings`
   - Expected: still green (docs-only change may skip if no tests; then this is no-op green)

## Integration

**Reachability:** docs linked from monorepo README Getting started / Consumer readiness.

**Data dependencies:** config keys and class names from REQ-050/051.

**Service dependencies:** none runtime; documentation only.
