package main

import (
	"strings"

	"github.com/rawphp/capabilities-cli/internal/helpfmt"
	"github.com/rawphp/capabilities-cli/internal/run"
)

// RootHelp is the top-level help text.
// Lists reserved meta-commands and a short discovery pointer — not full schemas.
func RootHelp() string {
	return `capabilities — product capability CLI (HTTP client, D-016)

USAGE:
  capabilities <command> [flags]
  capabilities <domain> <verb> [flags]

RESERVED COMMANDS:
  auth        Login / logout / status (keychain token store)
  catalog     List capabilities from the remote HTTP API
  describe    Show JSON Schema for a capability
  run         Validate locally, send Idempotency-Key, POST invoke
  mcp         MCP stdio bridge (proxies to remote API)
  approvals   Accept or reject pending approvals
  version     Print version
  help        Show help

DISCOVERY:
  capabilities catalog                       domain index (human default)
  capabilities catalog --flat                 name → domain verb list
  capabilities catalog --json                 agent machine map (full rows)
  capabilities <domain> --help                list verbs under a domain
  capabilities <domain> <verb> --help         schema-first capability help (fields + pass mode)
  capabilities run <name>                     always works for unmapped / canonical names

FLAGS (common):
  --profile=NAME     Auth profile (default: default)
  --base-url=URL     Override deployment base URL
  --json             Print D-018 JSON envelopes
  (globals may appear before or after the command: capabilities --profile=P catalog)

` + run.DocsExitCodes + `
` + `
NOTES:
  - Binary name is capabilities (not artisan / php artisan).
  - Single static Go binary; no Node or PHP required on the user machine.
  - No multi-language CLI matrix in v0.2 (Go only).
  - Local schema validation is UX only; server always re-validates.
  - Caller is server-derived from credentials — never spoof X-Capabilities-Caller.
  - Examples never embed domain business logic; they only call the HTTP API.
  - Root help does not dump full input/output schemas for the catalog.

EXAMPLES:
  capabilities auth login --base-url=https://app.example.com
  capabilities catalog                       # domains available on this deployment
  capabilities catalog --json                # agents: full capability map
  capabilities <domain> --help
  capabilities <domain> <verb> --help
  capabilities describe <name>
  capabilities run <name> --input='{...}' --json
  capabilities mcp
`
}

// CommandHelp returns help for a top-level command.
func CommandHelp(cmd string) string {
	cmd = strings.TrimSpace(cmd)
	switch cmd {
	case "auth", "auth login", "auth logout", "auth status":
		return `auth — authenticate the CLI against a deployment

USAGE:
  capabilities auth login --base-url=URL [--token=PAT] [--code=OAUTH] [--profile=NAME]
  capabilities auth logout [--profile=NAME]
  capabilities auth status [--profile=NAME] [--json]

Tokens are stored in the OS config/keychain dir — never printed to stdout by default.
  --json   D-018 envelope {profile, base_url, logged_in} (never includes the token)
`
	case "catalog":
		return `catalog — discover capabilities from GET /capabilities

USAGE:
  capabilities catalog [--json|--flat] [--include-schemas] [--no-cache] [--refresh] [--profile=NAME]

MODES:
  (default)   Human domain index — domains + verb counts + next steps
  --flat      Name → domain verb lines (previous human default)
  --json      Agent machine envelope (mapped_command; compact unless --include-schemas)
  --include-schemas  With list/JSON: include input_schema/output_schema (one round-trip for agents)

Unauthenticated or empty synthesis:
  No synthesizable domains yet — use auth status, catalog --json, or run <name>.
`
	case "describe":
		return `describe — fetch JSON Schema for one capability

USAGE:
  capabilities describe <name> [--json] [--no-cache] [--profile=NAME]
`
	case "run":
		return `run — local JSON Schema validate, ensure Idempotency-Key, POST /capabilities/{name}

USAGE:
  capabilities run <name> --input=JSON|--input-file=PATH [flags]

FLAGS:
  --input=JSON
  --input-file=PATH
  --idempotency-key=KEY   manual key (default: new UUID)
  --retry-last            reuse last Idempotency-Key after network failure
  --no-cache              re-fetch schema
  --json                  print D-018 envelope (stdout always envelope; flag kept for agents)
  --human                 short ok/fail summary on stderr (stdout stays the envelope)
  --tenant=ID             tenant hint only (not authoritative scope)
  --profile=NAME
  --base-url=URL

` + run.DocsExitCodes
	case "mcp":
		return `mcp — MCP stdio bridge

USAGE:
  capabilities mcp [--profile=NAME] [--base-url=URL]

Proxies tools/list and tools/call to the remote HTTP capability API using the
stored CLI token. No local domain run or authorize.
`
	case "approvals":
		return `approvals — accept or reject pending approvals via HTTP

USAGE:
  capabilities approvals accept <id>
  capabilities approvals reject <id>
`
	case "version":
		return `version — print CLI version

USAGE:
  capabilities version
`
	case "help":
		return RootHelp()
	default:
		return RootHelp()
	}
}

// KnownCommands lists first-class commands for discovery tests.
var KnownCommands = []string{
	"auth", "auth login", "auth logout", "auth status",
	"catalog", "describe", "run", "mcp", "approvals", "version", "help",
}

// CommandExists reports whether a command is registered.
func CommandExists(name string) bool {
	for _, c := range KnownCommands {
		if c == name {
			return true
		}
	}
	// Also accept top-level only.
	switch name {
	case "auth", "catalog", "describe", "run", "mcp", "approvals", "version", "help":
		return true
	}
	return false
}

// CapabilityHelpHuman formats schema-driven human capability help (INPUT table, OUTPUT, examples).
// Domain/Verb empty → run <name> usage (domain/verb null in machine form).
func CapabilityHelpHuman(info helpfmt.CapabilityInfo) string {
	return helpfmt.FormatHumanCapability(helpfmt.BuildCapabilityHelp(info))
}

// CapabilityHelpJSON returns the machine capability_help envelope for --help --json (stdout; no invoke).
func CapabilityHelpJSON(info helpfmt.CapabilityInfo) []byte {
	return helpfmt.FormatMachineCapability(helpfmt.BuildCapabilityHelp(info))
}

// DomainHelpHuman lists domain verbs with one-line descriptions and canonical names.
func DomainHelpHuman(domain string, verbs []helpfmt.DomainVerb) string {
	return helpfmt.FormatHumanDomain(domain, verbs)
}

// DomainHelpJSON returns the machine domain_help list envelope (--json).
func DomainHelpJSON(domain string, verbs []helpfmt.DomainVerb) []byte {
	return helpfmt.FormatMachineDomain(domain, verbs)
}
