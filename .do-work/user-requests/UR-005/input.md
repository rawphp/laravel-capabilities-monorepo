---
ur: UR-005
received: 2026-07-27
status: intake
---

# UR-005: User Request

## Request

3. Clock default is a footgun

Registry constructor defaults to FixedClock('2026-07-27…'). Correct for tests; dangerous if the singleton is used as-is in apps. Production should bind SystemClock.
