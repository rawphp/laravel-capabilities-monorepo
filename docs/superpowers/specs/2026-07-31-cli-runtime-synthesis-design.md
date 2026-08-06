# Design: CLI runtime command synthesis from catalog

**Status:** Approved for implementation planning  
**Date:** 2026-07-31  
**Packages:** `rawphp/capabilities-cli` (primary), `rawphp/laravel-capabilities` (catalog metadata)  
**Related:** monorepo `docs/spec.md` (D-004, D-009, D-015, D-016, D-018); product CLI user guide  

## Summary

Extend the existing Go product CLI (`capabilities`) so that, after auth and catalog cache load, it **synthesizes a domain/verb command tree from the remote catalog** and treats **scalar flags and JSON input as equal invoke paths**. Every capability command exposes **schema-first help** (field name, type, required, pass mode). Default invoke I/O is **agent-first**: stable JSON envelopes on stdout.

This is **runtime synthesis**, not codegen and not Artisan. No domain `run()` on the laptop. Same HTTP capability API (`caller: cli` server-derived).

### One-liner

> One binary: discover catalog → `--help` shows expected schema → invoke with flags or JSON → parse envelope.

---

## Goals and non-goals

### Primary audience

1. **Agents** (local shells, scripts, agent runtimes) — stable commands, discoverable schemas, machine I/O, exit codes, idempotency.
2. **Humans** (operators, developers) — same binary; optional human summary; flags for simple flat inputs.

### Goals

- Discover what can be called (`catalog`, domain help, capability help).
- Show what must be passed and what types (`--help` on every capability command is the primary schema surface).
- Invoke via **flags and/or** `--input` / `--input-file` with one validation + HTTP path.
- Shape: `capabilities <domain> <verb> …` when mappable; `capabilities run <name> …` always.
- Remain law-abiding: local JSON Schema is UX; server always re-validates; no dual mutation path.

### Non-goals

- Compile-time codegen / typed SDK generation (optional future; not this design).
- Artisan as the product CLI.
- Embedding application domain logic in Go.
- Inventing `create-invoice` → `invoices create` via English/NLP pluralization.
- Dotted/indexed flags for nested objects and arrays.
- ~~Replacing MCP stdio (it remains an alternate agent surface).~~ **Superseded (ORI-791/792):** CLI MCP stdio was hard-removed; product MCP is server-side `laravel/mcp` only.
- Dumping full schemas for every capability at root help.

### Success criteria

- An agent can: `catalog --json` → `<domain> <verb> --help --json` → invoke → parse stdout envelope → branch on exit code / error code — without reading human prose.
- A human can: same loop with readable `--help` tables and optional `--human` summary.
- Unmapped capabilities remain fully usable via `run` / `describe`.

---

## Approach

**Runtime synthesis inside the existing `capabilities` binary** (not a second product binary, not codegen-first).

After login / catalog refresh, the CLI indexes catalog entries and:

1. Resolves domain + verb → canonical capability name (see mapping rules).
2. Formats help from `input_schema` / `output_schema`.
3. Accepts scalar flags and/or JSON, merges to one object, validates, POSTs via the existing run pipeline.

---

## Command tree

### Reserved meta-commands

These always win at `argv[0]` and must never be registered as domains:

`auth` · `catalog` · `describe` · `run` · `mcp` · `approvals` · `version` · `help`

Global flags remain available: `--profile`, `--base-url`, `--no-cache`, and human/machine helpers as defined below.

### Synthesized capability commands

```text
capabilities <domain> <verb> [scalar flags | --input=… | --input-file=…] [shared invoke flags]
```

Examples:

```bash
capabilities invoices create --customer-id=42 --amount-cents=2500 --currency=USD
capabilities invoices create --input='{"customer_id":42,"amount_cents":2500,"currency":"USD"}'
capabilities run create-invoice --input='…'
```

### Discovery commands

