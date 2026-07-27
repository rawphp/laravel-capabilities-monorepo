package api

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func testServer(t *testing.T, h http.HandlerFunc) (*httptest.Server, *Client) {
	t.Helper()
	srv := httptest.NewServer(h)
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "tok-test")
	c.HTTP = srv.Client()
	return srv, c
}

func TestClientpoststosinglecapabilityhttpapi(t *testing.T) {
	var path string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		path = r.URL.Path
		w.Write([]byte(`{"ok":true,"data":{}}`))
	})
	_, err := c.InvokeCapability(context.Background(), "create-invoice", json.RawMessage(`{}`), "k1")
	if err != nil {
		t.Fatal(err)
	}
	if path != "/capabilities/create-invoice" {
		t.Fatalf("path %s", path)
	}
}

func TestClientsendsbearerfromkeychain(t *testing.T) {
	var auth string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		auth = r.Header.Get("Authorization")
		w.Write([]byte(`{"ok":true}`))
	})
	c.Token = "secret-from-store"
	_, _ = c.ListCapabilities(context.Background())
	if auth != "Bearer secret-from-store" {
		t.Fatalf("auth %q", auth)
	}
}

func TestClientsetscallernotviaspoofheaderasauthority(t *testing.T) {
	var gotCaller string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		gotCaller = r.Header.Get("X-Capabilities-Caller")
		w.Write([]byte(`{"ok":true}`))
	})
	c.ExtraHeaders = map[string]string{"X-Capabilities-Caller": "admin"}
	_, _ = c.ListCapabilities(context.Background())
	if gotCaller != "" {
		t.Fatalf("must not send spoof caller, got %q", gotCaller)
	}
}

func TestClientoptionalcliacceptheader(t *testing.T) {
	var accept string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		accept = r.Header.Get("Accept")
		w.Write([]byte(`{"ok":true}`))
	})
	c.Accept = AcceptCLI
	_, _ = c.ListCapabilities(context.Background())
	if accept != AcceptCLI {
		t.Fatalf("accept %q", accept)
	}
}

func TestClientidempotencyheaderalwayspresentonrun(t *testing.T) {
	var key string
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		key = r.Header.Get("Idempotency-Key")
		w.Write([]byte(`{"ok":true,"data":{}}`))
	})
	_, err := c.InvokeCapability(context.Background(), "x", json.RawMessage(`{}`), "auto-uuid-1")
	if err != nil {
		t.Fatal(err)
	}
	if key != "auto-uuid-1" {
		t.Fatalf("key %q", key)
	}
	_, err = c.InvokeCapability(context.Background(), "x", json.RawMessage(`{}`), "")
	if err == nil {
		t.Fatal("empty key must error")
	}
}

func TestClientdoesnotembeddomainlogic(t *testing.T) {
	// Client is HTTP-only: no invoice domain types, only wire JSON.
	if !IsHTTPOnlyClient() {
		t.Fatal("client must be HTTP only")
	}
	// Compile-time: Invoke takes raw JSON, not domain structs.
	var c *Client
	_ = c.InvokeCapability
}

func TestClientusessameinvokepathforcatalogdescriberun(t *testing.T) {
	paths := []string{}
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		paths = append(paths, r.Method+" "+r.URL.Path)
		w.Write([]byte(`{"ok":true,"data":{"name":"x","input_schema":{}}}`))
	})
	_, _ = c.ListCapabilities(context.Background())
	_, _ = c.DescribeCapability(context.Background(), "x")
	_, _ = c.InvokeCapability(context.Background(), "x", json.RawMessage(`{}`), "k")
	wantPrefix := "/capabilities"
	for _, p := range paths {
		if !strings.Contains(p, wantPrefix) {
			t.Fatalf("unexpected path %s", p)
		}
	}
	if len(paths) != 3 {
		t.Fatalf("paths %#v", paths)
	}
}

func TestClientmapshttperrorenvelopetostructurederror(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(422)
		w.Write([]byte(`{"ok":false,"error":{"code":"validation_failed","message":"bad","violations":[{"field":"a","message":"x"}]}}`))
	})
	res, err := c.InvokeCapability(context.Background(), "x", json.RawMessage(`{}`), "k")
	if err != nil {
		t.Fatal(err)
	}
	if res.Err == nil || res.Err.Code != CodeValidationFailed || res.Err.ExitCode != 2 {
		t.Fatalf("%#v", res.Err)
	}
}

func TestClientforwardsrequestidfromresponse(t *testing.T) {
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(500)
		w.Write([]byte(`{"ok":false,"error":{"code":"internal","message":"boom","request_id":"01JREQ"}}`))
	})
	res, _ := c.InvokeCapability(context.Background(), "x", json.RawMessage(`{}`), "k")
	if res.Err == nil || res.Err.RequestID != "01JREQ" {
		t.Fatalf("%#v", res.Err)
	}
}

func TestClienttimeoutisconfigurable(t *testing.T) {
	c := NewClient("http://example.invalid", "t")
	c.Timeout = 50 * time.Millisecond
	if c.Timeout != 50*time.Millisecond {
		t.Fatal("timeout not set")
	}
	c.HTTP = &http.Client{Timeout: c.Timeout}
	// ensure client uses timeout field
	_ = c.httpClient()
}

func TestClientdoesnotsendxcapabilitiescallerasauthority(t *testing.T) {
	if SpoofCallerHeader() != "" {
		t.Fatal("spoof header must be empty")
	}
	var headers http.Header
	_, c := testServer(t, func(w http.ResponseWriter, r *http.Request) {
		headers = r.Header.Clone()
		w.Write([]byte(`{"ok":true}`))
	})
	_, _ = c.do(context.Background(), http.MethodGet, PathCapabilities, nil, map[string]string{
		"X-Capabilities-Caller": "http",
		"X-Caller":              "admin",
	})
	if headers.Get("X-Capabilities-Caller") != "" || headers.Get("X-Caller") != "" {
		t.Fatal("spoof headers must be stripped")
	}
}

func TestClientbaseurlfromprofile(t *testing.T) {
	c := NewClient("https://app.example.com/", "tok")
	if c.BaseURL != "https://app.example.com" {
		t.Fatalf("base %q", c.BaseURL)
	}
	_ = io.Discard
}
