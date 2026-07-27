---
ur: UR-002
received: 2026-07-27
status: intake
---

# UR-002: User Request

## Request

1. Thin service provider / incomplete Laravel glue

Provider:

• merges config
• binds registry, in-memory idempotency, default scope, metrics

It does not (from what I saw):

• loadRoutesFrom the capability HTTP tree
• auto-discover app/Capabilities
• construct the registry from full config/capabilities.php (surfaces, audit, approval, clients)
• register real Artisan commands beyond pure tables

Routes exist as a RouteTable definition for unit tests, not as a ready Laravel route file lifecycle.