| Command | Role |
|---|---|
| `capabilities catalog [--json] [--refresh]` | List capabilities; include mapping fields when known |
| `capabilities <domain> --help` | List verbs under domain + one-liners |
| `capabilities <domain> <verb> --help` | **Primary schema surface** (field table + types) |
| `capabilities <domain> <verb> --help --json` | Machine `capability_help` envelope |
| `capabilities describe <name>` | Existing HTTP-aligned schema fetch (canonical name) |
| `capabilities run <name> --help` | Same field model for unmapped or explicit run path |

### Argv dispatch

1. If `argv[0]` is a reserved meta-command → existing handlers.
2. Else if `argv[0]` is a known domain in the profile catalog index:
   - Missing verb or domain-level `--help` → domain help.
   - Verb present → capability command (or capability `--help`).
3. Else → unknown command: envelope + hint to `catalog` / `run`.

Root help lists reserved commands and a short pointer to capability discovery (`catalog`, domain/verb pattern). It does **not** print full schemas for the entire catalog.

---

## Name mapping (registry ↔ domain + verb)

Resolution order when building the synthesis index:

| Priority | Source | Example |
|---|---|---|
| 1 | Catalog metadata `cli.domain` + `cli.verb` | `create-invoice` + `{domain:invoices, verb:create}` → `invoices create` |
| 2 | Mechanical name split only | `invoices.create` or `invoices/create` → single split on first `.` or `/` |
| 3 | No synthesis | Kebab-only names without metadata → **`run <name>` only** |

### Hard rules

- **No** pluralization, verb lists, or inventing domain/verb from `create-invoice`.
- If metadata is incomplete (only domain or only verb) → treat as **unmapped** (fail closed for synthesis).
- Domain and verb tokens: lowercase `[a-z][a-z0-9-]*`.
- Domain must not collide with reserved meta-command names.
- **Aliases** resolve to the canonical capability name; help shows canonical + aliases.
- Only capabilities effectively exposed on the **`cli` surface** (and reachable over HTTP per product rules) participate in synthesis. Others may still appear in broader catalog views per server policy but are not synthesized for this caller when the server filters by CLI surface.

### Collisions

- **Server (authoritative):** two definitions claiming the same `(domain, verb)` → **boot / register failure**.
- **Client (defense in depth):** on cache index build, if two names map to the same pair → **register neither** for synthesis; both remain available via `run <name>`; surface a `mapping_error` (or equivalent) on affected `catalog --json` rows so agents can detect the fault.

---

## Invoke paths (flags and JSON)

### Equal first-class inputs

| Source | Form | Use |
|---|---|---|
| Flags | `--field-name=value` (kebab-case of schema property) | Flat **scalar** properties only |
| JSON | `--input='{…}'` or `--input-file=path` | Full object and all nested/complex payloads |

### What becomes a flag

From JSON Schema `properties` on the capability input object:

| Schema shape | CLI |
|---|---|
| `string`, `integer`, `number`, `boolean` | Flag `--prop-name` |
| Scalar `enum` | Flag; validate against enum |
| Optional scalar | Optional flag; omit ⇒ property absent |
| `object`, `array`, or non-plain-scalar union | **json-only** (no flag) |
| Free-form maps / `additionalProperties` bags | **json-only** |

Canonical flag spelling for agents: **kebab-case** of the property name (`customer_id` → `--customer-id`). Accepting snake_case as an alias is optional DX; document kebab as canonical.

Booleans: `--flag` / `--flag=true|false`. Bare `--no-flag` is optional later, not required for v1.

### Merge rule

1. Base object = parsed `--input` / `--input-file` if present, else `{}`.
2. Apply each provided scalar flag as a **top-level** property (after flag → schema property name map).
3. On key conflict, **flag wins** (deterministic for agents).
4. Unknown flags → exit 2.
5. Flag targeting a json-only property → exit 2 with a clear message.
6. After merge, local JSON Schema validation; missing required fields → exit 2 (point user/agent at `--help`; do **not** require dumping the full schema on every validation error).

### Shared invoke flags

