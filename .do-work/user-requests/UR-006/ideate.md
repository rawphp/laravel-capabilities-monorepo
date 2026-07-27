# Ideate — UR-006

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **The brief is not asking to break unit-only CI.** Monorepo policy forbids live `laravel/ai` / `laravel/mcp` as package truth. Closing the D-011 gap means *reducing aspirational risk* without installing peers in default Pest CI: frozen wire-shape fixtures, explicit support matrix, release-gate documentation, and optional consumer-app jobs.
- **Two audiences.** Package maintainers need a release gate they can run honestly; consumer apps need a documented way to contract-test against the peers *they* install. The package can ship fixtures + a pest group + a matrix file; consumers wire the real SDKs.
- **Existing assets already half-solve this.** `AdapterApi`, `PeerVersionProbe` (injectable class_exists), `AiToolAdapterV1` / `McpToolAdapterV1`, `PeerSurfaceBootstrap`, and adapter unit tests prove shapes against mocks. The hole is: no version matrix beyond `*`, no frozen peer-facing contract snapshots, no optional CI path for real minors, no README release-gate checklist consumers can copy.
- **“Contract-shaped” is correct for core CI** — the risk is silent drift when peer minors change method names / tool registration APIs while `class_exists` still returns true.

## Challenger — Risks & Edge Cases

- **Requiring live peers in package CI would violate AGENTS.md.** Any REQ that needs `composer require laravel/ai` in default `composer test:core` is out of scope unless explicitly optional (`--group=peer-live` + not run in default suite).
- **class_exists is a weak compatibility signal.** A peer can be installed and still break tool registration. Need: (1) pinned version strings in a matrix when known, (2) interface-shape fixtures (method names / expected return shapes) that fail unit tests when adapters diverge, (3) AdapterApi bump discipline when our bridge changes.
- **False confidence from fakes.** Over-specified mock peers that don’t match real SDKs train adapters on fantasy APIs. Fixtures should be derived from public peer docs/interfaces and versioned with the matrix.
- **Matrix staleness.** A support matrix that nobody updates is worse than none. Capture should include a single source of truth file + tests that it is non-empty and referenced by probe defaults.
- **Scope creep into “install laravel/ai in monorepo.”** Resist. Prefer contract fixtures + optional documented consumer CI.

## Connector — Links & Reuse

- **Spec D-011:** support matrix, CI contract tests, AdapterApi versioning, fail/disable on incompatible, release gate.
- **Code:** `Adapters/AdapterApi.php`, `PeerVersionProbe.php`, `PeerSurfaceBootstrap.php`, `Ai/*`, `Mcp/*`, tests under `tests/Unit/Adapters/`.
- **Policy:** unit-only, mock peers; ≥95% coverage; no feature/DB tests.
- **Prior URs:** UR-001 built adapters; this UR hardens D-011 residual risk without rewriting the bus.

## Summary

Ship a real D-011 residual: explicit peer support matrix + frozen adapter contract fixtures + release-gate docs/checklist + optional (non-default) peer-live guidance for consumer apps — while keeping package CI free of live laravel/ai and laravel/mcp. Do not treat “install peers in monorepo CI” as the definition of done.
