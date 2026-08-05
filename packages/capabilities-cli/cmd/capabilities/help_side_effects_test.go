package main

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func TestAuthLoginHelpDoesNotRequireBaseURL(t *testing.T) {
	code, out, errb := CaptureExecute([]string{"auth", "login", "--help"}, t.TempDir(), nil)
	if code != api.ExitOK {
		t.Fatalf("exit %d stderr=%s", code, errb)
	}
	if !strings.Contains(out, "USAGE:") || strings.Contains(errb, "requires --base-url") {
		t.Fatalf("out=%s err=%s", out, errb)
	}
}

func TestAuthLogoutHelpDoesNotLogout(t *testing.T) {
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", "https://app.example.com", "secret-tok")
	code, out, errb := CaptureExecute([]string{"auth", "logout", "--help"}, root, nil)
	if code != api.ExitOK {
		t.Fatal(code, errb)
	}
	if !strings.Contains(out, "USAGE:") {
		t.Fatal(out)
	}
	if !st.HasToken("default") {
		t.Fatal("logout --help must not clear the token")
	}
	if _, err := st.GetToken("default"); err != nil {
		t.Fatal(err)
	}
}

func TestRunNameHelpShowsCapabilitySchema(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "/capabilities/") {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","description":"d","input_schema":{"type":"object","required":["customer_id"],"properties":{"customer_id":{"type":"integer"}}}}}`))
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", srv.URL, "tok")
	code, out, errb := CaptureExecute([]string{"run", "create-invoice", "--help"}, root, newClientFactory(srv))
	if code != api.ExitOK {
		t.Fatal(code, errb, out)
	}
	if !strings.Contains(out, "customer_id") || !strings.Contains(out, "create-invoice") {
		t.Fatalf("expected schema-first help, got:\n%s", out)
	}
	// Must not only dump the generic run meta-help without fields.
	if strings.Contains(out, "Exit codes (stable):") && !strings.Contains(out, "INPUT:") {
		t.Fatal("generic run help instead of capability help")
	}
}

func TestApprovalsAcceptRequiresID(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	st := auth.NewStore(root)
	_, _ = auth.LoginWithToken(st, "default", url, "tok")
	code, _, errb := CaptureExecute([]string{"approvals", "accept"}, root, newClientFactory(srv))
	if code != api.ExitValidation {
		t.Fatal(code, errb)
	}
	if !strings.Contains(errb, "requires <id>") {
		t.Fatal(errb)
	}
}
