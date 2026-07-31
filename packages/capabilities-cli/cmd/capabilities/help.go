package main

import (
	"strings"

	"github.com/rawphp/capabilities-cli/internal/helpfmt"
	"github.com/rawphp/capabilities-cli/internal/run"
)

// RootHelp is the top-level help text.
func RootHelp() string {
	return `capabilities — product capability CLI (HTTP client, D-016)

USAGE:
  capabilities <command> [flags]

COMMANDS:
  auth        Login / logout / status (keychain token store)
  catalog     List capabilities from the remote HTTP API
  describe    Show JSON Schema for a capability
  run         Validate locally, send Idempotency-Key, POST invoke
  mcp         MCP stdio bridge (proxies to remote API)
  approvals   Accept or reject pending approvals
  version     Print version
  help        Show help

FLAGS (common):
  --profile=NAME     Auth profile (default: default)
  --base-url=URL     Override deployment base URL
  --json             Print D-018 JSON envelopes

` + run.DocsExitCodes + `
` + `
NOTES:
  - Binary name is capabilities (not artisan / php artisan).
  - Single static Go binary; no Node or PHP required on the user machine.
  - No multi-language CLI matrix in v0.2 (Go only).
  - Local schema validation is UX only; server always re-validates.
  - Caller is server-derived from credentials — never spoof X-Capabilities-Caller.
  - Examples never embed domain business logic; they only call the HTTP API.

EXAMPLES:
  capabilities auth login --base-url=https://app.example.com
  capabilities catalog
  capabilities describe create-invoice
  capabilities run create-invoice --input='{"customer_id":42,"amount_cents":2500,"currency":"USD"}' --json
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
  capabilities auth status [--profile=NAME]

Tokens are stored in the OS config/keychain dir — never printed to stdout by default.
`
	case "catalog":
		return `catalog — list capabilities from GET /capabilities

USAGE:
  capabilities catalog [--json] [--no-cache] [--refresh] [--profile=NAME]
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
  --json                  print D-018 envelope
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
// Domain/Verb empty → run <name> usage (domain/verb null in machine form). Full dispatch is ORI-173.
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
