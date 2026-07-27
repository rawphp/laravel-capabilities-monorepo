# Ideate — UR-008

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **“Fully applies config” is ambiguous about which keys.** Scenario: a worker wires surfaces + stores but omits rate limits, tool profiles, or audit mode; apps still see drift vs `config/capabilities.php`. The brief lists four injects (approval/idempotency/audit/scope) — capture must name the full factory surface or explicitly defer non-listed knobs.
- **App integrators vs package maintainers are different success criteria.** Scenario: Packagist publish succeeds but the registry factory still ignores config; consumer adoption score barely moves. Item 3 alone does not close the 7→8.5 gap without items 1–2.
- **Default driver policy is foggy.** Scenario: config defaults `approval.store=database` while `makeRegistry` still uses in-memory ApprovalManager inside the registry; operators believe durability is on. Stakeholders (ops, security, agent-retry reliability) care that defaults and singleton wiring match.
- **Audit “inject” may mean different things than approval/idempotency stores.** Scenario: brief says inject audit, but current code has `AuditLogger`/`AuditWriter`/`AuditOutbox` split; unclear whether inject is writer, mode flags, or outbox. Undefined here risks a vague REQ that never fails a test.

## Challenger — Risks & Edge Cases

- **Registry and ApprovalManager double-binding.** Scenario: SP binds a DatabaseApprovalManager singleton *and* registry constructs its own manager; accept path uses one store, invoke/needsApproval another — double-accept or lost approvals under concurrency. Factory must single-source the store/manager used by `invoke`.
- **Unit-only monorepo vs first-party Eloquent gateway.** Scenario: REQ adds Eloquent gateway that only works with a live DB; CI policy forbids feature/DB tests, so “first-party” ships unproven or forces mocks that miss query builder quirks (JSON columns, compareAndUpdate races). Prefer gateway with injectable connection/query builder fakes, not RefreshDatabase.
- **Packagist + tagged release is partly human/network gated.** Scenario: worker “implements” publish docs and tags locally but cannot create Packagist package or GitHub release without credentials; UR stalls or fakes done. Capture should split automatable release prep (composer metadata, version, changelog, publish checklist) from manual advisory publish.
- **Document-only option for TableGateway can re-open the gap.** Scenario: team picks “document 10-line host binding” and never ships Eloquent gateway; residual table still says durable stores not turnkey. Brief’s “or” needs an explicit product decision: ship package gateway vs document-only host binding.
- **Messaging/CLI layers out of scope historically.** Scenario: capture expands Packagist to all three packages without layer gate; messaging autoload flakes or CLI goreleaser scope bloats this UR. Prior decisions scoped messaging/cli out for glue URs — confirm scope for item 3.

## Connector — Links & Reuse

- **UR-002 / UR-004 / UR-005 already built glue foundations.** Routes, discovery, artisan (UR-002), database stores + migrations (UR-004), SystemClock (UR-005). This UR should wire, not re-implement stores or clocks.
- **`ContainerBindings::makeRegistry` is the exact hotspot** (`unset($config); return new CapabilityRegistry(clock: new SystemClock)`). Pair with `makeIdempotencyStore` / `makeApprovalManager` and registry `with*` injectors already on `CapabilityRegistry`.
- **`TableGateway` + `ArrayTableGateway` + `Database*Store` pattern** already isolates persistence; first-party Eloquent gateway should implement the same interface and remain unit-testable with a fake connection or recorded query builder.
- **Release honesty infrastructure exists:** `docs/versioning.md`, per-package CHANGELOGs, readiness residuals in root README, `PeerSupportMatrix`. Item 3 should update residuals/versioning rather than invent a second process doc.
- **Standing decisions:** unit-only + ≥95% coverage; no feature/DB tests (UR-001); messaging/cli often layer-out for core glue (UR-002/004/006/007).

## Summary

The highest-leverage work is item 1: make the shared registry singleton the same object path as configured stores and governance. Item 2 must choose ship Eloquent/query gateway vs document-only host binding without violating unit-only CI. Item 3 should be release *prep* (tags, metadata, docs) with Packagist publish as a human/manual gate unless credentials are explicitly in scope. Reuse UR-004 stores and existing `with*` APIs; do not fork a second bus.

## Lower confidence

- Whether default config should flip idempotency to database when approval is database (product preference, not forced by brief).
- Whether audit injection requires a new DatabaseAuditWriter in this UR or only mode/outbox wiring already present.
