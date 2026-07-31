# Decisions

2026-07-27 | UR-001 | decompose ~5001 inventory scenarios into 19 path-unit REQs by foundation→pipeline→governance→surfaces→messaging→cli→green-gate | avoid 5000 micro-REQs and reduce footprint collisions
2026-07-27 | UR-001 | tests are SOT after intentional updates; docs/spec.md is conflict oracle before changing asserts | brief + AGENTS.md
2026-07-27 | UR-001 | harness load + shared fakes before domain implementation | suite currently fatals on Pest redeclare
2026-07-27 | UR-001 | unit-only + ≥95% coverage blocking; no feature/DB tests | AGENTS.md monorepo policy
2026-07-27 | UR-002 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-002 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-002 | path-unit + 4 core glue children (routes, discovery, config bindings, artisan); apply UR-001 pure tables in provider | avoid reimplementing domain
2026-07-27 | UR-003 | scaffold-sync only for test gaps (not re-implement inventory scenarios) | user chose minimal capture at classify gate
2026-07-27 | UR-003 | other-as-bug-fix; layers_in_scope empty; all REQs layer none | no new product surface
2026-07-27 | UR-003 | order harden-generator → rename go todo tests → inventory status sync → gap report | prevent regen wipe and path thrash
2026-07-27 | UR-004 | layer "messaging" out of scope | core persistence only (user gate pending — capture will confirm)
2026-07-27 | UR-004 | layer "cli" out of scope | core persistence only (user gate pending — capture will confirm)
2026-07-27 | UR-004 | ship approvals+idempotency drivers + shared migrations including audit_outbox schema; no separate audit writer REQ this UR | brief stresses approval/idempotency; schema matches spec layout
2026-07-27 | UR-004 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-004 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-005 | registry constructor + makeRegistry default SystemClock; tests inject FixedClock | align Clock contract and avoid frozen singleton in apps
2026-07-27 | UR-006 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-006 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-006 | D-011 residual via matrix+fixtures+release-gate docs; no live peers in default package CI | monorepo unit-only policy
2026-07-27 | UR-007 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-007 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-008 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-008 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-008 | ship first-party QueryTableGateway + host override docs (not document-only) | brief lists first-party first; closes durable residual
2026-07-27 | UR-008 | Packagist/tag publish is human-gated advisory; REQs cover prep + checklist only | no package credentials in monorepo CI
2026-07-27 | UR-008 | path units: registry wiring, durable gateway, pre-stable release prep | three brief items as three reachable paths
2026-07-27 | UR-009 | bug-fix single REQ plan+provider+tests | avoid plan/provider drift and second registry singleton
2026-07-27 | UR-009 | layers messaging/cli not prompted | bug-fix classification; core package DI only
2026-07-28 | UR-010 | one REQ: shared subcommand --help early-exit for mcp+siblings (not mcp-only) | avoid leaving same footgun on catalog/run/describe/approvals
2026-07-28 | UR-011 | layer "core" out of scope | user answered "No" at layer-coverage prompt
2026-07-28 | UR-011 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-28 | UR-011 | package-owned GoReleaser release on mirrored v* tags; secret-gated full platform signing; replace release on retag | question + capture
2026-07-31 | UR-012 | architecture-analyst fix capture prioritized critical/high/QW; deferred L-006 X-005 L-011 polish | focused ship
