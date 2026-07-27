package mcpstdio

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/rawphp/capabilities-cli/internal/api"
)

func TestNolocalauthorize(t *testing.T) {
	if HasLocalAuthorize() {
		t.Fatal()
	}
	if !strings.Contains(Principles, "No local authorize") {
		t.Fatal(Principles)
	}
}

func TestNolocalrun(t *testing.T) {
	if HasLocalRun() {
		t.Fatal()
	}
}

func TestUsesstoredtokenonly(t *testing.T) {
	if !UsesStoredTokenOnly() {
		t.Fatal()
	}
}

func TestDoesnotaccepthostinjectedactor(t *testing.T) {
	var body []byte
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			buf := make([]byte, 4096)
			n, _ := r.Body.Read(buf)
			body = buf[:n]
		}
		w.Write([]byte(`{"ok":true,"data":{}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	params, _ := json.Marshal(map[string]any{
		"name": "x",
		"arguments": map[string]any{
			"customer_id": 1,
			"caller":      "admin",
			"actor":       "spoof",
		},
	})
	line, _ := json.Marshal(map[string]any{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": json.RawMessage(params)})
	in := bytes.NewBuffer(append(line, '\n'))
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	if strings.Contains(string(body), `"caller"`) || strings.Contains(string(body), `"actor"`) {
		t.Fatalf("host actor leaked: %s", body)
	}
}

func TestDoesnotbypassserverprofile(t *testing.T) {
	if !strings.Contains(Principles, "server profile") {
		t.Fatal()
	}
}

func TestPropagatesservererrors(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(403)
		w.Write([]byte(`{"ok":false,"error":{"code":"forbidden","message":"nope"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	params, _ := json.Marshal(map[string]any{"name": "x", "arguments": map[string]any{}})
	line, _ := json.Marshal(map[string]any{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": json.RawMessage(params)})
	in := bytes.NewBuffer(append(line, '\n'))
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	if !strings.Contains(out.String(), "error") {
		t.Fatal(out.String())
	}
}

func TestPropagatesapprovalrequired(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(202)
		w.Write([]byte(`{"ok":false,"error":{"code":"approval_required","message":"need"}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	params, _ := json.Marshal(map[string]any{"name": "x", "arguments": map[string]any{}})
	line, _ := json.Marshal(map[string]any{"jsonrpc": "2.0", "id": 2, "method": "tools/call", "params": json.RawMessage(params)})
	in := bytes.NewBuffer(append(line, '\n'))
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	if !strings.Contains(out.String(), "approval_required") && !strings.Contains(out.String(), "need") {
		t.Fatal(out.String())
	}
}
