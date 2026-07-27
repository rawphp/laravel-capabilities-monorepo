# Decisions

2026-07-27 | UR-001 | decompose ~5001 inventory scenarios into 19 path-unit REQs by foundation→pipeline→governance→surfaces→messaging→cli→green-gate | avoid 5000 micro-REQs and reduce footprint collisions
2026-07-27 | UR-001 | tests are SOT after intentional updates; docs/spec.md is conflict oracle before changing asserts | brief + AGENTS.md
2026-07-27 | UR-001 | harness load + shared fakes before domain implementation | suite currently fatals on Pest redeclare
2026-07-27 | UR-001 | unit-only + ≥95% coverage blocking; no feature/DB tests | AGENTS.md monorepo policy
2026-07-27 | UR-002 | layer "messaging" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-002 | layer "cli" out of scope | user answered "No" at layer-coverage prompt
2026-07-27 | UR-002 | path-unit + 4 core glue children (routes, discovery, config bindings, artisan); apply UR-001 pure tables in provider | avoid reimplementing domain
