package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
)

func stdoutError(t *testing.T, out string) map[string]any {
	t.Helper()
	var env struct {
		OK    bool           `json:"ok"`
		Error map[string]any `json:"error"`
	}
	if err := json.Unmarshal([]byte(out), &env); err != nil || env.OK || env.Error == nil {
		t.Fatalf("stdout is not a D-018 error envelope: %q", out)
	}
	return env.Error
}

func errorServer(t *testing.T, status int, body string) (*httptest.Server, string) {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(status)
		w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	root := t.TempDir()
	seedLogin(t, auth.NewStore(root), "default", srv.URL, "tok")
	return srv, root
}

func TestApprovalsFailureWritesServerEnvelopeToStdout(t *testing.T) {
	srv, root := errorServer(t, http.StatusGone, `{"ok":false,"error":{"code":"expired","message":"Approval has expired.","approval_id":"ap-1","request_id":"r-9","cli_exit":5}}`)
	code, out, errb := CaptureExecute([]string{"approvals", "accept", "ap-1"}, root, newClientFactory(srv))
	if code != api.ExitDomain {
		t.Fatalf("exit %d", code)
	}
	e := stdoutError(t, out)
	if e["code"] != "expired" || e["approval_id"] != "ap-1" || e["request_id"] != "r-9" {
		t.Fatalf("envelope %#v", e)
	}
	if !strings.Contains(errb, "Approval has expired.") {
		t.Fatalf("stderr %q", errb)
	}
}

func TestCatalogFailureWritesServerEnvelopeToStdout(t *testing.T) {
	srv, root := errorServer(t, http.StatusUnauthorized, `{"ok":false,"error":{"code":"unauthenticated","message":"bad token","cli_exit":3}}`)
	code, out, _ := CaptureExecute([]string{"catalog", "--json"}, root, newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatalf("exit %d", code)
	}
	if e := stdoutError(t, out); e["code"] != "unauthenticated" {
		t.Fatalf("envelope %#v", e)
	}
}

func TestNonEnvelopeFailureWritesSynthesizedEnvelopeNotHTML(t *testing.T) {
	srv, root := errorServer(t, http.StatusBadGateway, `<!doctype html><html>proxy error</html>`)
	code, out, _ := CaptureExecute([]string{"catalog"}, root, newClientFactory(srv))
	if code != api.ExitInternal {
		t.Fatalf("exit %d", code)
	}
	if strings.Contains(out, "<html") {
		t.Fatalf("raw HTML on stdout: %q", out)
	}
	if e := stdoutError(t, out); e["code"] != api.CodeInternal || e["cli_exit"] != float64(api.ExitInternal) {
		t.Fatalf("envelope %#v", e)
	}
}

func TestDomainVerbCatalogFailureWritesEnvelopeToStdout(t *testing.T) {
	srv, root := errorServer(t, http.StatusUnauthorized, `{"ok":false,"error":{"code":"unauthenticated","message":"bad token","cli_exit":3}}`)
	code, out, _ := CaptureExecute([]string{"billing", "create"}, root, newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatalf("exit %d", code)
	}
	stdoutError(t, out)
}

func TestDescribeNonEnvelopeFailureDoesNotDumpBody(t *testing.T) {
	srv, root := errorServer(t, http.StatusBadGateway, `<!doctype html><html>proxy error</html>`)
	_, out, _ := CaptureExecute([]string{"describe", "x"}, root, newClientFactory(srv))
	if strings.Contains(out, "<html") {
		t.Fatalf("raw HTML on stdout: %q", out)
	}
	stdoutError(t, out)
}
