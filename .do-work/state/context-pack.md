# Context pack — laravel-capabilities monorepo

## Product
Product capability bus for Laravel: one domain capability → many surfaces.
Packages: rawphp/laravel-capabilities (core), rawphp/laravel-capabilities-messaging, rawphp/capabilities-cli (Go).

## Layout
- packages/laravel-capabilities/src + tests/Unit
- packages/laravel-capabilities-messaging/
- packages/capabilities-cli/
- docs/spec.md (design oracle)
- docs/requirements-inventory.md (spec → unit checklist)
- tools/report_inventory_gaps.py, tools/sync_requirements_inventory.py, tools/generate_requirement_stubs.py

## Testing policy (hard)
- Unit tests only; zero feature/DB tests
- Mock IO boundaries; ≥95% coverage floor
- composer test:core | test:messaging | test:cli | test

## Inventory matching
- Gap report matches Pest titles to inventory cases (matrix-aware)
- Python False vs PHP false can drift titles
- happy:/fail: prefixes matter for RouteRegistration matrix

## Conventions
- tracker.backend: linear — commits: feat(ORI-N): … Issue: ORI-N / UR: UR-001
- Branch: req/ORI-N worktree: .worktrees/req-ori-n
- Claim marker: <!-- laravel-capabilities-claim -->
