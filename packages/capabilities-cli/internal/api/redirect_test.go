package api

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

// The capability API has no legitimate redirects. Following one downgrades a
// POST invoke to a GET, which hits the describe route and returns an ok
// envelope: a silent false success. Any 3xx must surface as an error.
func TestInvokeDoesNotFollowRedirect(t *testing.T) {
	var methods []string
	srv, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		methods = append(methods, r.Method)
		if r.URL.Path == "/moved/capabilities/billing.charge" {
			_, _ = w.Write([]byte(`{"ok":true,"data":{"name":"billing.charge"}}`))
			return
		}
		http.Redirect(w, r, "/moved"+r.URL.Path, http.StatusMovedPermanently)
	})
	res, err := c.InvokeCapability(context.Background(), "billing.charge", nil, "k")
	if err != nil {
		t.Fatal(err)
	}
	if len(methods) != 1 || methods[0] != http.MethodPost {
		t.Fatalf("redirect followed: %v", methods)
	}
	if res.Err == nil || res.Err.Code != CodeInternal || res.Err.ExitCode != ExitInternal || res.Err.HTTPStatus != http.StatusMovedPermanently {
		t.Fatalf("expected redirect error, got %+v", res.Err)
	}
	want := srv.URL + "/moved/capabilities/billing.charge"
	if !strings.Contains(res.Err.Message, want) || !strings.Contains(res.Err.Message, "--base-url") {
		t.Fatalf("message should name Location and --base-url: %q", res.Err.Message)
	}
}

// The policy holds for the default client too, not only injected ones.
func TestDefaultClientDoesNotFollowRedirect(t *testing.T) {
	hits := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		hits++
		http.Redirect(w, r, "https://elsewhere.example/capabilities", http.StatusFound)
	}))
	t.Cleanup(srv.Close)
	for name, c := range map[string]*Client{
		"NewClient": NewClient(srv.URL, "t"),
		"nil HTTP":  {BaseURL: srv.URL},
	} {
		t.Run(name, func(t *testing.T) {
			hits = 0
			res, err := c.ListCapabilities(context.Background())
			if err != nil {
				t.Fatal(err)
			}
			if hits != 1 || res.Err == nil || res.Err.HTTPStatus != http.StatusFound {
				t.Fatalf("hits=%d err=%+v", hits, res.Err)
			}
			if !strings.Contains(res.Err.Message, "https://elsewhere.example/capabilities") {
				t.Fatalf("message: %q", res.Err.Message)
			}
		})
	}
}

func TestRedirectWithoutLocationStillErrors(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNotModified)
	})
	res, err := c.ListCapabilities(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeInternal || res.Err.HTTPStatus != http.StatusNotModified {
		t.Fatalf("got %+v", res.Err)
	}
}
