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
