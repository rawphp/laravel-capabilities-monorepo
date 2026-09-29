package main

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/synth"
)

// noHitAPI fails the test on any request: an unsafe path segment must be
// refused before the network (C-401).
func noHitAPI(t *testing.T) (*httptest.Server, string) {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		t.Errorf("unexpected request %s %s", r.Method, r.URL.Path)
		w.WriteHeader(500)
	}))
	t.Cleanup(srv.Close)
	return srv, srv.URL
}

func assertUsageEnvelope(t *testing.T, args []string, code int, out, errb string) {
	t.Helper()
	if code != api.ExitValidation {
		t.Fatalf("%v: exit %d out %q err %q", args, code, out, errb)
	}
	var env api.ErrorEnvelope
	if err := json.Unmarshal([]byte(out), &env); err != nil || env.OK || env.Error == nil || env.Error.Code != api.CodeValidationFailed {
		t.Fatalf("%v: stdout %q", args, out)
	}
	if !strings.Contains(errb, "single path segment") {
		t.Fatalf("%v: stderr %q", args, errb)
	}
}

func TestUnsafePathSegmentsExit2WithoutHTTP(t *testing.T) {
	srv, url := noHitAPI(t)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", url, "tok")
	for _, args := range [][]string{
		{"run", "approvals/x/accept"},
		{"run", "approvals/x/reject"},
		{"run", "auth/token", "--input", `{}`},
		{"run", ".."},
		{"run", "approvals%2Fx%2Faccept"},
		{"run", "approvals/x/accept", "--help"},
		{"describe", "approvals/x/accept"},
		{"describe", `a\b`},
		{"approvals", "accept", "a/b"},
		{"approvals", "reject", "../x"},
		{"approvals", "accept", "."},
	} {
		code, out, errb := CaptureExecute(args, root, newClientFactory(srv))
		assertUsageEnvelope(t, args, code, out, errb)
	}
}

func TestDomainVerbRefusesUnsafeCanonicalName(t *testing.T) {
	srv, url := noHitAPI(t)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", url, "tok")
	idx := &synth.Index{Domains: map[string]map[string]string{"invoices": {"create": "approvals/x/accept"}}}
	for _, args := range [][]string{{"invoices", "create"}, {"invoices", "create", "--help"}} {
		var out, errb bytes.Buffer
		code := Execute(Env{Args: args, Stdout: &out, Stderr: &errb, ConfigRoot: root, Index: idx, NewClient: newClientFactory(srv)})
		assertUsageEnvelope(t, args, code, out.String(), errb.String())
	}
}

func TestDottedCapabilityNameStillRuns(t *testing.T) {
	srv, url := testAPI(t)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", url, "tok")
	code, out, errb := CaptureExecute([]string{"run", "billing.create-invoice", "--input", `{"customer_id":1}`}, root, newClientFactory(srv))
	if code != api.ExitOK {
		t.Fatalf("exit %d out %q err %q", code, out, errb)
	}
}
