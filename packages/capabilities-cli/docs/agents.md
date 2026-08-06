# Agents & MCP

How local agents, IDE tools, and automation should use `capabilities`.

Related: [User guide](user-guide.md) · [Authentication & profiles](authentication.md)

---

## Design rules (do not break these)

1. **HTTP client only** — no domain `run()` on the laptop.
2. **Server is law** — local JSON Schema validation is UX (type, required, structure, and a portable string-`format` subset); the server always re-validates and authorizes (D-004). Do not treat local pass as final.
3. **Caller is server-derived** from credentials — never send spoofed caller headers.
4. **Stdout is machine; stderr is human** — parse **stdout** envelopes only. Optional `--human` writes a **short one-line** summary to **stderr** (not full JSON). Never parse `--human` stderr for structured data.
5. **Branch on exit code** — invoke/error codes **1–6** are stable. **Help/usage paths exit 0** (bare `capabilities`, `… --help`, bare `approvals`) — do not treat those as failure.
6. **Pick a profile** — multi-product agents must pass `--profile=` so tokens never mix.
7. **Globals may lead** — `--profile=NAME` / `--base-url=URL` work before or after the subcommand.

---

## Recommended agent loop

```bash
# 0. Ensure profile exists (human or bootstrap once)
capabilities auth status --json --profile="$PROFILE"
# if not logged_in →:
#   capabilities auth login --profile="$PROFILE" --base-url="$BASE_URL" --token="$TOKEN"

# 1. Discover (include schemas in one round-trip when you need them)
capabilities catalog --json --include-schemas --profile="$PROFILE"

# 2. Optional: schema help for one capability
capabilities describe <canonical.name> --json --profile="$PROFILE"
# or, if domain/verb metadata exists:
capabilities <domain> <verb> --help --json --profile="$PROFILE"
# or:
capabilities run <canonical.name> --help --profile="$PROFILE"

# 3. Invoke (stdout = envelope; omit --human for agents)
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
| **0** | Success **or help/usage** | Parse stdout envelope when you invoked a real command; bare help is not an error |
| **1** | Internal error | Retry once; then fail the turn with stderr |
| **2** | `validation_failed` | Fix input using `--help` / schema; do not retry same body. Includes **local** schema failures (no network) |
| **3** | Unauthenticated / forbidden | Re-auth or wrong profile / host |
| **4** | `approval_required` | Surface to human; `approvals accept/reject` |
| **5** | Domain error / conflict / not_found / output_invalid | Read envelope on **stdout**; do not invent alternate paths |
| **6** | Rate limited | Back off and retry |

**Help/usage (exit 0):** bare `capabilities`, `capabilities help …`, `… --help`, bare `approvals`.  
**Not help (exit 2):** `approvals accept` / `reject` without `<id>`; invalid flags / local schema failures (type, required, structure, **and** string formats).

### Local schema + string formats (exit 2, no network)

Before any HTTP invoke, `ValidateLocal` checks the catalog input schema with a
portable subset. Failures exit **2** with field-level stderr such as
`local schema validation failed (date: invalid date format (expected YYYY-MM-DD))`
— no request is sent.

Enforced string `format` values (aliases in parentheses):

| Format | Expectation (local) |
|--------|---------------------|
| `date` | `YYYY-MM-DD` (calendar-valid) |
| `date-time` (`datetime`) | RFC3339-style (`…T…Z` or offset) |
| `time` | `HH:MM:SS` (optional fractional seconds) |
| `email` | simple `local@domain.tld` shape |
| `uri` (`url`) | contains `://` or starts with `/` |
| `uuid` | 8-4-4-4-12 hex |

Unknown formats are **not** enforced locally. This subset may **false-reject**
values the server would accept (loose email/URI/date-time variants); always
handle server error envelopes as well (D-004).

On failure for `describe` / domain not-found style paths, a D-018 error envelope may appear on **stdout** (same idea as invoke). Always prefer stdout JSON over stderr text.

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
- Local schema failures (missing required, wrong type, **invalid string format**) → exit **2**, no network.
- Empty invoke with an all-optional schema may POST `{}`.
- Every run sends an `Idempotency-Key` (UUID unless `--idempotency-key` or `--retry-last`).

---

## MCP stdio bridge

```bash
capabilities mcp --profile=mesoprep
```

- Speaks MCP over **stdio**.
- Proxies `tools/list` and `tools/call` to the **same** remote HTTP capability API.
- `tools/list` requests `include_schemas=1`; each tool has a non-null `inputSchema` object (empty object if the server omitted schema).
- Uses the stored profile token — no separate MCP auth store.
- **No** local domain authorize/run.
- MCP `notifications/*` (e.g. `initialized`) are ignored (no JSON-RPC error reply).

### `tools/call` error.data (wire keys only)

On tool failure, MCP `error.data` is a D-018-shaped map. **Snake_case wire keys only** — never Go field names:

| Key | Meaning |
|-----|---------|
| `code` | D-018 error code (e.g. `validation_failed`) |
| `message` | Human message |
| `violations` | Optional `[{field, message}, …]` |
| `approval_id` | Present for `approval_required` (may be null) |
| `retryable` | bool |
| `http_status` | HTTP status from the capability API |
| `cli_exit` | Process exit code this CLI would use for the same error |
| `request_id` | Optional |

Raw HTTP bodies are **not** embedded in `error.data`.

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

- Bare `capabilities approvals` → usage, exit **0** (no auth required).
- `accept` / `reject` without `<id>` → stderr usage hint, exit **2**.

---

## Anti-patterns

| Don’t | Do |
|-------|-----|
| Hard-code product capability names in generic agents | Discover via `catalog --json` (add `--include-schemas` when needed) |
| Share one `default` profile across products | Named profiles per product |
| Parse human catalog text or `--human` stderr | Use stdout `--json` / default envelopes |
| Parse MCP `error.data.Code` / `HTTPStatus` | Use wire keys `code`, `http_status`, … |
| Treat bare CLI / help exit 0 as “nothing ran” failure | Help exits 0 by design |
| Put tokens in prompts or logs | Use `auth login` store; never echo tokens |
| Treat local validation as final | Always handle server error envelopes |
