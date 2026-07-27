---
ur: UR-004
received: 2026-07-27
status: intake
---

# UR-004: User Request

## Request

2. No production persistence drivers

• Migrations folder empty
• Default stores: in-memory approval / idempotency
• Contracts say production may use Eloquent — implementations are not shipped

Without DB drivers, approvals and idempotency do not survive process restart. That is fine for unit tests; it is not fine for agent retries in production.
