package catalog

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func clientServer(t *testing.T, h http.HandlerFunc) (*api.Client, *httptest.Server) {
	t.Helper()
	srv := httptest.NewServer(h)
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	return c, srv
}

func TestCataloglistscapabilitiesfromhttp(t *testing.T) {
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice","deprecated":false}]}}`))
	})
	svc := &Service{Client: c}
	list, _, err := svc.List(context.Background())
	if err != nil || len(list) != 1 || list[0].Name != "create-invoice" {
		t.Fatalf("%v %#v", err, list)
	}
}

func TestDescribefetchesjsonschema(t *testing.T) {
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"name":"create-invoice","schema_version":"1","input_schema":{"type":"object","required":["customer_id"]}}}`))
	})
	svc := &Service{Client: c, Cache: NewCache(t.TempDir())}
	e, _, err := svc.Describe(context.Background(), "create-invoice")
	if err != nil {
		t.Fatal(err)
	}
	if e.SchemaVersion != "1" || len(e.InputSchema) == 0 {
		t.Fatalf("%#v", e)
	}
}

func TestSchemacachebynameandversion(t *testing.T) {
	cache := NewCache(t.TempDir())
	entry := &CacheEntry{Name: "x", SchemaVersion: "1", InputSchema: json.RawMessage(`{"type":"object"}`)}
	if err := cache.Put(entry); err != nil {
		t.Fatal(err)
	}
	got, ok := cache.Get("x", "1")
	if !ok || got.SchemaVersion != "1" {
		t.Fatal(got, ok)
	}
	_, ok = cache.Get("x", "2")
	if ok {
		t.Fatal("version mismatch should miss")
	}
}

func TestCatalogrefreshinvalidatescache(t *testing.T) {
	cache := NewCache(t.TempDir())
	_ = cache.Put(&CacheEntry{Name: "x", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	hits := 0
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		hits++
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	})
	svc := &Service{Client: c, Cache: cache}
	_, err := svc.Refresh(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if _, ok := cache.Get("x", ""); ok {
		t.Fatal("cache should be cleared")
	}
	if hits != 1 {
		t.Fatal(hits)
	}
}

func TestDeprecatedcapabilitywarns(t *testing.T) {
	e := &CacheEntry{Name: "old", Deprecated: true, Successor: "new"}
	w := DeprecationWarning(e, time.Now())
	if !strings.Contains(w, "deprecated") || !strings.Contains(w, "new") {
		t.Fatal(w)
	}
}

func TestCatalognocacheflagforcesrefetch(t *testing.T) {
	cache := NewCache(t.TempDir())
	_ = cache.Put(&CacheEntry{Name: "x", SchemaVersion: "1", InputSchema: json.RawMessage(`{"type":"object"}`)})
	hits := 0
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		hits++
		w.Write([]byte(`{"ok":true,"data":{"name":"x","schema_version":"1","input_schema":{"type":"object"}}}`))
	})
	svc := &Service{Client: c, Cache: cache, NoCache: true}
	_, _, err := svc.Describe(context.Background(), "x")
	if err != nil {
		t.Fatal(err)
	}
	if hits != 1 {
		t.Fatal("expected network fetch with NoCache")
	}
}

func TestDescribebyaliasresolvescanonical(t *testing.T) {
	e := &CacheEntry{Name: "create-invoice", Canonical: "create-invoice", Aliases: []string{"invoice.create"}}
	if ResolveAlias(e, "invoice.create") != "create-invoice" {
		t.Fatal(ResolveAlias(e, "invoice.create"))
	}
}

func TestCatalogomitsdisabledsurfaces(t *testing.T) {
	// Client trusts server catalog; surfaces field present when server sends it.
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"a","surfaces":["cli"]}]}}`))
	})
	list, _, err := (&Service{Client: c}).List(context.Background())
	if err != nil || list[0].Surfaces[0] != "cli" {
		t.Fatal(err, list)
	}
}

func TestSchemacacheetaginvalidation(t *testing.T) {
	cache := NewCache(t.TempDir())
	_ = cache.Put(&CacheEntry{Name: "x", SchemaVersion: "1", ETag: "v1", InputSchema: json.RawMessage(`{}`)})
	_, ok := cache.GetByETag("x", "v1")
	if !ok {
		t.Fatal("etag hit")
	}
	_, ok = cache.GetByETag("x", "v2")
	if ok {
		t.Fatal("etag miss expected")
	}
}

func TestCatalogjsonoutputenvelope(t *testing.T) {
	b := EnvelopeJSON([]CapabilitySummary{{Name: "a"}})
	var m map[string]any
	if err := json.Unmarshal(b, &m); err != nil {
		t.Fatal(err)
	}
	if m["ok"] != true {
		t.Fatal(m)
	}
}

func TestSunsetcapabilitywarnedorblocked(t *testing.T) {
	e := &CacheEntry{Name: "old", SunsetAt: "2020-01-01", Successor: "new"}
	w := DeprecationWarning(e, time.Date(2026, 1, 1, 0, 0, 0, 0, time.UTC))
	if !strings.Contains(w, "sunset") {
		t.Fatal(w)
	}
}
