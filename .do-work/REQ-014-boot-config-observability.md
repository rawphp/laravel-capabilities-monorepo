# REQ-014: Boot rules config events observability

**UR:** UR-001
**Status:** backlog
**Created:** 2026-07-27
**Layer:** core
**Entry point:** CapabilitiesServiceProvider boot + config/capabilities.php
**Terminal state:** Boot/* Config/* Events/* Observability/* unit tests pass; disabled surfaces register nothing; missing peers fail closed/loud.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/config packages/laravel-capabilities/src/Events packages/laravel-capabilities/tests/Unit/Boot packages/laravel-capabilities/tests/Unit/Config packages/laravel-capabilities/tests/Unit/Events packages/laravel-capabilities/tests/Unit/Observability
**Depends on:** REQ-011, REQ-012, REQ-013

## Task

Implement service provider boot rules (surface flags, peer matrix, disabled surface behaviour), config keys, domain events, metrics/spans labels. Flesh Boot Config Events Observability tests with container fakes — still unit-level, no DB.

## Context

Surfaces default on (except messaging off until messaging package); fail closed when peers missing while surface enabled.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Surface boot rules and peer matrix tests pass
- [ ] Config matrices pass
- [ ] Events payload matrix and observability metrics/spans pass
- [ ] Disabled surface registers nothing / invoke denied

- [ ] Missing required peer with surface enabled produces clear boot failure or soft-disable, not silent half-registration

## Verification Steps

1. **test** `composer test:core -- --filter=Boot 2>&1 | tail -40`
   - Expected: Boot tests pass
2. **test** `composer test:core -- --filter=Config 2>&1 | tail -40`
   - Expected: Config tests pass
3. **test** `composer test:core -- --filter=Events 2>&1 | tail -30`
   - Expected: Events tests pass
4. **test** `composer test:core -- --filter=Observability 2>&1 | tail -40`
   - Expected: Observability tests pass

## Integration

**Reachability:** Laravel package auto-discovery → CapabilitiesServiceProvider

**Data dependencies:** config/capabilities.php

**Service dependencies:** All surface adapters registration

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
