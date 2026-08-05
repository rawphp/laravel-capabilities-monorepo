package main

import (
	"encoding/json"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func TestAuthStatusJSON(t *testing.T) {
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", "https://app.example.com", "tok")
	code, out, errb := CaptureExecute([]string{"auth", "status", "--json"}, root, nil)
	if code != api.ExitOK {
		t.Fatal(code, out, errb)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatal(err, out)
	}
	if env["ok"] != true {
		t.Fatal(out)
	}
	data := env["data"].(map[string]any)
	if data["logged_in"] != true || data["base_url"] != "https://app.example.com" {
		t.Fatal(data)
	}
	if strings.Contains(out, "tok") {
		t.Fatal("token leaked")
	}
}

func TestRunHelpDocumentsHuman(t *testing.T) {
	h := CommandHelp("run")
	if !strings.Contains(h, "--human") {
		t.Fatal(h)
	}
}

func TestCatalogHelpDocumentsIncludeSchemas(t *testing.T) {
	h := CommandHelp("catalog")
	if !strings.Contains(h, "--include-schemas") {
		t.Fatal(h)
	}
}
