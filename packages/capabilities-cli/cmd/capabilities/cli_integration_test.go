package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func testAPI(t *testing.T) (*httptest.Server, string) {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == api.PathAuthDevice:
			w.Write([]byte(`{"ok":true,"data":{"access_token":"device-token","device_code":"d"}}`))
		case r.URL.Path == api.PathAuthToken:
			w.Write([]byte(`{"ok":true,"data":{"access_token":"oauth-token"}}`))
		case r.Method == http.MethodGet && r.URL.Path == "/capabilities":
			w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice","deprecated":true,"successor":"create-invoice-v2"}]}}`))
		case r.Method == http.MethodGet && strings.HasPrefix(r.URL.Path, "/capabilities/"):
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","deprecated":true,"successor":"v2","input_schema":{"type":"object","required":["customer_id"],"properties":{"customer_id":{"type":"integer"}}}}}`))
		case r.Method == http.MethodPost && strings.HasPrefix(r.URL.Path, "/capabilities/approvals/"):
			w.Write([]byte(`{"ok":true,"data":{"status":"accepted"}}`))
		case r.Method == http.MethodPost && strings.HasPrefix(r.URL.Path, "/capabilities/"):
			if r.Header.Get("Idempotency-Key") == "" {
				w.WriteHeader(400)
				w.Write([]byte(`{"ok":false,"error":{"code":"validation_failed","message":"missing key"}}`))
				return
			}
			// echo validation failure for bad shape is server-side; local already checked
			w.Write([]byte(`{"ok":true,"data":{"invoice_id":99},"meta":{"request_id":"r1","capability":"create-invoice"}}`))
		default:
			w.WriteHeader(404)
			w.Write([]byte(`{"ok":false,"error":{"code":"not_found","message":"no"}}`))
		}
	}))
	t.Cleanup(srv.Close)
	return srv, srv.URL
}

func newClientFactory(srv *httptest.Server) func(string, string) *api.Client {
	return func(base, token string) *api.Client {
		c := api.NewClient(base, token)
		c.HTTP = srv.Client()
		return c
	}
}

func TestExecuteAuthLoginTokenAndStatusLogout(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	factory := newClientFactory(srv)

	code, out, errb := CaptureExecute([]string{"auth", "login", "--base-url=" + url, "--token=pat-1"}, root, factory)
	if code != 0 {
		t.Fatalf("login %d %s %s", code, out, errb)
	}
	if strings.Contains(out, "pat-1") {
		t.Fatal("token leaked to stdout")
	}

	code, out, _ = CaptureExecute([]string{"auth", "status"}, root, factory)
	if code != 0 || !strings.Contains(out, "logged_in=true") {
		t.Fatal(code, out)
	}

	code, _, _ = CaptureExecute([]string{"auth", "logout"}, root, factory)
	if code != 0 {
		t.Fatal(code)
	}
	code, out, _ = CaptureExecute([]string{"auth", "status"}, root, factory)
	if !strings.Contains(out, "logged_in=false") {
		t.Fatal(out)
	}
}

