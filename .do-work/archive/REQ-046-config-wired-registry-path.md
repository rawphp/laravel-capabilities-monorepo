# REQ-046: Config-wired registry path


**UR:** UR-008
**Status:** done
**Created:** 2026-07-27
**Layer:** none
**Entry point:** Laravel container resolves `CapabilityRegistry` (or `Capability::invoke` / facade) after package boot with published or default `config/capabilities.php`
**Terminal state:** The shared registry singleton applies surface/governance config and uses the same approval store, idempotency store, audit settings, and scope resolver as the configured container bindings — unit tests prove inject parity and no dual-manager drift
**Parent:**
**Closure proof:** checkpoint_log:passed (2/2) commit:8d48df2 children:REQ-047,REQ-048 archived
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/tests/Unit/Boot/RegistryFactoryPathTest.php
**Depends on:** REQ-047, REQ-048

## Task

Path-unit for closing the registry factory gap: app-facing invoke path uses one fully config-wired registry.

## Context

UR-008 item 1: "Registry factory fully applies config + injects approval/idempotency/audit/scope." Today `makeRegistry()` ignores config and only sets `SystemClock`. Ideate risk: registry ApprovalManager vs container ApprovalManager diverge under concurrent accept/invoke.

## Acceptance Criteria

- [x] Path-unit children REQ-047 and REQ-048 are done and archived
- [x] Unit evidence shows container-resolved registry shares configured stores and applies listed config domains
- [x] Dual ApprovalManager/idempotency store divergence is covered by a failing-then-passing unit scenario

## Verification Steps

1. **test** `composer test:core -- --filter=RegistryFactory` (or equivalent filters from children)
   - Expected: all related unit tests pass
2. **test** `composer test:core`
   - Expected: suite green after path children land

## Manual checks (advisory)

- [x] Integrator following first-capability tutorial can enable `approval.store=database` and confirm invoke + approval accept share durability semantics — Observable outcome: no process-local-only approval after host gateway binding (after REQ-049–051)

## Assets

- (none)

## Outputs

- packages/laravel-capabilities/tests/Unit/Boot/RegistryFactoryPathTest.php — path-unit closure tests
