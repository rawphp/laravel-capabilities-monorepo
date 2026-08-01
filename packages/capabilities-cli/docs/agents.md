# Agents & MCP

How local agents, IDE tools, and automation should use `capabilities`.

Related: [User guide](user-guide.md) · [Authentication & profiles](authentication.md)

---

## Design rules (do not break these)

1. **HTTP client only** — no domain `run()` on the laptop.
2. **Server is law** — local JSON Schema validation is UX; the server always re-validates and authorizes.
3. **Caller is server-derived** from credentials — never send spoofed caller headers.
4. **Stdout is machine; stderr is human** — with `--json` / default envelopes, parse **stdout**. Optional `--human` writes a short summary to **stderr** only.
5. **Branch on exit code** — stable codes are part of the contract (see below).
6. **Pick a profile** — multi-product agents must pass `--profile=` so tokens never mix.

---

## Recommended agent loop

```bash
# 0. Ensure profile exists (human or bootstrap once)
capabilities auth status --profile="$PROFILE" || \
  capabilities auth login --profile="$PROFILE" --base-url="$BASE_URL" --token="$TOKEN"

# 1. Discover
capabilities catalog --json --profile="$PROFILE"

# 2. Optional: schema help for one capability
capabilities describe <canonical.name> --json --profile="$PROFILE"
# or, if domain/verb metadata exists:
capabilities <domain> <verb> --help --json --profile="$PROFILE"

# 3. Invoke
capabilities run <canonical.name> --profile="$PROFILE" --input="$JSON"
# or:
capabilities <domain> <verb> --profile="$PROFILE" --flag=value
```

### Discovery fields (`catalog --json`)

Rows may include client-side synthesis helpers:

| Field | Meaning |
|-------|---------|
| `cli.domain` / `cli.verb` | Routing metadata from the server when fully set |
| `mapped_command` | Client-derived `"domain verb"` string |
| `mapping_error` | Client suppressed synthesis (e.g. collision) — use `run <name>` |

Unmapped capabilities stay available via `run` / `describe` only.

Reserved meta-commands always win over domain tokens of the same name:

`auth` · `catalog` · `describe` · `run` · `mcp` · `approvals` · `version` · `help`

---

## Exit codes (stable)

| Code | Meaning | Agent action |
|------|---------|--------------|
| **0** | Success | Parse stdout envelope |
| **1** | Internal error | Retry once; then fail the turn with stderr |
| **2** | `validation_failed` | Fix input using `--help` / schema; do not retry same body |
| **3** | Unauthenticated / forbidden | Re-auth or wrong profile / host |
| **4** | `approval_required` | Surface to human; `approvals accept/reject` |
| **5** | Domain error / conflict / not_found / output_invalid | Read envelope; do not invent alternate paths |
| **6** | Rate limited | Back off and retry |

---

## Input shapes for invoke

```bash
# Full JSON
capabilities run <name> --profile=P --input='{"customer_id":1}'

# File
capabilities run <name> --profile=P --input-file=./payload.json

# Scalar flags (schema-driven) — flag wins over JSON key if both set
capabilities <domain> <verb> --profile=P --customer_id=1

# Hybrid
capabilities <domain> <verb> --profile=P --input='{"a":1}' --b=2
```

Rules:

- Base body = `--input` / `--input-file` or `{}`.
- Each scalar flag overwrites that key (**flag wins**).
- Object/array fields are **JSON-only** (no flag).
- Unknown flags or json-only fields as flags → exit **2**.
- Empty invoke with an all-optional schema may POST `{}`.
- Every run sends an `Idempotency-Key` (UUID unless `--idempotency-key` or `--retry-last`).

---

## MCP stdio bridge

```bash
capabilities mcp --profile=mesoprep
```

- Speaks MCP over **stdio**.
- Proxies `tools/list` and `tools/call` to the **same** remote HTTP capability API.
- Uses the stored profile token — no separate MCP auth store.
- **No** local domain authorize/run.

Wire your agent runtime’s MCP client to this process with the correct profile
(or a wrapper script that injects `--profile=`).

Example wrapper:

```bash
#!/usr/bin/env bash
exec capabilities mcp --profile=mesoprep
```

---

## Approvals

When invoke returns exit **4** / `approval_required`, a human (or trusted
automation) can:

```bash
capabilities approvals accept <id> --profile=P
capabilities approvals reject <id> --profile=P
```

---

## Anti-patterns

| Don’t | Do |
|-------|-----|
| Hard-code product capability names in generic agents | Discover via `catalog --json` for that profile’s host |
| Share one `default` profile across products | Named profiles per product |
| Parse human catalog text | Use `--json` envelopes |
| Put tokens in prompts or logs | Use `auth login` store; never echo tokens |
| Treat local validation as final | Always handle server error envelopes |
