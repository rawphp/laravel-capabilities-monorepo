# Ideate — UR-001

**Reviewed:** 2026-07-27

## Explorer — Assumptions & Perspectives

- **Scale is 5001 unit scenarios, not a single feature.** The brief says "implement all the tests and business logic"; inventory + stubs already list ~4497 core / 237 messaging / 267 CLI cases across ~255 files. A flat "do everything" decomposition without path-units or dependency order will thrash workers on overlapping `src/` and never green the suite. Trigger: "implement all… all tests may pass."
- **Empty `->todo()` stubs are not yet the source of truth.** Today stubs only name the scenario; they have no asserts, fakes, or behavioural contracts. Making tests SOT requires implementing assertions *and* production code together (TDD per module), not "fill in green later." Scenario: a worker marks todos complete with weak asserts and coverage games — package behaviour drifts from `docs/spec.md`. Trigger: "tests are source of truth for the packages."
- **Spec is secondary only when a test is wrong or incomplete.** Gaps/conflicts need a documented rule: prefer implemented test behaviour after intentional update; until then, read `docs/spec.md` (D-00x, pipeline order, refuse tables) and fix the stub/assert. Scenario: stub title contradicts pipeline order in spec — worker invents API. Trigger: "if gaps or conflicts, read docs/spec.md… and update the tests."
- **Suite cannot load today.** `composer test:core` fatals on Pest redeclare (`KeyFormatMatrixTest` duplicate evaluable names from generator collisions). Zero implementation progress is measurable until the harness loads. Scenario: workers "implement" while CI can't enumerate tests. Trigger: "all tests may pass."
- **Three packages, one monorepo choke point.** Core registry is law; messaging and Go CLI are adapters/clients. Stakeholders include: app authors defining capabilities, agent/MCP/HTTP/CLI/job callers, approvers, multi-tenant operators, and CI agents under unit-only + ≥95% coverage policy in `AGENTS.md`. Foggy: whether v0.1 stops at core bus only or includes messaging + CLI in this UR.

## Challenger — Risks & Edge Cases

- **Generator quality debt blocks the goal.** Auto-generated stubs have duplicate titles (Pest redeclare), matrix explosion (e.g. 135-case pipeline stage × caller matrices), and no shared fakes yet. Implementing business logic without first fixing uniqueness / shared test doubles will create unmaintainable per-file mocks. Scenario: two stubs same name → fatal before any green. Trigger: "all tests may pass."
- **Footprint / concurrency collisions on shared core.** Almost every core REQ will touch `CapabilityRegistry`, contracts, and pipeline stages. Without path-units ordered by foundation → pipeline → governance → surfaces → matrices, parallel workers will fight over the same files. Scenario: approval REQ and idempotency REQ both rewrite `InvokePipeline`. Trigger: "implement all… business logic."
- **95% coverage + unit-only is non-negotiable and easy to game.** Brief does not mention coverage, but monorepo policy does. Scenario: dead code or `@codeCoverageIgnore` to raise %; or workers reach for feature/DB tests when injectability is hard. Trigger: AGENTS.md Testing section vs brief silence on coverage.
- **"Update the tests" can become silent scope rewrite.** Without a decision that test renames/removals must stay inventory/generator-aligned (or intentionally supersede inventory), workers will delete hard stubs instead of implementing. Scenario: 5001 → 200 by pruning matrices. Trigger: "update the tests" on conflicts.
- **Peer adapters (`laravel/ai`, `laravel/mcp`) are mock-only.** Implementing AI/MCP surfaces without in-memory fakes of peer contracts will tempt live peer installs. Scenario: boot rules tests try real package versions. Trigger: surfaces in inventory + D-011.

## Connector — Links & Reuse

- **Contract scaffold already exists.** `docs/requirements-inventory.md`, `tools/generate_requirement_stubs.py`, and package `tests/Unit/**` stubs are the decomposition map — capture should group by inventory modules/files, not invent a parallel task graph.
- **Spec is the second source.** `docs/spec.md` decisions D-002–D-023, pipeline stages, approval SM, idempotency, audit modes, surfaces/boot rules — reuse as assert oracles when fleshing stubs.
- **Src is empty class stubs.** e.g. `CapabilityRegistry` is a final empty class — implement behind interfaces with in-memory fakes so every module can unit-test without DB (AGENTS.md).
- **Package order for reuse:** core types/registry first → HTTP/catalog/approval stores → AI/MCP/job adapters → messaging (core contracts only) → Go CLI HTTP client. Cross-cutting: error envelopes, caller derivation, scope, audit, rate limit — build once in core, assert from every surface matrix.
- **Test harness:** root `composer test` / `test:core` / `test:messaging` / `test:cli`; Pest unit only; Go `go test ./...`. Worktree should symlink `vendor` (config already sets `worktree.link_paths`).

## Summary

This UR is a full monorepo implementation of the capability bus contract: flesh ~5001 unit scenarios and the production code they describe until suites pass under unit-only + ≥95% coverage rules. Before feature REQs, the harness must load (fix generator/duplicate Pest names) and a shared fake/support layer must exist so modules do not re-invent doubles. Capture should decompose by package and inventory domain (foundation → pipeline → governance → surfaces → matrices → messaging → CLI), treat tests as SOT with `docs/spec.md` as conflict oracle, and sequence REQs so shared `src/` ownership does not deadlock parallel work.
