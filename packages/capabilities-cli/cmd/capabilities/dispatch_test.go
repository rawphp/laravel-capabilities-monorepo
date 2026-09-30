package main

import (
	"bytes"
	"encoding/json"
	"errors"
	"net/http"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
	"github.com/rawphp/capabilities-cli/internal/helpfmt"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

func fakeIndex(entries ...synth.Entry) *synth.Index {
	return synth.Build(entries)
}

func captureDispatch(t *testing.T, args []string, idx *synth.Index, summaries []catalog.CapabilitySummary, schemaFor SchemaLookup) (int, string, string) {
	t.Helper()
	var out, errb bytes.Buffer
	exit := Execute(Env{
		Args:       args,
		Stdout:     &out,
		Stderr:     &errb,
		ConfigRoot: t.TempDir(),
		Index:      idx,
		Summaries:  summaries,
		SchemaFor:  schemaFor,
	})
	return exit, out.String(), errb.String()
}

func TestRootHelpListsReservedAndDiscoveryPointer(t *testing.T) {
	h := RootHelp()
	for _, cmd := range []string{"auth", "catalog", "describe", "run", "approvals", "version", "self-update", "help"} {
		if !strings.Contains(h, cmd) {
			t.Fatalf("root help missing reserved %q:\n%s", cmd, h)
		}
	}
	if strings.Contains(h, "MCP stdio") {
		t.Fatalf("root help must not list MCP stdio:\n%s", h)
	}
	if !strings.Contains(h, "<domain>") && !strings.Contains(strings.ToLower(h), "domain") {
		t.Fatalf("expected domain/verb discovery pointer:\n%s", h)
	}
	if strings.Contains(h, `"properties"`) || strings.Contains(h, "input_schema") {
		t.Fatalf("root help must not print full schemas:\n%s", h)
	}
}

func TestReservedMetaWinsOverDomainToken(t *testing.T) {
	// Meta `catalog` always wins even when a synth index is present.
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, _ := captureDispatch(t, []string{"catalog", "--help"}, idx, nil, nil)
	if code != api.ExitOK {
		t.Fatalf("exit=%d", code)
	}
	if !strings.Contains(out, "capabilities catalog") {
		t.Fatalf("expected meta catalog help, got:\n%s", out)
	}
}

func TestUnknownDomainExit5NotFoundEnvelope(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, errb := captureDispatch(t, []string{"unknown-domain"}, idx, nil, nil)
	if code != api.ExitDomain {
		t.Fatalf("exit=%d want %d stdout=%s stderr=%s", code, api.ExitDomain, out, errb)
	}
	var env api.ErrorEnvelope
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatalf("stdout not envelope: %v %q", err, out)
	}
	if env.OK || env.Error == nil || env.Error.Code != api.CodeNotFound {
		t.Fatalf("envelope=%+v", env)
	}
	combined := out + errb
	if !strings.Contains(combined, "catalog") && !strings.Contains(combined, "run") {
		t.Fatalf("expected catalog/run hint: stderr=%q out=%q", errb, out)
	}
}

func TestUnknownVerbExit5NotFoundEnvelope(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, _ := captureDispatch(t, []string{"invoices", "delete"}, idx, nil, nil)
	if code != api.ExitDomain {
		t.Fatalf("exit=%d want %d out=%s", code, api.ExitDomain, out)
	}
	var env api.ErrorEnvelope
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatalf("stdout not envelope: %v %q", err, out)
	}
	if env.Error == nil || env.Error.Code != api.CodeNotFound {
		t.Fatalf("%+v", env)
	}
}

