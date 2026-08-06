package main

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

func TestLeadingJSONBeforeCommand(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	// git-style: capabilities --json catalog
	code, out, errb := CaptureExecute([]string{"--json", "catalog"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb, out)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatalf("expected JSON catalog envelope: %v %s", err, out)
	}
	if env["ok"] != true {
		t.Fatal(out)
	}
}

func TestPeelLeadingJSONAndHuman(t *testing.T) {
	got := peelLeadingGlobalFlags([]string{"--json", "--human", "catalog", "--flat"})
	if got[0] != "catalog" {
		t.Fatalf("%v", got)
	}
	joined := strings.Join(got, " ")
	if !strings.Contains(joined, "--json") || !strings.Contains(joined, "--human") {
		t.Fatal(joined)
	}
}

func TestAuthListProfiles(t *testing.T) {
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", "https://a.example.com", "t1")
	_, _ = auth.LoginWithToken(st, "other", "https://b.example.com", "t2")
	code, out, errb := CaptureExecute([]string{"auth", "list"}, root, nil)
	if code != api.ExitOK {
		t.Fatal(code, errb)
	}
	if !strings.Contains(out, "default") || !strings.Contains(out, "other") {
		t.Fatal(out)
	}
	if strings.Contains(out, "t1") || strings.Contains(out, "t2") {
		t.Fatal("token leaked")
	}
	code, out, _ = CaptureExecute([]string{"auth", "list", "--json"}, root, nil)
	if code != 0 {
		t.Fatal(code)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatal(err, out)
	}
	data := env["data"].(map[string]any)
	rows := data["profiles"].([]any)
	if len(rows) != 2 {
		t.Fatal(rows)
	}
}

func TestSuggestTypoCatalg(t *testing.T) {
	if suggestReservedOrDomain("catalg", nil) != "catalog" {
		t.Fatal(suggestReservedOrDomain("catalg", nil))
	}
	if suggestReservedOrDomain("zzzz", nil) != "" {
		t.Fatal("no false positives for distant tokens")
	}
}

func TestSuggestPrefersDomainPrefix(t *testing.T) {
	// mele is distance 2 from both "meal" and "help"; common prefix favors meal.
	idx := &synth.Index{Domains: map[string]map[string]string{"meal": {"today": "get_today_meals"}}}
	if suggestReservedOrDomain("mele", idx) != "meal" {
		t.Fatalf("got %q", suggestReservedOrDomain("mele", idx))
	}
}

func TestUnauthDomainIsAuthExit(t *testing.T) {
	code, _, errb := CaptureExecute([]string{"meal", "today"}, t.TempDir(), nil)
	if code != api.ExitAuth {
		t.Fatalf("exit=%d want auth; %s", code, errb)
	}
	if !strings.Contains(errb, "not authenticated") {
		t.Fatal(errb)
	}
}
