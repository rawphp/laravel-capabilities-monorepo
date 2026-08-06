package main

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/helpfmt"
)

func TestHelpauth(t *testing.T) {
	if !strings.Contains(CommandHelp("auth"), "login") {
		t.Fatal()
	}
}
func TestHelpcatalog(t *testing.T) {
	if !strings.Contains(CommandHelp("catalog"), "catalog") {
		t.Fatal()
	}
}
func TestHelpdescribe(t *testing.T) {
	if !strings.Contains(CommandHelp("describe"), "Schema") {
		t.Fatal()
	}
}
func TestHelprun(t *testing.T) {
	h := CommandHelp("run")
	if !strings.Contains(h, "Idempotency") || !strings.Contains(h, "Exit codes") {
		t.Fatal(h)
	}
}
func TestHelpmcp(t *testing.T) {
	h := CommandHelp("mcp")
	if !strings.Contains(h, "stdio") {
		t.Fatal(h)
	}
	// Host wiring + auth prerequisite — primary MCP UX (avoid silent hang discovery).
	for _, need := range []string{"mcpServers", "auth login", "NOT AN INTERACTIVE", "tools/list", "--profile=NAME"} {
		if !strings.Contains(h, need) {
			t.Fatalf("mcp help missing %q\n%s", need, h)
		}
	}
}
func TestHelpapprovals(t *testing.T) {
	if !strings.Contains(CommandHelp("approvals"), "accept") {
		t.Fatal()
	}
}
func TestHelpexitcodestable(t *testing.T) {
	if !strings.Contains(RootHelp(), "Exit codes") {
		t.Fatal()
	}
}
func TestHelpexamplesdonotshowdomainlogic(t *testing.T) {
	h := RootHelp()
	if strings.Contains(h, "Eloquent") || strings.Contains(h, "DB::") {
		t.Fatal()
	}
	if !strings.Contains(h, "never embed domain") {
		t.Fatal(h)
	}
}

func TestCapabilityHelpHumanWiring(t *testing.T) {
	info := helpfmt.CapabilityInfo{
		Domain: "invoices",
		Verb:   "create",
		Name:   "create-invoice",
		InputSchema: map[string]any{
			"type":     "object",
			"required": []any{"customer_id"},
			"properties": map[string]any{
				"customer_id": map[string]any{"type": "integer"},
				"line_items":  map[string]any{"type": "array"},
			},
		},
	}
	text := CapabilityHelpHuman(info)
	if !strings.Contains(text, "INPUT") || !strings.Contains(text, "--customer-id") || !strings.Contains(text, "json-only") {
		t.Fatal(text)
	}
	raw := CapabilityHelpJSON(info)
	var env map[string]any
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	if env["ok"] != true {
		t.Fatal(env)
	}
	data := env["data"].(map[string]any)
	if data["kind"] != helpfmt.KindCapabilityHelp {
		t.Fatal(data["kind"])
	}
}

func TestDomainHelpWiring(t *testing.T) {
	verbs := []helpfmt.DomainVerb{{Verb: "create", Name: "create-invoice", Description: "Create"}}
	if !strings.Contains(DomainHelpHuman("invoices", verbs), "create-invoice") {
		t.Fatal()
	}
	raw := DomainHelpJSON("invoices", verbs)
	var env map[string]any
	if err := json.Unmarshal(raw, &env); err != nil {
		t.Fatal(err)
	}
	if env["ok"] != true {
		t.Fatal(env)
	}
}
