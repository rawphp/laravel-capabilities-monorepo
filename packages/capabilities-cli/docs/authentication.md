# Authentication & profiles

How `capabilities` stores credentials, how **one laptop talks to many projects**,
and how agents should select a profile.

Related: [User guide](user-guide.md) · [Agents & MCP](agents.md)

---

## What gets stored

Default config root:

```text
~/.config/capabilities/
  profiles/
    <profile>/
      token          # mode 0600 — never printed by auth status
      config.json    # { "base_url": "https://..." }
      schemas/       # catalog JSON Schema cache
      last_run.json  # last Idempotency-Key (for --retry-last)
```

| Item | Purpose |
|------|---------|
| **Profile name** | Isolates credentials per product/deployment (`default` if omitted) |
| **Base URL** | Deployment root used by the HTTP client |
| **Token** | Bearer credential; server derives `caller: cli` and authorization |

The CLI never embeds product domain logic. Authorization always happens on the
**server**. Do not spoof caller headers (for example `X-Capabilities-Caller`).

---

## Commands

```bash
capabilities auth login --base-url=URL [--token=PAT] [--code=OAUTH] [--profile=NAME]
capabilities auth logout [--profile=NAME]
capabilities auth status [--profile=NAME]
```

### Login modes

| Flags | Flow |
|-------|------|
| `--base-url` + `--token` | Store a PAT / API token directly |
| `--base-url` + `--code` | OAuth authorization-code exchange against the API |
| `--base-url` only | Device-code login against the API |

`login` **requires** `--base-url`. Successful login best-effort prefetches the
catalog into that profile’s schema cache.

### Status & logout

```bash
capabilities auth status --profile=mesoprep
# profile=mesoprep base_url=https://… logged_in=true
# (token is never printed)

capabilities auth logout --profile=mesoprep
# other profiles are unchanged
```

---

## Multi-project / multi-deployment (profiles)

**One global binary, many logins.** Use a **named profile per product** (or per
environment).

### Why profiles exist

| Situation | Without profiles | With profiles |
|-----------|------------------|---------------|
| MesoPrep + YardPilot on one machine | Overwrite `default` every switch | `mesoprep` and `yardpilot` side by side |
| Staging vs production | Easy to hit the wrong host | `yardpilot-staging` / `yardpilot-prod` |
| Agents / CI | Shared default is risky | Explicit `--profile=` in every command |

Tokens, base URLs, and schema caches are **isolated** per profile (covered by
package tests).

### One-time setup

```bash
# Product A
capabilities auth login \
  --profile=mesoprep \
  --base-url=https://mesoprep.example.com \
  --token="$MESOPREP_TOKEN"

# Product B
capabilities auth login \
  --profile=yardpilot \
  --base-url=https://yardpilot.example.com \
  --token="$YARDPILOT_TOKEN"

capabilities auth status --profile=mesoprep
capabilities auth status --profile=yardpilot
```

### Day-to-day

Pass `--profile=NAME` on **every** command that hits the network (or use shell
aliases — see below).

```bash
capabilities catalog --profile=mesoprep
capabilities run billing.create-invoice --profile=mesoprep --input='{"…":"…"}'

capabilities catalog --profile=yardpilot
capabilities jobs schedule --profile=yardpilot --flag=value
```

The same flag works on:

`auth` · `catalog` · `describe` · `run` · synthesized `<domain> <verb>` · `mcp` · `approvals`

### Base URL override

```bash
# one-off override without rewriting stored config
capabilities catalog --profile=mesoprep --base-url=https://staging.mesoprep.example.com
```

If a profile has a token but no base URL (corrupt/partial state), the CLI asks
you to re-login or pass `--base-url`.

### Profile name rules

Names are sanitized for the filesystem: letters, digits, `-`, `_`. Other
characters become `_`. Empty → `default`.

### Shell aliases (zero extra tools)

```bash
alias cap-meso='capabilities --profile=mesoprep'
alias cap-yard='capabilities --profile=yardpilot'

cap-meso catalog
cap-yard auth status
cap-meso run some.capability --input='{}'
```

### Inspecting stored profiles

There is no `auth list` command yet. Profiles are directories:

```bash
ls ~/.config/capabilities/profiles/
```

---

## Security notes

- Treat profile directories like secrets. Back up carefully; do not commit them.
- Prefer short-lived tokens or device/OAuth flows in shared environments.
- `auth status` is safe to share in screenshots (no token).
- MCP and agents inherit the **same** profile token — pick the profile deliberately.

---

## What is not supported (yet)

Optional polish not in the current binary:

1. `auth list` (list profile names)
2. `CAPABILITIES_PROFILE` environment default
3. Project-local default file (e.g. `.capabilities-profile` in a repo)

Until those exist, pass `--profile=` or use aliases.

---

## Failures related to auth

| Symptom | Exit | What to do |
|---------|------|------------|
| `auth login requires --base-url` | 2 | Pass `--base-url` |
| `not authenticated: run capabilities auth login` | 3 | Login for that profile |
| `missing base URL` | 3 | Re-login with base URL or pass `--base-url` |
| Server rejects token | 3 | New token / correct profile / correct host |
