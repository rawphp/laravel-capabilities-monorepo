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

func TestListStructuredError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(401)
		w.Write([]byte(`{"ok":false,"error":{"code":"unauthenticated","message":"x"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	if _, _, err := (&Service{Client: c}).List(context.Background()); err == nil {
		t.Fatal()
	}
}

func TestForceFetchAndDescribeErrors(t *testing.T) {
	hits := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		hits++
		w.Write([]byte(`{"ok":true,"data":{"name":"n","schema_version":"1","input_schema":{"type":"object"},"aliases":["a"],"canonical":"n"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	cache := NewCache(t.TempDir())
	_ = cache.Put(&CacheEntry{Name: "n", SchemaVersion: "1", InputSchema: json.RawMessage(`{}`)})
	svc := &Service{Client: c, Cache: cache}
	e, _, err := svc.ForceFetchDescribe(context.Background(), "n")
	if err != nil || e.Name != "n" {
		t.Fatal(err, e)
	}
	if hits != 1 {
		t.Fatal(hits)
	}
	if ResolveAlias(e, "a") != "n" {
		t.Fatal(ResolveAlias(e, "a"))
	}
	if ResolveAlias(nil, "x") != "x" {
		t.Fatal()
	}
	if ResolveAlias(e, "other") != "other" {
		// name not matching returns name
	}
}

func TestDescribeAPIError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(404)
		w.Write([]byte(`{"ok":false,"error":{"code":"not_found","message":"no"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	if _, _, err := (&Service{Client: c, Cache: NewCache(t.TempDir())}).Describe(context.Background(), "x"); err == nil {
		t.Fatal()
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

func TestDeprecationWarningNilAndFutureSunset(t *testing.T) {
	if DeprecationWarning(nil, time.Now()) != "" {
		t.Fatal()
	}
	w := DeprecationWarning(&CacheEntry{Name: "x", SunsetAt: "2099-01-01", Deprecated: false}, time.Now())
	if w != "" {
		t.Fatal(w)
	}
}

func TestInvalidateMissingDir(t *testing.T) {
	c := NewCache(t.TempDir() + "/nope")
	if err := c.Invalidate(""); err != nil {
		t.Fatal(err)
	}
	if err := c.Invalidate("x"); err != nil {
		t.Fatal(err)
	}
}

func TestListNetworkError(t *testing.T) {
	c := api.NewClient("http://127.0.0.1:1", "t")
	if _, _, err := (&Service{Client: c}).List(context.Background()); err == nil {
		t.Fatal()
	}
}

func TestDescribeNetworkError(t *testing.T) {
	c := api.NewClient("http://127.0.0.1:1", "t")
	if _, _, err := (&Service{Client: c, Cache: NewCache(t.TempDir())}).Describe(context.Background(), "x"); err == nil {
		t.Fatal()
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

func TestResolveAliasCanonicalEmpty(t *testing.T) {
	e := &CacheEntry{Name: "n", Aliases: []string{"a"}}
	if ResolveAlias(e, "a") != "n" {
		t.Fatal(ResolveAlias(e, "a"))
	}
	if ResolveAlias(e, "n") != "n" {
		t.Fatal()
	}
}
