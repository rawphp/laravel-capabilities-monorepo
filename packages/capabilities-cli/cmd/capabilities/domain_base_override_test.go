package main

import (
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/auth"
)

// --base-url must steer the domain/verb catalog lookup too, so resolution and
// invoke hit the same deployment (and the token is not sent to the stored host).
func TestDomainVerbCatalogUsesBaseURLOverride(t *testing.T) {
	var storedHits int32
	stored := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		atomic.AddInt32(&storedHits, 1)
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(stored.Close)

	var listHits, invokeHits int32
	override := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/capabilities":
			atomic.AddInt32(&listHits, 1)
			w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"billing.create-invoice","surfaces":["cli"],"cli":{"domain":"billing","verb":"create"}}]}}`))
		case r.Method == http.MethodGet && r.URL.Path == "/capabilities/billing.create-invoice":
			w.Write([]byte(`{"ok":true,"data":{"name":"billing.create-invoice","schema_version":"1","input_schema":{"type":"object"},"output_schema":{}}}`))
		case r.Method == http.MethodPost && r.URL.Path == "/capabilities/billing.create-invoice":
			atomic.AddInt32(&invokeHits, 1)
			w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
		default:
			w.Write([]byte(`{"ok":true,"data":{}}`))
		}
	}))
	t.Cleanup(override.Close)

	root := t.TempDir()
	st := auth.NewStore(root)
	_ = st.SetBaseURL("default", stored.URL)
	_ = st.SetToken("default", "tok")

	code, out, errb := CaptureExecute([]string{"--base-url=" + override.URL, "billing", "create", "--input={}"}, root, newClientFactory(override))
	if code != 0 {
		t.Fatalf("exit %d out=%s err=%s", code, out, errb)
	}
	if atomic.LoadInt32(&storedHits) != 0 {
		t.Fatalf("stored base URL contacted %d times despite --base-url", storedHits)
	}
	if listHits == 0 || invokeHits != 1 {
		t.Fatalf("override list=%d invoke=%d", listHits, invokeHits)
	}
}
