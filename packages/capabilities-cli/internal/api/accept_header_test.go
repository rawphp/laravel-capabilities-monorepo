package api

import (
	"context"
	"net/http"
	"testing"
)

func TestDefaultacceptjson(t *testing.T) {
	var accept string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		accept = r.Header.Get("Accept")
		w.Write([]byte(`{"ok":true}`))
	})
	_, _ = c.ListCapabilities(context.Background())
	if accept != AcceptJSON {
		t.Fatalf("got %q", accept)
	}
}

func TestOptionalclivendoraccept(t *testing.T) {
	c := NewClient("http://x", "t")
	c.Accept = AcceptCLI
	if c.accept() != AcceptCLI {
		t.Fatal(c.accept())
	}
}

func TestVendoracceptdoesnotchangeservercaller(t *testing.T) {
	// Presentation only — client still strips spoof caller headers.
	var caller string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		caller = r.Header.Get("X-Capabilities-Caller")
		w.Write([]byte(`{"ok":true}`))
	})
	c.Accept = AcceptCLI
	c.ExtraHeaders = map[string]string{"X-Capabilities-Caller": "cli"}
	_, _ = c.ListCapabilities(context.Background())
	if caller != "" {
		t.Fatal("caller must not be client-set")
	}
}

func TestVendoracceptonlyaffectspresentation(t *testing.T) {
	if AcceptCLI == AcceptJSON {
		t.Fatal("vendor accept is distinct media type")
	}
	if !IsHTTPOnlyClient() {
		t.Fatal("still HTTP only")
	}
}