| Flag | Role |
|---|---|
| `--input` / `--input-file` | JSON body |
| `--idempotency-key` | Manual key (default: new UUID) |
| `--retry-last` | Reuse last key (existing behavior) |
| `--no-cache` | Re-fetch schema |
| `--profile` / `--base-url` | Auth target |
| `--tenant` | Tenant **hint** only (D-003); not authoritative scope |
| `--human` | Human summary on **stderr**; stdout envelope unchanged |

### Validation and HTTP pipeline

```text
parse argv → merge flags + JSON
  → load schema (cache / describe)
  → local JSON Schema validate → fail exit 2 + envelope
  → ensure Idempotency-Key
  → POST /capabilities/{canonicalName}
  → map response → exit code + envelope on stdout
```

Server always re-validates and authorizes. Local validation is UX and agent efficiency only.

Empty invoke with all-optional schema may POST `{}`. Empty invoke with required fields fails locally (exit 2).

---

## Schema help UX (primary contract surface)

### Principle

Help is the contract surface. Agents and humans learn inputs from `--help` (and machine help), not from buried documentation. `describe` remains the raw/catalog-aligned schema fetch; help is the **formatted** view of the same cached schema.

### Human capability help

`capabilities <domain> <verb> --help` prints at least:

- Name (domain verb), canonical capability name, `schema_version`
- Description
- **INPUT** table: name, type, required, constraints (when known), flag name **or** `json-only`
- **OUTPUT** summary when `output_schema` is available
- Examples: flags (if any scalars) and JSON
- See also: `describe`, `run`

Every input row must expose: **name, type, required, pass mode (flag vs json-only)**.

### Machine capability help

```bash
capabilities invoices create --help --json
# equivalent: capabilities help invoices create --json
```

Stdout envelope shape (normative fields; exact nesting may use the shared D-018 envelope with `data`):

```json
{
  "ok": true,
  "data": {
    "kind": "capability_help",
    "domain": "invoices",
    "verb": "create",
    "name": "create-invoice",
    "description": "…",
    "schema_version": "1",
    "input_schema": {},
    "output_schema": {},
    "fields": [
      {
        "name": "customer_id",
        "type": "integer",
        "required": true,
        "flag": "--customer-id",
        "pass": "flag",
        "constraints": { "minimum": 1 }
      },
      {
        "name": "line_items",
        "type": "array",
        "required": false,
        "flag": null,
        "pass": "json-only",
        "constraints": {}
      }
    ],
    "examples": {
      "flags": "capabilities invoices create --customer-id=42 --amount-cents=2500 --currency=USD",
      "json": "capabilities invoices create --input='{\"customer_id\":42,…}'"
    }
  }
}
```

For `run <name> --help --json`, `domain` / `verb` may be null when unmapped.

### Domain help

`capabilities <domain> --help` lists verbs with one-line descriptions and canonical names. `--json` yields a machine list.

### Catalog enrichment

List rows should include when known:

- Existing: `name`, `description`, `schema_version`, `surfaces`, deprecation fields, aliases
- New: `cli.domain`, `cli.verb` (or nested `cli` object)
- Derived client-side optional: `mapped_command` (e.g. `"invoices create"`) or null
- Mapping faults: error marker when collision suppressed synthesis

---

## I/O and exit codes

### Invoke streams

| Stream | Content |
|---|---|
| **stdout** | Always D-018-style **JSON envelope** for capability invokes (success and failure). Agents parse stdout only. |
| **stderr** | Deprecation warnings; optional `--human` summary; non-critical hints |
| **`--human`** | Adds human summary on stderr; **does not** remove or replace stdout envelope |

Machine help (`--help --json`) also prints a JSON envelope on stdout and exits 0.

Legacy `--json` on invoke may remain as an explicit alias/no-op for compatibility; default invoke output is already the machine envelope (agents must not need `--json` to get structured output).

### Exit codes (stable)

| Code | Meaning |
|---|---|
| 0 | Success |
| 1 | Internal error |
| 2 | Validation / usage (bad flags, schema fail, bad local input) |
| 3 | Unauthenticated / forbidden |
| 4 | Approval required |
| 5 | Domain error / conflict / **not_found** (unknown domain, verb, or capability) |
| 6 | Rate limited |

