package main

import (
	"bytes"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func TestCmdMcpHappyPath(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"t1"}]}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	var out, errb bytes.Buffer
	in := bytes.NewBufferString(`{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}` + "\n")
	code := Execute(Env{
		Args:       []string{"mcp"},
		Stdout:     &out,
		Stderr:     &errb,
		Stdin:      in,
		ConfigRoot: root,
		NewClient: func(base, token string) *api.Client {
			c := api.NewClient(base, token)
			c.HTTP = srv.Client()
			return c
		},
	})
	if code != 0 {
		t.Fatal(code, errb.String())
	}
	if !strings.Contains(out.String(), "t1") {
		t.Fatal(out.String())
	}
}

func TestCommandHelpDefaultsAndExists(t *testing.T) {
	if !strings.Contains(CommandHelp("unknown-cmd"), "capabilities") {
		t.Fatal()
	}
	if !CommandExists("auth") || !CommandExists("help") {
		t.Fatal()
	}
	if CommandExists("not-a-real-command") {
		t.Fatal()
	}
}

func TestAuthUnknownSubcommand(t *testing.T) {
	code, _, errb := CaptureExecute([]string{"auth", "wat"}, t.TempDir(), nil)
	if code == 0 || !strings.Contains(errb, "unknown") {
		t.Fatal(code, errb)
	}
}

func TestApprovalsUnknownAction(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"approvals", "shrug", "1"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestApprovalsMissingArgs(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, _ := CaptureExecute([]string{"approvals"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code)
	}
}

func TestCatalogNoCacheAndProfileFlags(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, errb := CaptureExecute([]string{"catalog", "--no-cache", "--profile=default", "--base-url=" + url}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
}

func TestRunTenantAndJSONFlags(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, out, errb := CaptureExecute([]string{
		"run", "create-invoice",
		"--input={\"customer_id\":1}",
		"--tenant=acme",
		"--json",
		"--no-cache",
	}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, out, errb)
	}
}

func TestDescribeNoJSON(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, out, errb := CaptureExecute([]string{"describe", "create-invoice"}, root, newClientFactory(srv))
	if code != 0 {
		t.Fatal(code, errb)
	}
	if !strings.Contains(out, "schema_version") && !strings.Contains(out, "create-invoice") {
		t.Fatal(out)
	}
}

func TestStoreDefaultConfigRoot(t *testing.T) {
	// store(env) with empty ConfigRoot uses home — just ensure Execute path works with explicit root
	code, _, _ := CaptureExecute([]string{"version"}, t.TempDir(), nil)
	if code != 0 {
		t.Fatal(code)
	}
}

func TestClientForMissingBaseURL(t *testing.T) {
	root := t.TempDir()
	st := auth.NewStore(root)
	_ = st.SetToken("default", "tok")
	// no base url
	code, _, errb := CaptureExecute([]string{"catalog"}, root, nil)
	if code != api.ExitAuth && code != api.ExitInternal {
		// clientFor returns error → ExitAuth path in cmdCatalog
		if code == 0 {
			t.Fatal(code, errb)
		}
	}
}

func TestAuthLoginInvalidBase(t *testing.T) {
	code, _, _ := CaptureExecute([]string{"auth", "login", "--base-url=ftp://bad"}, t.TempDir(), nil)
	if code == 0 {
		t.Fatal()
	}
}

func TestExecuteDefaultsWriters(t *testing.T) {
	// Ensure nil stdout/stderr paths don't panic for version with custom env partially set
	var out bytes.Buffer
	code := Execute(Env{Args: []string{"version"}, Stdout: &out, ConfigRoot: t.TempDir()})
	if code != 0 {
		t.Fatal(code)
	}
}

func TestCatalogServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(403)
		w.Write([]byte(`{"ok":false,"error":{"code":"forbidden","message":"no"}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	code, _, _ := CaptureExecute([]string{"catalog"}, root, newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatal(code)
	}
}

func TestDescribeServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(404)
		w.Write([]byte(`{"ok":false,"error":{"code":"not_found","message":"no"}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	code, _, _ := CaptureExecute([]string{"describe", "missing"}, root, newClientFactory(srv))
	if code != api.ExitDomain {
		t.Fatal(code)
	}
}

func TestApprovalsServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(500)
		w.Write([]byte(`{"ok":false,"error":{"code":"internal","message":"boom"}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	code, _, _ := CaptureExecute([]string{"approvals", "accept", "x"}, root, newClientFactory(srv))
	if code != api.ExitInternal {
		t.Fatal(code)
	}
}
