package run

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

type invokeRec struct {
	Path string
	Key  string
	Body []byte
	N    int
}

func harness(t *testing.T, handler http.HandlerFunc) (Options, *invokeRec) {
	t.Helper()
	rec := &invokeRec{}
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet && (r.URL.Path == "/capabilities/create-invoice" || r.URL.Path == "/capabilities/x") {
			w.Header().Set("ETag", "e1")
			w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object","required":["customer_id"],"properties":{"customer_id":{"type":"integer"},"amount_cents":{"type":"integer"},"currency":{"type":"string"}}}}}`))
			return
		}
		if r.Method == http.MethodGet && r.URL.Path == "/capabilities" {
			w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice"}]}}`))
			return
		}
		if r.Method == http.MethodPost {
			rec.N++
			rec.Path = r.URL.Path
			rec.Key = r.Header.Get("Idempotency-Key")
			var err error
			rec.Body, err = readBody(r)
			if err != nil {
				t.Fatal(err)
			}
		}
		if handler != nil {
			handler(w, r)
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"invoice_id":1},"meta":{"request_id":"r1","capability":"create-invoice"}}`))
	}))
	t.Cleanup(srv.Close)

	root := t.TempDir()
	st := auth.NewStore(root)
	_ = st.SetBaseURL("default", srv.URL)
	_ = st.SetToken("default", "tok")
	c := api.NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	cache := catalog.NewCache(st.SchemaCacheDir("default"))
	svc := &catalog.Service{Client: c, Cache: cache}
	opts := Options{
		Profile:    "default",
		Capability: "create-invoice",
		InputJSON:  []byte(`{"customer_id":42,"amount_cents":100,"currency":"USD"}`),
		Store:      st,
		Client:     c,
		Catalog:    svc,
		LastRunPath: filepath.Join(root, "last_run.json"),
	}
	return opts, rec
}

func readBody(r *http.Request) ([]byte, error) {
	defer r.Body.Close()
	return io.ReadAll(r.Body)
}

func writeInputFile(t *testing.T, content string) string {
	t.Helper()
	p := filepath.Join(t.TempDir(), "in.json")
	if err := os.WriteFile(p, []byte(content), 0o600); err != nil {
		t.Fatal(err)
	}
	return p
}

func errEnvelope(code, msg string) []byte {
	b, _ := json.Marshal(map[string]any{
		"ok": false,
		"error": map[string]any{
			"code": code, "message": msg, "retryable": false,
		},
	})
	return b
}
