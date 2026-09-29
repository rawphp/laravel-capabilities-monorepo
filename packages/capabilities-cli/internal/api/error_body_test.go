package api

import (
	"context"
	"net/http"
	"strings"
	"testing"
)

func TestNonJSONHTMLErrorIsHumanized(t *testing.T) {
	html := `<!doctype html><html><head><title>Example Domain</title></head><body><h1>Example Domain</h1></body></html>`
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotFound)
		_, _ = w.Write([]byte(html))
	})
	res, err := c.ListCapabilities(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil {
		t.Fatal("expected structured error")
	}
	if strings.Contains(res.Err.Message, "<!doctype") || strings.Contains(res.Err.Message, "<html") {
		t.Fatalf("raw HTML leaked: %q", res.Err.Message)
	}
	if !strings.Contains(res.Err.Message, "non-JSON") && !strings.Contains(res.Err.Message, "HTML") {
		t.Fatalf("message not helpful: %q", res.Err.Message)
	}
}

func TestHumanizeHTTPErrorBodyJSONMessage(t *testing.T) {
	msg := humanizeHTTPErrorBody([]byte(`{"message":"Unauthenticated."}`), 401)
	if msg != "Unauthenticated." {
		t.Fatal(msg)
	}
}

func TestListCapabilitiesWithSchemasQuery(t *testing.T) {
	var rawQuery string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		rawQuery = r.URL.RawQuery
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	})
	_, err := c.ListCapabilitiesWithSchemas(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if rawQuery != "include_schemas=1" {
		t.Fatalf("query %q", rawQuery)
	}
}

func TestClientNonEnvelopeHTTPError(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(403)
		w.Write([]byte("forbidden plain"))
	})
	res, err := c.ListCapabilities(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeForbidden || res.Err.Message != "forbidden plain" {
		t.Fatalf("%#v", res.Err)
	}
}

func TestHumanizeHTTPErrorBodyEmptyAndLongBodies(t *testing.T) {
	if got := humanizeHTTPErrorBody([]byte("  "), 502); got != "HTTP 502 from capability API" {
		t.Fatal(got)
	}
	long := strings.Repeat("x", 500)
	got := humanizeHTTPErrorBody([]byte(long), 500)
	if got != strings.Repeat("x", 200)+"…" {
		t.Fatalf("len %d: %q", len(got), got)
	}
}
