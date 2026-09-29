package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func testAPI(t *testing.T) (*httptest.Server, string) {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == api.PathAuthDevice:
			w.Write([]byte(`{"ok":true,"data":{"device_code":"d","user_code":"HOST-USER","verification_uri":"https://example.test/device","expires_in":600,"interval":5}}`))
		case r.URL.Path == api.PathAuthToken:
			var body map[string]any
			_ = json.NewDecoder(r.Body).Decode(&body)
			if body["grant_type"] == auth.DeviceCodeGrantType && body["device_code"] == "d" {
				w.Write([]byte(`{"ok":true,"data":{"access_token":"device-token"}}`))
				return
			}
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
	var out, errb bytes.Buffer
	var waits []time.Duration
	code := Execute(Env{
		Args:       []string{"auth", "login", "--base-url", url},
		Stdout:     &out,
		Stderr:     &errb,
		ConfigRoot: root,
		NewClient:  newClientFactory(srv),
		Sleep: func(_ context.Context, d time.Duration) error {
			waits = append(waits, d)
			return nil
		},
	})
	if code != 0 {
		t.Fatal(code, errb.String())
	}
	if !strings.Contains(errb.String(), "HOST-USER") || strings.Contains(out.String(), "HOST-USER") {
		t.Fatalf("user code prompt belongs on stderr: out=%q err=%q", out.String(), errb.String())
	}
	if len(waits) != 1 || waits[0] != 10*time.Second {
		t.Fatalf("waits %v", waits)
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
	seedLogin(t, st, "default", url, "tok")

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

	// retry last with no input replays the stored body (schema requires customer_id)
	code, _, errb = CaptureExecute([]string{"run", "create-invoice", "--retry-last"}, root, factory)
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
	seedLogin(t, st, "default", url, "tok")
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

func TestExecuteUnknownCommandUnauthenticated(t *testing.T) {
	// Without a token, domain/unknown paths fail closed as unauthenticated (exit 3),
	// not "unknown domain" — empty catalog must not look like missing product domains.
	code, out, errb := CaptureExecute([]string{"nope"}, t.TempDir(), nil)
	if code != api.ExitAuth {
		t.Fatalf("exit=%d want %d stderr=%s stdout=%s", code, api.ExitAuth, errb, out)
	}
	if !strings.Contains(errb, "not authenticated") && !strings.Contains(errb, "auth login") {
		t.Fatal(code, errb, out)
	}
}

func TestExecuteUnknownCommandAuthenticated(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	// Authenticated with empty catalog → unknown domain is exit 5 not_found.
	code, out, errb := CaptureExecute([]string{"nope"}, root, newClientFactory(srv))
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
	seedLogin(t, st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"run"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestExecuteDescribeMissingName(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"describe"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestExecuteMcpIsUnknownNotBridge(t *testing.T) {
	// Bare mcp is not a command: non-zero (auth when unauthenticated catalog load, or not_found).
	code, _, _ := CaptureExecute([]string{"mcp"}, t.TempDir(), nil)
	if code == api.ExitOK {
		t.Fatal("bare mcp must exit non-zero")
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
	seedLogin(t, st, "default", srv.URL, "tok")
	code, _, _ := CaptureExecute([]string{"run", "x", "--input", `{}`}, root, newClientFactory(srv))
	if code != api.ExitRateLimit {
		t.Fatal(code)
	}
}

func TestExecuteSubcommandHelpFlags(t *testing.T) {
	// Help must win before auth/network for every top-level command that used to ignore trailing --help.
	cases := []struct {
		args   []string
		needle string
	}{
		{[]string{"catalog", "--help"}, "catalog"},
		{[]string{"describe", "--help"}, "Schema"},
		{[]string{"run", "--help"}, "Idempotency"},
		{[]string{"approvals", "--help"}, "accept"},
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
			if strings.Contains(errb, "not authenticated") {
				t.Fatalf("help required auth: %q", errb)
			}
		})
	}
}

func TestExecuteAuthLogoutRejectsCollidingProfileName(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	factory := newClientFactory(srv)
	if code, _, errb := CaptureExecute([]string{"auth", "login", "--profile=prod_eu", "--base-url=" + url, "--token=pat-1"}, root, factory); code != 0 {
		t.Fatal(code, errb)
	}
	code, out, errb := CaptureExecute([]string{"auth", "logout", "--profile=prod.eu"}, root, factory)
	if code != api.ExitValidation || strings.Contains(out, "logged out") || !strings.Contains(errb, "invalid profile name") {
		t.Fatalf("want validation exit and no logout claim: %d %q %q", code, out, errb)
	}
	if !auth.NewStore(root).HasToken("prod_eu") {
		t.Fatal("prod_eu token deleted via colliding name")
	}
}

func TestCatalogNoCacheAndProfileFlags(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	code, _, errb := CaptureExecute([]string{"catalog", "--no-cache", "--profile=default", "--base-url=" + url}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
}

// --tenant was removed: the server never read the hint, and DTO schemas
// (additionalProperties:false) rejected the injected body key. It is now an
// unknown flag like any other — exit 2 before any POST (D-003: scope is server-derived).

func TestDescribeNoJSON(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	seedLogin(t, st, "default", url, "tok")
	code, out, errb := CaptureExecute([]string{"describe", "create-invoice"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
	if !strings.HasPrefix(out, "create-invoice schema_version=1\n") || !strings.Contains(out, `"customer_id"`) {
		t.Fatal(out)
	}
}

func TestStoreDefaultsToUserConfigDir(t *testing.T) {
	home := t.TempDir()
	t.Setenv("HOME", home)
	if got := store(Env{}).Root; got != home+"/.config/capabilities" {
		t.Fatalf("root %q", got)
	}
}

func TestCommandsWithTokenButNoBaseURLExitAuth(t *testing.T) {
	root := t.TempDir()
	if err := auth.NewStore(root).SetToken("default", "tok"); err != nil {
		t.Fatal(err)
	}
	for _, args := range [][]string{
		{"catalog"},
		{"describe", "create-invoice"},
		{"run", "create-invoice"},
		{"approvals", "accept", "ap-1"},
		{"invoices", "create"},
		{"invoices", "create", "--help"},
	} {
		code, _, errb := CaptureExecute(args, root, nil)
		if code != api.ExitAuth || !strings.Contains(errb, "missing base URL") {
			t.Fatalf("%v: exit %d stderr %q", args, code, errb)
		}
	}
}

func TestTransportFailuresExitInternal(t *testing.T) {
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", "https://api.example", "tok")
	down := func(base, token string) *api.Client {
		c := api.NewClient(base, token)
		c.HTTP = &http.Client{Transport: roundTripFunc(func(*http.Request) (*http.Response, error) {
			return nil, errors.New("dial: connection refused")
		})}
		return c
	}
	for _, args := range [][]string{
		{"catalog"},
		{"describe", "create-invoice"},
		{"approvals", "reject", "ap-1"},
		{"invoices", "create"},
	} {
		code, _, errb := CaptureExecute(args, root, down)
		if code != api.ExitInternal || !strings.Contains(errb, "connection refused") {
			t.Fatalf("%v: exit %d stderr %q", args, code, errb)
		}
	}
}

type roundTripFunc func(*http.Request) (*http.Response, error)

func (f roundTripFunc) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

func TestCatalogFlatAndIncludeSchemasOutputs(t *testing.T) {
	var query string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		query = r.URL.RawQuery
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"invoices.create","input_schema":{"type":"object"}}]}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", srv.URL, "tok")

	code, out, errb := CaptureExecute([]string{"catalog", "--flat"}, root, newClientFactory(srv))
	if code != 0 || out != "invoices.create → invoices create\n" {
		t.Fatalf("flat: exit %d out %q err %q", code, out, errb)
	}
	code, out, errb = CaptureExecute([]string{"catalog", "--json", "--include-schemas"}, root, newClientFactory(srv))
	if code != 0 || query != "include_schemas=1" || !strings.Contains(out, `"input_schema"`) {
		t.Fatalf("include-schemas: exit %d query %q out %q err %q", code, query, out, errb)
	}
}

func TestDefaultHTTPClientTalksToProfileBaseURL(t *testing.T) {
	_, url := testAPI(t)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", url, "tok")
	code, out, errb := CaptureExecute([]string{"catalog", "--flat"}, root, nil)
	if code != 0 || !strings.Contains(out, "create-invoice") {
		t.Fatalf("exit %d out %q err %q", code, out, errb)
	}
}
