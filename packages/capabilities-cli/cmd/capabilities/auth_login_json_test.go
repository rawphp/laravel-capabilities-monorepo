package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func unauthenticatedLoginAPI(t *testing.T) *httptest.Server {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusUnauthorized)
		w.Write([]byte(`{"ok":false,"error":{"code":"unauthenticated","message":"bad client","retryable":false,"approval_id":null}}`))
	}))
	t.Cleanup(srv.Close)
	return srv
}

func decodeEnvelope(t *testing.T, out string) map[string]any {
	t.Helper()
	var env map[string]any
	if err := json.Unmarshal([]byte(out), &env); err != nil {
		t.Fatalf("stdout is not a JSON envelope: %v\n%s", err, out)
	}
	return env
}

func TestAuthLoginJSONStructuredErrorWritesEnvelope(t *testing.T) {
	srv := unauthenticatedLoginAPI(t)
	code, out, errb := CaptureExecute([]string{"auth", "login", "--base-url=" + srv.URL, "--json"}, t.TempDir(), newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatalf("exit=%d want %d stderr=%s", code, api.ExitAuth, errb)
	}
	env := decodeEnvelope(t, out)
	if env["ok"] != false {
		t.Fatal(out)
	}
	e := env["error"].(map[string]any)
	if e["code"] != "unauthenticated" || e["message"] != "bad client" {
		t.Fatal(e)
	}
	if !strings.Contains(errb, "bad client") {
		t.Fatal(errb)
	}
}

func TestAuthLoginStructuredErrorUsesMappedExitWithoutJSON(t *testing.T) {
	srv := unauthenticatedLoginAPI(t)
	code, out, errb := CaptureExecute([]string{"auth", "login", "--base-url=" + srv.URL, "--code=abc"}, t.TempDir(), newClientFactory(srv))
	if code != api.ExitAuth {
		t.Fatalf("exit=%d want %d", code, api.ExitAuth)
	}
	if out != "" {
		t.Fatalf("stdout should stay empty without --json: %q", out)
	}
	if !strings.Contains(errb, "bad client") {
		t.Fatal(errb)
	}
}

func TestAuthLoginJSONPlainErrorWritesInternalEnvelope(t *testing.T) {
	code, out, errb := CaptureExecute([]string{"auth", "login", "--base-url=ftp://bad", "--token=tok", "--json"}, t.TempDir(), nil)
	if code != api.ExitInternal {
		t.Fatalf("exit=%d stderr=%s", code, errb)
	}
	env := decodeEnvelope(t, out)
	e := env["error"].(map[string]any)
	if env["ok"] != false || e["code"] != api.CodeInternal || e["message"] == "" {
		t.Fatal(out)
	}
}

func TestAuthLoginJSONSuccessEnvelopeNeverPrintsToken(t *testing.T) {
	srv, url := testAPI(t)
	code, out, errb := CaptureExecute([]string{"auth", "login", "--base-url=" + url, "--token=secret-pat", "--json"}, t.TempDir(), newClientFactory(srv))
	if code != api.ExitOK {
		t.Fatal(code, errb)
	}
	env := decodeEnvelope(t, out)
	data := env["data"].(map[string]any)
	if env["ok"] != true || data["profile"] != "default" || data["base_url"] != url || data["logged_in"] != true {
		t.Fatal(out)
	}
	if strings.Contains(out, "secret-pat") {
		t.Fatal("token leaked")
	}
}

func TestAuthHelpDocumentsLoginJSON(t *testing.T) {
	if !strings.Contains(CommandHelp("auth login"), "[--profile=NAME] [--json]\n  capabilities auth logout") {
		t.Fatal(CommandHelp("auth login"))
	}
}
