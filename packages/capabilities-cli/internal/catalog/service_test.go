package catalog

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestListRejectsNonEnvelopeShapes(t *testing.T) {
	// The server only sends {"ok":true,"data":{"capabilities":[...]}}; anything else is a parse error,
	// including rows that would previously have been salvaged as name-only summaries.
	cases := map[string]string{
		"data as array":       `{"ok":true,"data":[{"name":"a"}]}`,
		"bare array":          `[{"name":"b"}]`,
		"missing key":         `{"ok":true,"data":{"nope":1}}`,
		"null capabilities":   `{"ok":true,"data":{"capabilities":null}}`,
		"mistyped row fields": `{"ok":true,"data":{"capabilities":[{"name":"c","aliases":"x"}]}}`,
		"not json":            `nope`,
	}
	for label, body := range cases {
		body := body
		t.Run(label, func(t *testing.T) {
			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				w.Write([]byte(body))
			}))
			t.Cleanup(srv.Close)
			c := api.NewClient(srv.URL, "t")
			c.HTTP = srv.Client()
			list, res, err := (&Service{Client: c}).List(context.Background())
			if err == nil {
				t.Fatalf("expected shape error, got %v", list)
			}
			if res == nil {
				t.Fatal("expected response alongside parse error")
			}
			if !strings.Contains(err.Error(), "unexpected catalog list shape") {
				t.Fatalf("unclear error: %v", err)
			}
		})
	}
}

func TestListEmptyEnvelopeIsEmptyList(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[],"schema_version":"","etag":"e"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	list, _, err := (&Service{Client: c}).List(context.Background())
	if err != nil || list == nil || len(list) != 0 {
		t.Fatal(err, list)
	}
}

func TestParseDescribeBareAndEtag(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("ETag", `"abc"`)
		w.Write([]byte(`{"name":"bare","schema_version":"2","input_schema":{"type":"object"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	e, _, err := (&Service{Client: c, Cache: NewCache(t.TempDir())}).Describe(context.Background(), "bare")
	if err != nil || e.ETag != "abc" || e.SchemaVersion != "2" {
		t.Fatal(err, e)
	}
}

func TestParseDescribeMapsFields(t *testing.T) {
	res := &api.Response{
		Body:   []byte(`{"ok":true,"data":{"name":"n","schema_version":"9","input_schema":{"type":"object"},"aliases":["al"]}}`),
		Header: http.Header{},
	}
	res.Header.Set("ETag", `"zz"`)
	e, err := parseDescribe(res)
	if err != nil || e.Name != "n" || e.SchemaVersion != "9" {
		t.Fatal(err, e)
	}
	if e.ETag != "zz" {
		t.Fatalf("etag %q", e.ETag)
	}
	// invalid body
	if _, err := parseDescribe(&api.Response{Body: []byte(`{`), Header: http.Header{}}); err == nil {
		t.Fatal()
	}
}

// unreachable returns a client whose every request fails at the transport.
func unreachable() *api.Client {
	c := api.NewClient("https://unreachable.invalid", "t")
	c.HTTP = &http.Client{Transport: roundTripFunc(func(*http.Request) (*http.Response, error) {
		return nil, errors.New("dial: connection refused")
	})}
	return c
}

type roundTripFunc func(*http.Request) (*http.Response, error)

func (f roundTripFunc) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

func TestServiceSurfacesTransportErrors(t *testing.T) {
	svc := &Service{Client: unreachable(), Cache: NewCache(t.TempDir())}
	ctx := context.Background()
	if _, _, err := svc.List(ctx); err == nil {
		t.Fatal("List")
	}
	if _, _, err := svc.ListWithSchemas(ctx); err == nil {
		t.Fatal("ListWithSchemas")
	}
	if _, _, err := svc.Describe(ctx, "x"); err == nil {
		t.Fatal("Describe")
	}
}

func TestServiceReturnsServerErrorEnvelopes(t *testing.T) {
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
		w.Write([]byte(`{"ok":false,"error":{"code":"not_found","message":"no"}}`))
	})
	svc := &Service{Client: c, Cache: NewCache(t.TempDir())}
	var se *api.StructuredError
	if _, _, err := svc.List(context.Background()); !errors.As(err, &se) || se.Code != api.CodeNotFound {
		t.Fatalf("List: %v", err)
	}
	if _, _, err := svc.Describe(context.Background(), "x"); !errors.As(err, &se) || se.Code != api.CodeNotFound {
		t.Fatalf("Describe: %v", err)
	}
}

func TestListWithSchemasRequestsSchemasAndDecodesThem(t *testing.T) {
	var query string
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		query = r.URL.RawQuery
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"a","input_schema":{"type":"object"}}]}}`))
	})
	list, _, err := (&Service{Client: c}).ListWithSchemas(context.Background())
	if err != nil || len(list) != 1 {
		t.Fatalf("%v %v", err, list)
	}
	if query != "include_schemas=1" {
		t.Fatalf("query %q", query)
	}
	if string(list[0].InputSchema) != `{"type":"object"}` {
		t.Fatalf("input_schema %s", list[0].InputSchema)
	}
}

func TestDescribeMalformedBodyIsNotCached(t *testing.T) {
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"name":5}}`))
	})
	cache := NewCache(t.TempDir())
	if _, _, err := (&Service{Client: c, Cache: cache}).Describe(context.Background(), "x"); err == nil {
		t.Fatal("expected parse error")
	}
	if _, ok := cache.Get("x", ""); ok {
		t.Fatal("malformed describe must not be cached")
	}
}

func TestForceFetchDescribeBypassesAndReplacesCachedEntry(t *testing.T) {
	hits := 0
	c, _ := clientServer(t, func(w http.ResponseWriter, r *http.Request) {
		hits++
		w.Write([]byte(`{"ok":true,"data":{"name":"n","schema_version":"2","input_schema":{"type":"object"}}}`))
	})
	cache := NewCache(t.TempDir())
	_ = cache.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	e, _, err := (&Service{Client: c, Cache: cache}).ForceFetchDescribe(context.Background(), "n")
	if err != nil || e.SchemaVersion != "2" || hits != 1 {
		t.Fatalf("%v %+v hits=%d", err, e, hits)
	}
	if cached, _ := cache.Get("n", ""); cached.SchemaVersion != "2" {
		t.Fatalf("stale cache kept: %+v", cached)
	}
}