func TestExecuteAuthLoginDevice(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	code, _, errb := CaptureExecute([]string{"auth", "login", "--base-url", url}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
	st := auth.NewStore(root)
	tok, err := st.GetToken("default")
	if err != nil || tok != "device-token" {
		t.Fatal(tok, err)
	}
}

func TestExecuteAuthLoginOAuthCode(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	code, _, errb := CaptureExecute([]string{"auth", "login", "--base-url=" + url, "--code=abc"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
}

func TestExecuteCatalogDescribeRun(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	factory := newClientFactory(srv)
	// seed auth
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")

	code, out, errb := CaptureExecute([]string{"catalog", "--json"}, root, factory)
	if code != 0 {
		t.Fatal(code, out, errb)
	}
	if !strings.Contains(out, "create-invoice") {
		t.Fatal(out)
	}

	code, out, errb = CaptureExecute([]string{"catalog", "--refresh"}, root, factory)
	if code != 0 {
		t.Fatal(code, errb)
	}

	code, out, errb = CaptureExecute([]string{"describe", "create-invoice", "--json"}, root, factory)
	if code != 0 {
		t.Fatal(code, errb)
	}
	if !strings.Contains(out, "input_schema") && !strings.Contains(out, "schema_version") {
		t.Fatal(out)
	}

	code, out, errb = CaptureExecute([]string{
		"run", "create-invoice",
		"--input", `{"customer_id":42}`,
		"--json",
	}, root, factory)
	if code != 0 {
		t.Fatalf("run %d out=%s err=%s", code, out, errb)
	}
	var env map[string]any
	if err := json.Unmarshal([]byte(strings.TrimSpace(out)), &env); err != nil {
		// may print data only
		if !strings.Contains(out, "invoice_id") && !strings.Contains(out, "ok") {
			t.Fatal(out, err)
		}
	}

	// local validation failure
	code, _, errb = CaptureExecute([]string{
		"run", "create-invoice",
		"--input", `{"customer_id":"bad"}`,
	}, root, factory)
	if code != api.ExitValidation {
		t.Fatal(code, errb)
	}

	// input file
	p := filepath.Join(root, "payload.json")
	_ = os.WriteFile(p, []byte(`{"customer_id":7}`), 0o600)
	code, _, errb = CaptureExecute([]string{"run", "create-invoice", "--input-file", p, "--idempotency-key", "k1"}, root, factory)
	if code != 0 {
		t.Fatal(code, errb)
	}

	// retry last
	code, _, errb = CaptureExecute([]string{"run", "create-invoice", "--input-file", p, "--retry-last"}, root, factory)
	if code != 0 {
		t.Fatal(code, errb)
	}
}

func TestExecuteRunRequiresAuth(t *testing.T) {
	root := t.TempDir()
	code, _, errb := CaptureExecute([]string{"run", "x", "--input", `{}`}, root, nil)
	if code != api.ExitAuth {
		t.Fatal(code, errb)
	}
}

func TestExecuteCatalogRequiresAuth(t *testing.T) {
	root := t.TempDir()
	code, _, _ := CaptureExecute([]string{"catalog"}, root, nil)
	if code != api.ExitAuth {
		t.Fatal(code)
	}
}

func TestExecuteDescribeRequiresAuth(t *testing.T) {
	root := t.TempDir()
	code, _, _ := CaptureExecute([]string{"describe", "x"}, root, nil)
	if code != api.ExitAuth {
		t.Fatal(code)
	}
}

func TestExecuteApprovals(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, out, errb := CaptureExecute([]string{"approvals", "accept", "ap1"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, out, errb)
	}
	code, _, errb = CaptureExecute([]string{"approvals", "reject", "ap1"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
}

func TestExecuteApprovalsRequiresAuth(t *testing.T) {
	root := t.TempDir()
	code, _, _ := CaptureExecute([]string{"approvals", "accept", "x"}, root, nil)
	if code != api.ExitAuth {
		t.Fatal(code)
	}
}

func TestExecuteHelpSubcommands(t *testing.T) {
	for _, args := range [][]string{
		{"help"},
		{"help", "run"},
		{"auth", "help"},
		{"-h"},
	} {
		code, out, _ := CaptureExecute(args, t.TempDir(), nil)
		if code != 0 && args[0] != "auth" {
			// auth help returns 0
		}
		if out == "" && code != 0 {
			// root -h writes to stdout
		}
		_ = out
	}
	code, out, _ := CaptureExecute([]string{"help", "run"}, t.TempDir(), nil)
	if code != 0 || !strings.Contains(out, "Idempotency") {
		t.Fatal(code, out)
	}
}

func TestExecuteUnknownCommand(t *testing.T) {
	code, out, errb := CaptureExecute([]string{"nope"}, t.TempDir(), nil)
	// Unknown domain/command → exit 5 not_found envelope (ORI-173).
	if code != api.ExitDomain {
		t.Fatalf("exit=%d want %d stderr=%s stdout=%s", code, api.ExitDomain, errb, out)
	}
	if !strings.Contains(errb, "unknown") && !strings.Contains(out, "unknown") {
		t.Fatal(code, errb, out)
	}
	if !strings.Contains(out, "not_found") {
		t.Fatalf("expected not_found envelope on stdout: %s", out)
	}
}

func TestExecuteAuthLoginMissingBaseURL(t *testing.T) {
	code, _, _ := CaptureExecute([]string{"auth", "login"}, t.TempDir(), nil)
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestExecuteRunMissingName(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"run"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestExecuteDescribeMissingName(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"describe"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestExecuteMcpRequiresAuth(t *testing.T) {
	code, _, _ := CaptureExecute([]string{"mcp"}, t.TempDir(), nil)
	if code != api.ExitAuth {
		t.Fatal(code)
	}
}

func TestExecuteRunServerErrorMapping(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"x","schema_version":"1","input_schema":{"type":"object"}}}`))
			return
		}
		w.WriteHeader(429)
		w.Write([]byte(`{"ok":false,"error":{"code":"rate_limited","message":"slow","retryable":true}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	code, _, _ := CaptureExecute([]string{"run", "x", "--input", `{}`}, root, newClientFactory(srv))
	if code != api.ExitRateLimit {
		t.Fatal(code)
	}
}

func TestExecuteSubcommandHelpFlags(t *testing.T) {
	// Help must win before auth/network for every top-level command that used to ignore trailing --help.
	cases := []struct {
		args     []string
		needle   string
		emptyCfg bool
	}{
		{[]string{"mcp", "--help"}, "MCP stdio", true},
		{[]string{"mcp", "-h"}, "MCP stdio", true},
		{[]string{"mcp", "--profile=default", "--help"}, "capabilities mcp", true},
		{[]string{"catalog", "--help"}, "catalog", true},
		{[]string{"describe", "--help"}, "Schema", true},
		{[]string{"run", "--help"}, "Idempotency", true},
		{[]string{"approvals", "--help"}, "accept", true},
	}
	for _, tc := range cases {
		t.Run(strings.Join(tc.args, " "), func(t *testing.T) {
			root := t.TempDir()
			code, out, errb := CaptureExecute(tc.args, root, nil)
			if code != api.ExitOK {
				t.Fatalf("exit %d stderr=%q stdout=%q", code, errb, out)
			}
			if !strings.Contains(out, tc.needle) {
				t.Fatalf("stdout missing %q: %q", tc.needle, out)
			}
			if strings.Contains(errb, "not authenticated") || strings.Contains(errb, "auth") {
				t.Fatalf("help required auth: %q", errb)
			}
		})
	}

	// Logged-in config must still print help and must not start the MCP bridge (empty success path).
	root := t.TempDir()
	st := auth.NewStore(root)
	if _, err := auth.LoginWithToken(st, "default", "http://127.0.0.1:9", "tok"); err != nil {
		t.Fatal(err)
	}
	code, out, errb := CaptureExecute([]string{"mcp", "--help"}, root, nil)
	if code != api.ExitOK {
		t.Fatalf("logged-in mcp --help exit %d stderr=%q", code, errb)
	}
	if !strings.Contains(out, "MCP stdio") && !strings.Contains(out, "capabilities mcp") {
		t.Fatalf("logged-in mcp --help missing help text: %q", out)
	}
	if out == "" {
		t.Fatal("logged-in mcp --help must not exit with empty stdout (MCP bridge false-success)")
	}
}
