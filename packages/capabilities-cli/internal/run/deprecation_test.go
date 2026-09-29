package run

import (
	"context"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

const deprecationLine = `capability "create-invoice" is deprecated; successor: create-invoice-v2`

// deprecatedHarness serves a deprecated describe entry; invoke is handled by post.
func deprecatedHarness(t *testing.T, post http.HandlerFunc) Options {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","deprecated":true,"successor":"create-invoice-v2","input_schema":{"type":"object","properties":{"customer_id":{"type":"integer"}}}}}`))
			return
		}
		post(w, r)
	}))
	t.Cleanup(srv.Close)

	root := t.TempDir()
	st := auth.NewStore(root)
	_ = st.SetToken("default", "tok")
	c := api.NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	return Options{
		Profile:     "default",
		Capability:  "create-invoice",
		InputJSON:   []byte(`{"customer_id":42}`),
		Store:       st,
		Client:      c,
		Catalog:     &catalog.Service{Client: c, Cache: catalog.NewCache(st.SchemaCacheDir("default"))},
		LastRunPath: filepath.Join(root, "last_run.json"),
	}
}

func assertDeprecationKept(t *testing.T, res *Result, errPart string) {
	t.Helper()
	if res.Deprecation != deprecationLine {
		t.Fatalf("deprecation = %q", res.Deprecation)
	}
	if !strings.HasPrefix(res.Stderr, deprecationLine+"\n") {
		t.Fatalf("stderr lost D-012 warning: %q", res.Stderr)
	}
	if !strings.Contains(res.Stderr, errPart) {
		t.Fatalf("stderr missing invoke error %q: %q", errPart, res.Stderr)
	}
}

func TestRunDeprecatedStructuredErrorKeepsWarning(t *testing.T) {
	opts := deprecatedHarness(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusForbidden)
		w.Write([]byte(`{"ok":false,"error":{"code":"forbidden","message":"not allowed","retryable":false}}`))
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitCodeFor(api.CodeForbidden) {
		t.Fatalf("exit = %d", res.ExitCode)
	}
	assertDeprecationKept(t, res, "not allowed")
}

func TestRunDeprecatedTransportErrorKeepsWarning(t *testing.T) {
	opts := deprecatedHarness(t, func(w http.ResponseWriter, r *http.Request) {
		conn, _, err := w.(http.Hijacker).Hijack()
		if err != nil {
			t.Fatal(err)
		}
		conn.Close()
	})
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitInternal || !res.HTTPCalled {
		t.Fatalf("exit = %d called = %v", res.ExitCode, res.HTTPCalled)
	}
	assertDeprecationKept(t, res, "create-invoice")
}

func TestRunDeprecatedHumanSuccessKeepsWarning(t *testing.T) {
	opts := deprecatedHarness(t, func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"id":"inv_1"}}`))
	})
	opts.Human = true
	res := Run(context.Background(), opts)
	if res.ExitCode != ExitOK {
		t.Fatalf("exit = %d", res.ExitCode)
	}
	if res.Stderr != deprecationLine+"\nok create-invoice id=inv_1\n" {
		t.Fatalf("stderr = %q", res.Stderr)
	}
}

func TestAppendStderrSeparatesLines(t *testing.T) {
	res := &Result{Stderr: "warning"}
	appendStderr(res, "boom")
	if res.Stderr != "warning\nboom" {
		t.Fatalf("stderr = %q", res.Stderr)
	}
	empty := &Result{}
	appendStderr(empty, "boom")
	if empty.Stderr != "boom" {
		t.Fatalf("stderr = %q", empty.Stderr)
	}
}
