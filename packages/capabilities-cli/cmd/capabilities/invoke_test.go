package main

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
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

func TestRunRetryLastWithoutInputResendsPriorBody(t *testing.T) {
	var gotBody []byte
	var gotKey string
	schema := invoiceSchemaJSON()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case r.Method == http.MethodPost && strings.HasPrefix(r.URL.Path, "/capabilities/"):
			gotBody, _ = io.ReadAll(r.Body)
			gotKey = r.Header.Get("Idempotency-Key")
			_, _ = w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
		case r.Method == http.MethodGet && strings.HasPrefix(r.URL.Path, "/capabilities/"):
			_, _ = w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":` + schema + `,"output_schema":{},"surfaces":["cli"]}}`))
		default:
			_, _ = w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice","surfaces":["cli"]}]}}`))
		}
	}))
	t.Cleanup(srv.Close)

	root := t.TempDir()
	factory := newClientFactory(srv)
	if code, _, errb := CaptureExecute([]string{"auth", "login", "--base-url", srv.URL, "--token", "tok"}, root, factory); code != 0 {
		t.Fatalf("login %d %s", code, errb)
	}
	if code, _, errb := CaptureExecute([]string{"run", "create-invoice", "--customer-id=42", "--currency=USD", "--idempotency-key=k1"}, root, factory); code != 0 {
		t.Fatalf("first run %d %s", code, errb)
	}
	first := string(gotBody)

	code, _, errb := CaptureExecute([]string{"run", "create-invoice", "--retry-last"}, root, factory)
	if code != 0 {
		t.Fatalf("retry %d %s", code, errb)
	}
	if gotKey != "k1" || string(gotBody) != first {
		t.Fatalf("retry-last must resend prior invoke: key=%s body=%s want=%s", gotKey, gotBody, first)
	}
}

// --tenant was removed: the server never read the hint, and DTO schemas
// (additionalProperties:false) rejected the injected body key. It is now an
// unknown flag like any other — exit 2 before any POST (D-003: scope is server-derived).
func TestFlagtenantremoved(t *testing.T) {
	posts := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			posts++
		}
		if r.Method == http.MethodGet && strings.HasPrefix(r.URL.Path, "/capabilities/") {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object","required":["customer_id"],"properties":{"customer_id":{"type":"integer"}}}}}`))
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", srv.URL, "tok")
	code, _, errb := CaptureExecute([]string{
		"run", "create-invoice",
		"--input={\"customer_id\":1}",
		"--tenant=acme",
		"--no-cache",
	}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatalf("want exit 2 got %d stderr=%s", code, errb)
	}
	if !strings.Contains(errb, "unknown flag") {
		t.Fatalf("want unknown flag, got %s", errb)
	}
	if posts != 0 {
		t.Fatalf("must not POST, got %d", posts)
	}
	if strings.Contains(CommandHelp("run"), "--tenant") {
		t.Fatal("run help must not document --tenant")
	}
}

func TestRunInputProblemsExitValidationWithoutPost(t *testing.T) {
	posts := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			posts++
		}
		w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":` + invoiceSchemaJSON() + `}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", srv.URL, "tok")
	cases := map[string][]string{
		"unreadable input file": {"run", "create-invoice", "--input-file=" + root + "/missing.json"},
		"empty flag name":       {"run", "create-invoice", "--=5"},
		"stray positional":      {"run", "create-invoice", "--customer-id=1", "stray"},
	}
	for name, args := range cases {
		code, _, errb := CaptureExecute(args, root, newClientFactory(srv))
		if code != api.ExitValidation || errb == "" {
			t.Fatalf("%s: exit %d stderr %q", name, code, errb)
		}
	}
	if posts != 0 {
		t.Fatalf("posted %d times", posts)
	}
}

func TestMergeInputRejectsUnreadableSchema(t *testing.T) {
	if _, _, msg := mergeInput("x", []byte(`{`), nil, nil); !strings.Contains(msg, "invalid input schema") {
		t.Fatalf("msg %q", msg)
	}
}

// Describe failing is not fatal: the server stays the validator (D-004).
func TestRunInvokesWhenDescribeFails(t *testing.T) {
	var posted []byte
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			posted, _ = io.ReadAll(r.Body)
			w.Write([]byte(`{"ok":true,"data":{"invoice_id":7}}`))
			return
		}
		w.WriteHeader(http.StatusServiceUnavailable)
		w.Write([]byte(`{"ok":false,"error":{"code":"internal","message":"describe down"}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", srv.URL, "tok")
	code, out, errb := CaptureExecute([]string{"run", "create-invoice", `--input={"customer_id":1}`}, root, newClientFactory(srv))
	if code != api.ExitOK || !strings.Contains(out, `"invoice_id":7`) || string(posted) != `{"customer_id":1}` {
		t.Fatalf("exit %d out %q err %q posted %s", code, out, errb, posted)
	}
}
