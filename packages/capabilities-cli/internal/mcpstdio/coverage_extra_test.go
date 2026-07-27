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

func TestHandleInitializePingUnknown(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(strings.Join([]string{
		`{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}`,
		`{"jsonrpc":"2.0","id":2,"method":"ping","params":{}}`,
		`{"jsonrpc":"2.0","id":3,"method":"nope","params":{}}`,
		`not-json`,
		``,
	}, "\n") + "\n")
	var out bytes.Buffer
	if err := New(c, "t", in, &out).Run(context.Background()); err != nil {
		t.Fatal(err)
	}
	s := out.String()
	if !strings.Contains(s, "protocolVersion") || !strings.Contains(s, "method not found") {
		t.Fatal(s)
	}
}

func TestCallToolParseError(t *testing.T) {
	c := api.NewClient("http://example.invalid", "t")
	s := New(c, "t", bytes.NewBuffer(nil), &bytes.Buffer{})
	_, err := s.callTool(context.Background(), json.RawMessage(`{`))
	if err == nil {
		t.Fatal()
	}
}

func TestListToolsNetworkError(t *testing.T) {
	c := api.NewClient("http://127.0.0.1:1", "t")
	s := New(c, "t", bytes.NewBuffer(nil), &bytes.Buffer{})
	_, err := s.listTools(context.Background())
	if err == nil {
		t.Fatal()
	}
}
