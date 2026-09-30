# Architecture Audit — laravel-capabilities-monorepo (iteration 4)

**Generated:** 2026-09-30
**Stack:** Laravel package monorepo (illuminate 11–13, PHP 8.2+; core bus + messaging + AI) + Go CLI (HTTP client only); Pest unit tests, PHPStan + Pint in CI; GitHub Actions tests + package split; no frontend
**Mode:** audit
**Audited HEAD:** edd3edd
**Previous runs:** ../2026-09-30-iter3/ (15), ../2026-09-30-iter2/ (28), ../2026-09-29-iter1/ (58)

Full finding detail (current/recommended state, rationale, implementation hints) is in `findings.json` next to this file.

## Summary

- **5** findings: **0** critical, **1** high, **1** medium, **3** low
- Effort: 3 trivial, 2 small
- **Loop trend:** iter1 58 → iter2 28 → iter3 15 → iter4 5
- All iteration-3 fixes verified. Two were incomplete: M-206 guards only the first LLM round (→ M-302), and C-202 doesn't reach warm caches (→ C-301). X-302 is doc debt left by L-201/M-203.
- M-301 is pre-existing, not caused by a fix. The `tenantOf` helpers exist at c82e624. M-202 made it visible. The fix belongs in core.

## Findings

| Id | Severity | Effort | Title |
|---|---|---|---|
| M-301 | high | small | Let an in-tenant approver accept: core reads approver and row tenant differently |
| M-302 | medium | small | Budget the whole AI turn, not each LLM call, against the claim_ttl job timeout |
| X-301 | low | trivial | Packagist checklist still omits the AI package and forbids a third one |
| X-302 | low | trivial | Iteration-3 fixes left D-021 notify row and core sibling allowlist stale |
| C-301 | low | trivial | Make the C-202 fix reach warm caches; --no-cache does not refresh them |

### M-301 (high)

The approval row's tenant comes from the ScopeResolver (`current_tenant_id`, or `'default-tenant'` if the user has none). `ApprovalManager::tenantOf()` and `ApprovalResumer::tenantOf()` read the approver's `tenant_id`. Neither the Telegram path nor the HTTP approval path passes a tenant option. Every accept or reject is therefore `forbidden` unless the actor has both attributes and they match. That includes a requester approving their own request on the default registry.

Fix: resolve the approver's tenant with the same ScopeResolver that stamped the row.

### M-302 (medium)

`RunTurnJob` gives the whole turn `claim_ttl` (120s) for up to 8 LLM rounds. The timeout check (M-104) and the retry deadline (M-206) both measure from the start of each call. A multi-round turn can therefore outlive its job and get the worker killed.

Fix: use a deadline for the whole turn in TurnRunner, and pass it to the client's retry decision.

### X-301, X-302, C-301 (low)

- **X-301:** the Packagist checklist in `docs/versioning.md` leaves out `rawphp/laravel-capabilities-ai`.
- **X-302:** two docs are out of date. The spec's D-021 notify row doesn't reflect L-201, and the core README's Redactor allowlist doesn't mention messaging.
- **C-301:** entries in the CLI's describe cache aren't versioned, and the CHANGELOG remedy (`--no-cache`) doesn't persist.

## Deferred (owner decisions)

| Item | Why it waits |
|---|---|
| AI tests on sqlite :memory: (EloquentTurnClaimTest, EloquentConversationStoreTest, MigrationModelsTest) | Test-policy decision |
| CLI `catalog.ResolveAlias` / `Cache.GetByETag`: unused, referenced by inventory tests | Delete-or-wire decision |
| Package-remote `main` possibly rolled back by the old v0.5.3 tag split run (pre-X-101) | Remote repair needs the owner |
| AI `llm.anthropic.max_tokens` default 64000 | Recorded host-parity decision |
| Core audit `required=true` fallback outbox is process-local | Documented limit (L-104) |
| Local branch `backup/analyst-loop-pre-filter` holds the stripped 10 MB binary (C-201) | Must never be pushed; delete after the PR |

## Appendix

| Auditor | Files scanned | Findings |
|---|---|---|
| laravel-auditor (core) | 24 | 0 |
| laravel-auditor-siblings | 41 | 2 |
| control-plane-auditor | 16 | 1 |
| cross-cutting-auditor | 41 | 2 |

- Nothing dropped, downgraded or merged.
- The M- prefix is non-standard and was kept deliberately.
- The cross-cutting auditor ran these gates on the audit tree, all green: Pint, PHPStan, gofmt, the inventory gap check, `tools/tests`, and the release self-test (55/55).