Unknown domain or verb → **exit 5** (`not_found` class). Bad flags / type errors → **exit 2**.

### Schema freshness

- `auth login` and `catalog --refresh` populate / refresh the profile cache.
- Help and invoke use cache; `--no-cache` forces re-fetch of describe/schema.
- `schema_version` / etag invalidate stale entries per existing catalog design.

---

## Server contract (core package)

Additive, backward-compatible catalog metadata.

### Definition API (illustrative)

Attribute and fluent authors may set optional CLI routing:

```php
#[Capability(
    name: 'create-invoice',
    description: 'Create an invoice for a customer.',
    cliDomain: 'invoices',
    cliVerb: 'create',
)]
```

```php
Capability::define('create-invoice')
    ->cli('invoices', 'create')
    // …
```

Exact PHP property names are an implementation detail; the **wire** contract is normative.

### Catalog wire

List and describe entries may include:

```json
{
  "name": "create-invoice",
  "description": "Create an invoice for a customer.",
  "surfaces": ["agent", "mcp", "http", "cli"],
  "schema_version": "1",
  "cli": {
    "domain": "invoices",
    "verb": "create"
  },
  "input_schema": {},
  "output_schema": {}
}
```

Rules:

- Omit `cli` when unmapped (old clients ignore unknown fields; new clients treat as unmapped).
- If `cli` is present, **both** `domain` and `verb` are required; incomplete objects fail at definition build/boot.
- Domain/verb tokens validated; reserved meta names rejected for domain.
- Duplicate `(domain, verb)` across definitions → register/boot failure.
- `cli` is routing metadata only — **not** a second schema. JSON Schema remains the only portable input/output contract (D-004 / D-015).

Touch points: `CapabilityDefinition`, `#[Capability]`, fluent builder, discovery, `CatalogPresenter` metadata, unit tests. **No second HTTP invoke tree** (D-009).

---

## CLI architecture (Go)

### Layout

```text
cmd/capabilities/           # argv: reserved vs domain/verb
internal/
  catalog/                  # summaries + cli metadata; cache
  synth/                    # map catalog → domain/verb index; collisions
  helpfmt/                  # human tables + capability_help envelopes
  flagschema/               # schema → flags; merge; scalar vs json-only
  run/                      # existing validate → idempotency → POST
  api/, auth/               # HTTP client roles (mcpstdio/ removed — historical)
```

### Component responsibilities

| Unit | Responsibility | Dependencies |
|---|---|---|
| `synth` | Domain→verb→canonical name index; collision policy | catalog entries |
| `flagschema` | Flag defs from schema; merge flags+JSON; reject invalid | `input_schema` |
| `helpfmt` | INPUT table + machine `fields[]` from same model | flagschema + entry |
| `run` | Local validate, idempotency, POST | api, catalog |
| `Execute` | Dispatch reserved vs synthesized | synth, run, helpfmt |

### Data flow

```text
auth login / catalog refresh
        → cache summaries + schemas + cli metadata
        → catalog / domain help / verb help (helpfmt)
        → <domain> <verb> [flags|input]
              → flagschema.merge
              → run pipeline
              → stdout envelope
```

---

## Edge case matrix

| Situation | Behavior | Exit |
|---|---|---|
| Unknown domain | Envelope; hint `catalog` | 5 |
| Known domain, unknown verb | Envelope; verb list available via domain help | 5 |
| Unmapped kebab used as domain | not_found for synthesis | 5 |
| Missing required after merge | Local validation fail; point to `--help` | 2 |
| Unknown flag | Reject | 2 |
| Flag on json-only field | Reject | 2 |
| Invalid scalar type on flag | Reject | 2 |
| JSON + flags same key | Flag wins | — |
| All fields optional, empty invoke | POST `{}` | server outcome |
| Not logged in | Auth error | 3 |
| Stale schema | Prefer refresh; server remains law | 2/5 per response |
| Approval required | Existing path | 4 |
| Deprecated capability | Invoke allowed; warn stderr + meta | 0 if success |
| Sunset capability | Server deny; help may warn | 5 |
| Client mapping collision | No synth path; `run` works; catalog error marker | — |
| Server mapping collision | Must not boot/register | — |
| Reserved word as domain in metadata | Reject at server; never synthesize | — |
| Name `invoices.create` without metadata | Mechanical map | — |
| Name `create-invoice` without metadata | No synth; use `run` | — |
| `--human` on invoke | Envelope stdout; summary stderr | — |
| `--help --json` | `capability_help`; no invoke | 0 |

