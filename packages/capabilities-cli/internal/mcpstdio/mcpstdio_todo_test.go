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

func TestMcpstdioproxiestoremotehttpwithstoredtoken(t *testing.T) {
	var auth string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		auth = r.Header.Get("Authorization")
		if r.URL.Path == "/capabilities" {
			w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"create-invoice","description":"d"}]}}`))
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"invoice_id":1}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "stored-tok")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(`{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}` + "\n")
	var out bytes.Buffer
	s := New(c, "stored-tok", in, &out)
	if err := s.Run(context.Background()); err != nil {
		t.Fatal(err)
	}
	if auth != "Bearer stored-tok" {
		t.Fatal(auth)
	}
	if !strings.Contains(out.String(), "create-invoice") {
		t.Fatal(out.String())
	}
}

func TestMcpstdionolocaldomainrun(t *testing.T) {
	if HasLocalRun() {
		t.Fatal()
	}
}

func TestMcpstdiousessameauthascli(t *testing.T) {
	if !UsesStoredTokenOnly() {
		t.Fatal()
	}
}

func TestMcpstdiodoesnotbypassserverauthorization(t *testing.T) {
	if HasLocalAuthorize() {
		t.Fatal()
	}
}

func TestMcpstdioprofiletoolscomefromserver(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"from-server"}]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(`{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}` + "\n")
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	if !strings.Contains(out.String(), "from-server") {
		t.Fatal(out.String())
	}
}

func TestMcpstdioforwardsidempotencykeys(t *testing.T) {
	var key string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			key = r.Header.Get("Idempotency-Key")
		}
		w.Write([]byte(`{"ok":true,"data":{}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	params, _ := json.Marshal(map[string]any{"name": "x", "arguments": map[string]any{"a": 1}})
	line, _ := json.Marshal(map[string]any{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": json.RawMessage(params)})
	in := bytes.NewBuffer(append(line, '\n'))
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	if key == "" {
		t.Fatal("idempotency key not forwarded")
	}
}