func TestDomainHelpListsVerbs(t *testing.T) {
	idx := fakeIndex(
		synth.Entry{Name: "create-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "create"}},
		synth.Entry{Name: "void-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "void"}},
	)
	summaries := []catalog.CapabilitySummary{
		{Name: "create-invoice", Description: "Create an invoice"},
		{Name: "void-invoice", Description: "Void an invoice"},
	}
	code, out, _ := captureDispatch(t, []string{"invoices", "--help"}, idx, summaries, nil)
	if code != api.ExitOK {
		t.Fatalf("exit=%d out=%s", code, out)
	}
	if !strings.Contains(out, "create") || !strings.Contains(out, "create-invoice") {
		t.Fatalf("domain help missing create:\n%s", out)
	}
	if !strings.Contains(out, "void") {
		t.Fatalf("domain help missing void:\n%s", out)
	}
}

func TestDomainHelpJSONEnvelope(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, _ := captureDispatch(t, []string{"invoices", "--help", "--json"}, idx, nil, nil)
	if code != api.ExitOK {
		t.Fatalf("exit=%d out=%s", code, out)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatal(err, out)
	}
	if env["ok"] != true {
		t.Fatal(env)
	}
	data := env["data"].(map[string]any)
	if data["kind"] != helpfmt.KindDomainHelp {
		t.Fatalf("kind=%v", data["kind"])
	}
}

func TestCapabilityHelpRoutesToHelpfmt(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	schemaFor := SchemaLookup(func(name string) (desc, version string, in, out map[string]any) {
		if name != "create-invoice" {
			return "", "", nil, nil
		}
		return "Create an invoice", "1", map[string]any{
			"type":     "object",
			"required": []any{"customer_id"},
			"properties": map[string]any{
				"customer_id": map[string]any{"type": "integer"},
				"line_items":  map[string]any{"type": "array"},
			},
		}, map[string]any{"type": "object"}
	})
	code, out, _ := captureDispatch(t, []string{"invoices", "create", "--help"}, idx, nil, schemaFor)
	if code != api.ExitOK {
		t.Fatalf("exit=%d out=%s", code, out)
	}
	if !strings.Contains(out, "INPUT") || !strings.Contains(out, "--customer-id") || !strings.Contains(out, "json-only") {
		t.Fatalf("expected helpfmt capability help:\n%s", out)
	}
}

func TestCapabilityHelpJSONMachineEnvelope(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	schemaFor := SchemaLookup(func(name string) (desc, version string, in, out map[string]any) {
		return "Create", "1", map[string]any{
			"type": "object",
			"properties": map[string]any{
				"customer_id": map[string]any{"type": "integer"},
			},
		}, nil
	})
	code, out, _ := captureDispatch(t, []string{"invoices", "create", "--help", "--json"}, idx, nil, schemaFor)
	if code != api.ExitOK {
		t.Fatalf("exit=%d out=%s", code, out)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatal(err)
	}
	data := env["data"].(map[string]any)
	if data["kind"] != helpfmt.KindCapabilityHelp {
		t.Fatalf("kind=%v", data["kind"])
	}
	if data["domain"] != "invoices" || data["verb"] != "create" {
		t.Fatalf("domain/verb: %v %v", data["domain"], data["verb"])
	}
}

func TestDomainOnlyWithoutHelpShowsDomainHelp(t *testing.T) {
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, _ := captureDispatch(t, []string{"invoices"}, idx, nil, nil)
	if code != api.ExitOK {
		t.Fatalf("exit=%d out=%s", code, out)
	}
	if !strings.Contains(out, "create-invoice") && !strings.Contains(out, "create") {
		t.Fatalf("expected domain help listing verbs:\n%s", out)
	}
}

func TestCapabilityInvokeDoesNotNotFound(t *testing.T) {
	// Known domain+verb must not return not_found (ORI-175 wires full invoke; may auth-fail here).
	idx := fakeIndex(synth.Entry{
		Name: "create-invoice",
		CLI:  &synth.CLI{Domain: "invoices", Verb: "create"},
	})
	code, out, errb := captureDispatch(t, []string{"invoices", "create", "--input={}"}, idx, nil, nil)
	if code == api.ExitDomain {
		var env api.ErrorEnvelope
		if err := json.Unmarshal([]byte(out), &env); err == nil && env.Error != nil && env.Error.Code == api.CodeNotFound {
			t.Fatalf("should not treat known domain+verb as not_found: %d %s %s", code, out, errb)
		}
	}
}

func TestBareArgsPrintsHelpExit0(t *testing.T) {
	code, _, errb := CaptureExecute(nil, t.TempDir(), nil)
	if code != api.ExitOK {
		t.Fatalf("exit %d", code)
	}
	if !strings.Contains(errb, "USAGE:") {
		t.Fatal(errb)
	}
}

func TestProfileFlagBeforeCommand(t *testing.T) {
	// Leading --profile must not be treated as an unknown domain (peel to trailing flags).
	got := peelLeadingGlobalFlags([]string{"--profile=other", "catalog"})
	if len(got) < 1 || got[0] != "catalog" {
		t.Fatalf("%v", got)
	}
	if !strings.Contains(strings.Join(got, " "), "--profile=other") {
		t.Fatal(got)
	}
}

func TestPeelLeadingGlobalFlagsBaseURL(t *testing.T) {
	got := peelLeadingGlobalFlags([]string{"--profile=p", "--base-url=https://x", "catalog", "--json"})
	if len(got) < 2 || got[0] != "catalog" {
		t.Fatalf("%v", got)
	}
	joined := strings.Join(got, " ")
	if !strings.Contains(joined, "--profile=p") || !strings.Contains(joined, "--base-url=https://x") {
		t.Fatal(joined)
	}
}

func TestPeelLeadingGlobalFlagsSpaceSeparatedValues(t *testing.T) {
	got := peelLeadingGlobalFlags([]string{"--profile", "work", "--base-url", "https://x", "--json", "catalog", "extra"})
	want := []string{"catalog", "extra", "--json", "--profile=work", "--base-url=https://x"}
	if strings.Join(got, " ") != strings.Join(want, " ") {
		t.Fatalf("got %v want %v", got, want)
	}
}

func TestUnknownDomainSuggestsClosestDomain(t *testing.T) {
	idx := fakeIndex(synth.Entry{Name: "create-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "create"}})
	code, _, errb := captureDispatch(t, []string{"invoics"}, idx, nil, nil)
	if code != api.ExitDomain || !strings.Contains(errb, "did you mean: invoices") {
		t.Fatalf("exit %d stderr %q", code, errb)
	}
}

func TestUnknownVerbHelpIsNotFound(t *testing.T) {
	idx := fakeIndex(synth.Entry{Name: "create-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "create"}})
	code, out, errb := captureDispatch(t, []string{"invoices", "delete", "--help"}, idx, nil, nil)
	if code != api.ExitDomain || !strings.Contains(errb, "try: capabilities invoices --help") {
		t.Fatalf("exit %d out %q stderr %q", code, out, errb)
	}
}

func TestDomainWithOnlyFlagsShowsDomainHelp(t *testing.T) {
	idx := fakeIndex(synth.Entry{Name: "create-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "create"}})
	code, out, _ := captureDispatch(t, []string{"invoices", "--verbose"}, idx, nil, nil)
	if code != api.ExitOK || !strings.Contains(out, "invoices — domain capabilities") {
		t.Fatalf("exit %d out %q", code, out)
	}
}

func TestSuggestionEdges(t *testing.T) {
	if got := suggestReservedOrDomain("  ", nil); got != "" {
		t.Fatalf("blank token: %q", got)
	}
	if got := suggestReservedOrDomain("catalg", nil); got != "catalog" {
		t.Fatalf("reserved typo: %q", got)
	}
	cases := []struct {
		a, b string
		want int
	}{{"", "abc", 3}, {"abc", "", 3}, {"same", "same", 0}, {"kitten", "sitting", 3}}
	for _, c := range cases {
		if got := levenshtein(c.a, c.b); got != c.want {
			t.Fatalf("levenshtein(%q,%q)=%d want %d", c.a, c.b, got, c.want)
		}
	}
	if got := commonPrefixLen("invoices", "inv"); got != 3 {
		t.Fatalf("commonPrefixLen %d", got)
	}
}

func TestRawToMapTreatsMissingOrInvalidSchemaAsEmpty(t *testing.T) {
	for _, raw := range []string{"", "{", "[1]"} {
		if got := rawToMap(json.RawMessage(raw)); len(got) != 0 {
			t.Fatalf("%q: %v", raw, got)
		}
	}
	if got := rawToMap(json.RawMessage(`{"type":"object"}`)); got["type"] != "object" {
		t.Fatalf("%v", got)
	}
}

// With a pre-built index but no inline schema, capability help fetches the
// schema via describe, so it needs the same auth and reports describe failures.
func TestCapabilityHelpFetchesSchemaUnderAuth(t *testing.T) {
	idx := fakeIndex(synth.Entry{Name: "create-invoice", CLI: &synth.CLI{Domain: "invoices", Verb: "create"}})
	help := func(root string, newClient func(string, string) *api.Client) (int, string, string) {
		var out, errb bytes.Buffer
		code := Execute(Env{Args: []string{"invoices", "create", "--help"}, Stdout: &out, Stderr: &errb, ConfigRoot: root, Index: idx, NewClient: newClient})
		return code, out.String(), errb.String()
	}

	if code, _, errb := help(t.TempDir(), nil); code != api.ExitAuth || !strings.Contains(errb, "not authenticated") {
		t.Fatalf("no login: exit %d stderr %q", code, errb)
	}

	root := t.TempDir()
	_ = auth.NewStore(root).SetToken("default", "tok")
	if code, _, errb := help(root, nil); code != api.ExitAuth || !strings.Contains(errb, "missing base URL") {
		t.Fatalf("no base URL: exit %d stderr %q", code, errb)
	}

	srv, root := errorServer(t, http.StatusNotFound, `{"ok":false,"error":{"code":"not_found","message":"gone"}}`)
	code, out, _ := help(root, newClientFactory(srv))
	if code != api.ExitDomain || stdoutError(t, out)["code"] != api.CodeNotFound {
		t.Fatalf("describe error: exit %d out %q", code, out)
	}

	down := func(base, token string) *api.Client {
		c := api.NewClient(base, token)
		c.HTTP = &http.Client{Transport: roundTripFunc(func(*http.Request) (*http.Response, error) {
			return nil, errors.New("dial: connection refused")
		})}
		return c
	}
	if code, _, errb := help(root, down); code != api.ExitInternal || !strings.Contains(errb, "connection refused") {
		t.Fatalf("transport: exit %d stderr %q", code, errb)
	}
}
