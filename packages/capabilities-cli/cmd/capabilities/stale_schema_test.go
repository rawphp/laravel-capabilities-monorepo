package main

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

type schemaServer struct {
	srv       *httptest.Server
	describes int32
	invokes   int32
}

// newSchemaServer serves one capability "ship" whose live schema requires
// "parcel_id" (an older schema required "order_id").
func newSchemaServer(t *testing.T, invokeBody string) *schemaServer {
	t.Helper()
	s := &schemaServer{}
	s.srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.Method == http.MethodGet && r.URL.Path == "/capabilities/ship":
			atomic.AddInt32(&s.describes, 1)
			w.Write([]byte(`{"ok":true,"data":{"name":"ship","schema_version":"2","input_schema":{"type":"object","required":["parcel_id"],"properties":{"parcel_id":{"type":"integer"}}}}}`))
		case r.Method == http.MethodPost && r.URL.Path == "/capabilities/ship":
			atomic.AddInt32(&s.invokes, 1)
			if invokeBody != "" {
				w.WriteHeader(http.StatusUnprocessableEntity)
				w.Write([]byte(invokeBody))
				return
			}
			w.Write([]byte(`{"ok":true,"data":{"shipped":true}}`))
		default:
			w.Write([]byte(`{"ok":true,"data":{}}`))
		}
	}))
	t.Cleanup(s.srv.Close)
	return s
}

func seedSchemaCache(t *testing.T, root, base string, entry *catalog.CacheEntry) *catalog.Cache {
	t.Helper()
	st := auth.NewStore(root)
	_ = st.SetBaseURL("default", base)
	_ = st.SetToken("default", "tok")
	cache := catalog.PrincipalCache(st.SchemaCacheDir("default"), api.NewClient(base, "tok"))
	if entry != nil {
		if err := cache.Put(entry); err != nil {
			t.Fatal(err)
		}
	}
	return cache
}

var staleShip = &catalog.CacheEntry{
	Name:          "ship",
	SchemaVersion: "1",
	InputSchema:   json.RawMessage(`{"type":"object","required":["order_id"],"properties":{"order_id":{"type":"integer"}}}`),
}

func TestRunRefetchesStaleCachedSchemaBeforeRejectingInput(t *testing.T) {
	s := newSchemaServer(t, "")
	root := t.TempDir()
	cache := seedSchemaCache(t, root, s.srv.URL, staleShip)

	code, out, errb := CaptureExecute([]string{"run", "ship", `--input={"parcel_id":7}`}, root, newClientFactory(s.srv))
	if code != api.ExitOK {
		t.Fatalf("exit %d out=%s err=%s", code, out, errb)
	}
	if s.describes != 1 || s.invokes != 1 {
		t.Fatalf("describes=%d invokes=%d", s.describes, s.invokes)
	}
	if e, ok := cache.Get("ship", ""); !ok || e.SchemaVersion != "2" {
		t.Fatalf("cache not refreshed: %#v", e)
	}
}

func TestRunRefetchesStaleCachedSchemaForUnknownFlag(t *testing.T) {
	s := newSchemaServer(t, "")
	root := t.TempDir()
	seedSchemaCache(t, root, s.srv.URL, staleShip)

	code, out, errb := CaptureExecute([]string{"run", "ship", "--parcel-id=7"}, root, newClientFactory(s.srv))
	if code != api.ExitOK {
		t.Fatalf("exit %d out=%s err=%s", code, out, errb)
	}
	if s.invokes != 1 {
		t.Fatalf("invokes=%d", s.invokes)
	}
}

func TestRunStillRejectsInputInvalidAgainstLiveSchema(t *testing.T) {
	s := newSchemaServer(t, "")
	root := t.TempDir()
	seedSchemaCache(t, root, s.srv.URL, staleShip)

	code, out, _ := CaptureExecute([]string{"run", "ship", `--input={"nope":1}`}, root, newClientFactory(s.srv))
	if code != api.ExitValidation {
		t.Fatalf("exit %d", code)
	}
	if s.invokes != 0 {
		t.Fatal("invoked despite local validation failure")
	}
	var env map[string]any
	if json.Unmarshal([]byte(out), &env) != nil || env["ok"] != false {
		t.Fatalf("stdout envelope %q", out)
	}
}

func TestRunDescribesOnceOnCacheMiss(t *testing.T) {
	s := newSchemaServer(t, "")
	root := t.TempDir()
	seedSchemaCache(t, root, s.srv.URL, nil)

	code, _, errb := CaptureExecute([]string{"run", "ship", `--input={"parcel_id":7}`}, root, newClientFactory(s.srv))
	if code != api.ExitOK {
		t.Fatalf("exit %d %s", code, errb)
	}
	if s.describes != 1 {
		t.Fatalf("describes=%d want 1", s.describes)
	}
}

func TestRunServerValidationFailureDropsCachedSchema(t *testing.T) {
	s := newSchemaServer(t, `{"ok":false,"error":{"code":"validation_failed","message":"bad","cli_exit":2}}`)
	root := t.TempDir()
	// Cached schema accepts the input; the server (law) rejects it.
	cache := seedSchemaCache(t, root, s.srv.URL, &catalog.CacheEntry{Name: "ship", SchemaVersion: "1", InputSchema: json.RawMessage(`{"type":"object"}`)})

	code, _, _ := CaptureExecute([]string{"run", "ship", `--input={"parcel_id":7}`}, root, newClientFactory(s.srv))
	if code != api.ExitValidation {
		t.Fatalf("exit %d", code)
	}
	if _, ok := cache.Get("ship", ""); ok {
		t.Fatal("stale schema still cached after server validation_failed")
	}
}
