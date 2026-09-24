package api

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func healthServer(t *testing.T, status int, body string) *Client {
	t.Helper()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodGet || r.URL.Path != PathHealth {
			t.Fatalf("unexpected probe %s %s", r.Method, r.URL.Path)
		}
		w.WriteHeader(status)
		w.Write([]byte(body))
	}))
	t.Cleanup(srv.Close)
	c := NewClient(srv.URL, "tok")
	c.HTTP = srv.Client()
	return c
}

func TestCheckAPIVersionMatchingServerPasses(t *testing.T) {
	c := healthServer(t, 200, `{"ok":true,"data":{"ok":true,"surfaces":{},"api_version":1}}`)
	if err := c.CheckAPIVersion(context.Background()); err != nil {
		t.Fatal(err)
	}
}

func TestCheckAPIVersionNewerServerAsksForSelfUpdate(t *testing.T) {
	c := healthServer(t, 200, `{"ok":true,"data":{"api_version":2}}`)
	err := c.CheckAPIVersion(context.Background())
	var verr *APIVersionError
	if !errors.As(err, &verr) || verr.Server != 2 {
		t.Fatalf("want APIVersionError{Server:2}, got %v", err)
	}
	if !strings.Contains(err.Error(), "v2") || !strings.Contains(err.Error(), "capabilities self-update") {
		t.Fatal(err.Error())
	}
}

func TestCheckAPIVersionOlderServerAsksForServerUpgrade(t *testing.T) {
	c := healthServer(t, 200, `{"ok":true,"data":{"api_version":0}}`)
	err := c.CheckAPIVersion(context.Background())
	var verr *APIVersionError
	if !errors.As(err, &verr) || verr.Server != 0 {
		t.Fatalf("want APIVersionError{Server:0}, got %v", err)
	}
	if !strings.Contains(err.Error(), "upgrade rawphp/laravel-capabilities") {
		t.Fatal(err.Error())
	}
}

// Unhealthy surfaces (agent/mcp down) must not block CLI runs — only the wire version matters.
func TestCheckAPIVersionIgnoresSurfaceHealth(t *testing.T) {
	c := healthServer(t, 200, `{"ok":true,"data":{"ok":false,"surfaces":{"mcp":{"status":"missing","enabled":true}},"api_version":1}}`)
	if err := c.CheckAPIVersion(context.Background()); err != nil {
		t.Fatal(err)
	}
}

// Servers that predate api_version, gate health, or return junk are not a mismatch:
// the invoke itself reports those failures.
func TestCheckAPIVersionUnknownIsNotAMismatch(t *testing.T) {
	cases := map[string]struct {
		status int
		body   string
	}{
		"no api_version":  {200, `{"ok":true,"data":{"ok":true,"surfaces":{}}}`},
		"health denied":   {401, `{"ok":false,"error":{"code":"unauthenticated","message":"no"}}`},
		"not found":       {404, `<html>nope</html>`},
		"non-json":        {200, `hello`},
		"data not object": {200, `{"ok":true,"data":[1]}`},
	}
	for name, tc := range cases {
		t.Run(name, func(t *testing.T) {
			c := healthServer(t, tc.status, tc.body)
			if err := c.CheckAPIVersion(context.Background()); err != nil {
				t.Fatal(err)
			}
		})
	}
}

func TestCheckAPIVersionTransportErrorIsNotAMismatch(t *testing.T) {
	c := NewClient("http://127.0.0.1:1", "tok")
	if err := c.CheckAPIVersion(context.Background()); err != nil {
		t.Fatal(err)
	}
}