### Canonical agent loop

```text
capabilities catalog --json
capabilities invoices create --help --json
capabilities invoices create --input='…'   # or scalar flags
# parse stdout; branch on ok / error.code / process exit
```

### Canonical human loop

```text
capabilities catalog
capabilities invoices --help
capabilities invoices create --help
capabilities invoices create --customer-id=1 … --human
```

---

## Testing (unit only)

Monorepo policy: **unit tests only**, no feature/DB suites. Mock HTTP boundaries.

### Core PHP

- Present `cli` when both domain and verb set.
- Incomplete `cli` rejected.
- Invalid tokens / reserved domain rejected.
- Duplicate `(domain, verb)` fails register/boot.
- Omitted `cli` leaves catalog valid without mapping.
- Describe includes `cli` when set.

### Go CLI

- Reserved meta wins over domain names.
- Mapping priority: metadata → mechanical split → none.
- Collision disables synthesis for both.
- Flag generation: scalars, enums, json-only exclusion.
- Merge: flag wins; unknown flag; json-only flag rejected.
- Help: human columns + machine `fields[]` with types and `pass`.
- Invoke path reuses run law (validate → key → POST) with merged JSON.
- Exit codes for not_found vs validation vs auth.
- `--human` never strips stdout envelope.

---

## Implementation phases

1. **Core:** `cli` metadata on definitions + catalog wire + PHP unit tests.
2. **CLI discovery:** synth index, domain/verb dispatch, human + machine help (no invoke change required beyond help on `run`).
3. **CLI invoke:** flagschema merge + wire into existing run pipeline; dual path on synthesized commands and `run`.
4. **Docs:** CLI user-guide, agent quickstart, authoring `->cli()` / attribute fields; monorepo concepts cross-link if needed (no broken post-split relative links from package READMEs into monorepo-only paths).
5. **Optional later:** shell completion from cache; codegen SDKs; `--no-flag` booleans.

Phase 1 can ship alone (old CLIs ignore `cli`). Phases 2–3 are the user-visible product.

---

## Relationship to existing decisions

| Decision | Interaction |
|---|---|
| D-004 JSON Schema only in catalog | Unchanged; `cli` is routing metadata |
| D-009 one HTTP API | CLI remains client only |
| D-015 DTO → JSON Schema | Source of field types for help/flags |
| D-016 Go CLI | Extend same binary |
| D-018 error envelope | Default invoke stdout |
| Spec “codegen later” | Still later; this design is runtime synthesis |
| D-003 tenancy | `--tenant` remains hint only |
| D-005 idempotency | Unchanged; CLI still sends keys |
| D-022 caller derivation | Still server-derived from credentials |

---

## Explicit non-requirements

- Multi-language CLI matrix.
- Project-branded second binary.
- Progressive disclosure profiles for shell (server profiles may still filter catalog; client shows what catalog returns for the CLI principal).
- Interactive prompts / TUI wizards (out of scope; agents need non-interactive).

---

## Open implementation details (non-UX)

These may be fixed in the implementation plan without reopening product UX:

- Exact PHP attribute parameter names vs nested `cli:` array.
- Whether catalog omits the `cli` key or sends `cli: null` when unmapped (prefer **omit**).
- Exact D-018 top-level keys for help envelopes (`ok` + `data.kind` as sketched).
- Whether snake_case flag aliases ship in v1.

No TBD remains for product behavior covered above.
```
