---
ur: UR-006
received: 2026-07-27
status: intake
---

# UR-006: User Request

## Request

4. Peer adapters are contract-shaped, not live-SDK-proof

AI/MCP adapters:

• map tools to arrays
• probe peers via class_exists
• do not require live laravel/ai / laravel/mcp in CI

That matches monorepo policy and is honest. It also means peer churn risk is not fully retired — D-011’s “contract tests against real minors” is still aspirational for a consumer app.
