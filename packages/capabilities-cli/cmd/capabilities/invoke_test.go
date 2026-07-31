package main

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func invoiceSchemaJSON() string {
	b, _ := json.Marshal(map[string]any{
		"type":     "object",
		"required": []any{"customer_id", "currency"},
		"properties": map[string]any{
			"customer_id":  map[string]any{"type": "integer"},
			"amount_cents": map[string]any{"type": "integer"},
			"currency":     map[string]any{"type": "string"},
			"meta":         map[string]any{"type": "object"},
		},
	})
	return string(b)
}

func TestRunFlagsMergeAndHumanKeepsEnvelope(t *testing.T) {
	var gotBody []byte
	schema := invoiceSchemaJSON()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodPost && strings.HasPrefix(r.URL.Path, "/capabilities/"):
			gotBody, _ = io.ReadAll(r.Body)
			_, _ = w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
		case r.Method == http.MethodGet && strings.HasPrefix(r.URL.Path, "/capabilities/") && r.URL.Path != "/capabilities":
			// describe
			_, _ = w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":` + schema + `,"output_schema":{},"surfaces":["cli"]}}`))
		case r.Method == http.MethodGet && (r.URL.Path == "/capabilities" || strings.HasSuffix(r.URL.Path, "/capabilities")):
			_, _ = w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice","surfaces":["cli"]}]}}`))
		default:
			_, _ = w.Write([]byte(`{"ok":true,"data":{}}`))
		}
	}))
	t.Cleanup(srv.Close)

	root := t.TempDir()
	factory := newClientFactory(srv)
	if code, _, errb := CaptureExecute([]string{"auth", "login", "--base-url", srv.URL, "--token", "tok"}, root, factory); code != 0 {
		t.Fatalf("login %d %s", code, errb)
	}

	code, stdout, stderr := CaptureExecute([]string{
		"run", "create-invoice",
		"--customer-id=42", "--currency=USD", "--amount-cents=100",
		"--human",
	}, root, factory)
	if code != 0 {
		t.Fatalf("exit=%d stderr=%s stdout=%s", code, stderr, stdout)
	}
	if !strings.Contains(stdout, `"ok"`) {
		t.Fatalf("stdout must remain envelope: %s", stdout)
	}
	if strings.TrimSpace(stderr) == "" {
		t.Fatalf("expected --human summary on stderr")
	}
	var body map[string]any
	if err := json.Unmarshal(gotBody, &body); err != nil {
		t.Fatalf("body %q: %v", gotBody, err)
	}
	if body["customer_id"] != float64(42) || body["currency"] != "USD" {
		t.Fatalf("merged body=%v", body)
	}
}

func TestRunMissingRequiredExit2(t *testing.T) {
	schema := invoiceSchemaJSON()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		if r.Method == http.MethodPost {
			t.Fatal("must not POST when local validation fails")
		}
		if strings.HasPrefix(r.URL.Path, "/capabilities/") && r.URL.Path != "/capabilities" {
			_, _ = w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":` + schema + `}}`))
			return
		}
		_, _ = w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	factory := newClientFactory(srv)
	if code, _, _ := CaptureExecute([]string{"auth", "login", "--base-url", srv.URL, "--token", "tok"}, root, factory); code != 0 {
		t.Fatal("login")
	}
	code, _, stderr := CaptureExecute([]string{"run", "create-invoice"}, root, factory)
	if code != api.ExitValidation {
		t.Fatalf("want 2 got %d stderr=%s", code, stderr)
	}
}

func TestRunUnknownFlagExit2(t *testing.T) {
	schema := invoiceSchemaJSON()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		if strings.HasPrefix(r.URL.Path, "/capabilities/") && r.URL.Path != "/capabilities" {
			_, _ = w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":` + schema + `}}`))
			return
		}
		_, _ = w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	factory := newClientFactory(srv)
	if code, _, _ := CaptureExecute([]string{"auth", "login", "--base-url", srv.URL, "--token", "tok"}, root, factory); code != 0 {
		t.Fatal("login")
	}
	code, _, stderr := CaptureExecute([]string{"run", "create-invoice", "--not-a-field=1"}, root, factory)
	if code != api.ExitValidation {
		t.Fatalf("want 2 got %d stderr=%s", code, stderr)
	}
	if !strings.Contains(stderr, "unknown flag") {
		t.Fatalf("stderr=%s", stderr)
	}
}
