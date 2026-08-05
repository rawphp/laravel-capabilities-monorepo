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

func TestToolsListRequestsIncludeSchemasAndEmitsInputSchema(t *testing.T) {
	var sawQuery string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		sawQuery = r.URL.RawQuery
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[
			{"name":"create-invoice","description":"d","input_schema":{"type":"object","required":["customer_id"],"properties":{"customer_id":{"type":"integer"}}}}
		]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(`{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}` + "\n")
	var out bytes.Buffer
	if err := New(c, "t", in, &out).Run(context.Background()); err != nil {
		t.Fatal(err)
	}
	if sawQuery != "include_schemas=1" {
		t.Fatalf("expected include_schemas=1, got %q", sawQuery)
	}
	if !strings.Contains(out.String(), "customer_id") {
		t.Fatal(out.String())
	}
	if !strings.Contains(out.String(), "inputSchema") {
		t.Fatal(out.String())
	}
}

func TestToolsListNullSchemaBecomesEmptyObject(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Compact catalog: no input_schema field.
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{"name":"x","description":"d"}]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(`{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}` + "\n")
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	var resp map[string]any
	if err := json.Unmarshal([]byte(strings.TrimSpace(out.String())), &resp); err != nil {
		t.Fatal(err, out.String())
	}
	result := resp["result"].(map[string]any)
	tools := result["tools"].([]any)
	tool := tools[0].(map[string]any)
	if tool["inputSchema"] == nil {
		t.Fatal("inputSchema still null")
	}
	schema := tool["inputSchema"].(map[string]any)
	if schema["type"] != "object" {
		t.Fatalf("%v", schema)
	}
}

func TestNotificationsInitializedSilent(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(strings.Join([]string{
		`{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}`,
		`{"jsonrpc":"2.0","method":"notifications/initialized"}`,
		`{"jsonrpc":"2.0","id":2,"method":"ping","params":{}}`,
	}, "\n") + "\n")
	var out bytes.Buffer
	if err := New(c, "t", in, &out).Run(context.Background()); err != nil {
		t.Fatal(err)
	}
	if strings.Contains(out.String(), "notifications/initialized") || strings.Contains(out.String(), "method not found: notifications") {
		t.Fatal(out.String())
	}
	// initialize + ping only
	lines := strings.Split(strings.TrimSpace(out.String()), "\n")
	if len(lines) != 2 {
		t.Fatalf("lines=%d out=%q", len(lines), out.String())
	}
}
