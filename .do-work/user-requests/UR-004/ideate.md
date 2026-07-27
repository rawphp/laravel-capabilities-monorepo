# Ideate — UR-004

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **Survival across process restart is the product requirement, not “ship Eloquent for its own sake.”** The brief names empty migrations and in-memory defaults. The user-facing failure is agent/MCP/CLI retries and approval crash recovery (D-005 / D-006) after worker death or deploy — without durable stores, double-run risk and lost pending approvals. Eloquent is one implementation strategy the contracts already promise, not the only possible driver.
- **Unit-only monorepo policy still holds.** AGENTS.md forbids feature/DB tests and real MySQL/Postgres/SQLite in CI for package truth. Production drivers must still be unit-tested with fakes/mocks of the query layer (or a narrow in-memory fake of the DB connection abstraction), not `RefreshDatabase` end-to-end. Capture must not invent Feature suites.
- **Three tables in the design bible.** Spec package layout lists migrations for `approvals`, `idempotency`, and `audit_outbox` in core — not Telegram identities. Brief stresses approval + idempotency; audit outbox is related durability for D-010 and likely belongs in the same UR or an explicit out-of-scope decision.
- **UR-002 already made config select drivers but maps `database` → package memory with `package_default: true`.** Shipping real drivers means extending `ContainerBindings` so `approval.store=database` / `idempotency.driver=database` construct Eloquent (or query) implementations, not silent memory fallback.
- **Stakeholders:** host-app operators (migrate + config), agents that retry mutating tools, approval notifiers (resume after crash), package unit-test authors, multi-tenant SaaS (tenant_id on composite keys).

## Challenger — Risks & Edge Cases

- **Exactly-once under concurrency.** Approval `compareAndUpdate` / `claimLease` and idempotency put/find need transactional or conditional SQL semantics; naive Eloquent `update` races will double-run. In-memory store already models these; DB drivers must preserve the same contracts under concurrent accepts (unit-testable with sequential state machines + mocked connection expectations, or transaction fakes).
- **TTL / expiry.** Idempotency expired rows treated as missing; approval expiry. DB drivers need indexes and cleanup strategy (lazy on read vs scheduled prune). Missing cleanup balloons tables under agent load.
- **Multi-tenant composite keys.** Idempotency identity is (tenant, actor, capability, key). Wrong unique index → cross-tenant collisions or false misses.
- **Migrations empty vs publish tag already exists.** Provider publishes `capabilities-migrations` to empty folder — publishing empty is worse than shipping migrations. Host apps that migrate nothing stay on memory defaults silently.
- **Testing without a real DB is non-negotiable here.** Risk: workers open SQLite in tests to “prove” migrations. Prefer: pure migration schema assertions (string/file structure), driver unit tests against mocked query builders / fake PDO, contract tests shared with InMemory* (same scenario suite).
- **Audit outbox scope creep.** If only approval+idempotency ship, audit `database` driver remains half-done. Decide in capture whether audit_outbox is in this UR.

## Connector — Links & Reuse

- **Contracts already exist:** `Contracts\ApprovalStore`, `Contracts\IdempotencyStore`, `Contracts\AuditWriter` — implement, don’t redesign.
- **In-memory references:** `Support\InMemoryApprovalStore`, `Support\InMemoryIdempotencyStore` (and domain `Idempotency\IdempotencyStore` array-backed) define behavioural SOT for unit scenarios; DB drivers should pass the same scenario matrix with injected query fakes.
- **Config + bindings:** `config/capabilities.php` (`approval.store`, `idempotency.driver`, `audit.driver`), `Boot\ContainerBindings` (REQ-023) — wire new concretes.
- **Spec:** D-005 idempotency, D-006 approval SM + crash recovery, package layout migrations line, D-010 audit modes.
- **Standing decisions:** unit-only + ≥95% coverage; no feature/DB tests; UR-002 config-driven bindings as integration point.

## Summary

Ship durable approval and idempotency (and decide on audit_outbox) as Eloquent/DB drivers + migrations, wired through existing config/ContainerBindings, while keeping package tests unit-only with mocks/fakes of the DB boundary. The success metric is process-restart survival for agent retries and approval recovery — not a full Laravel feature suite against a live database.
