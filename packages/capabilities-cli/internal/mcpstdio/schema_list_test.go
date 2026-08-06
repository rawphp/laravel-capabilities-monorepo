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

func TestToolsListNormalizesArrayPropertiesAndVersion(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Empty-DTO style: properties as [] and multi type — confuses MCP hosts.
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[{
			"name":"get_today_meals",
			"description":"read",
			"input_schema":{"type":["object","array"],"properties":[],"additionalProperties":false}
		}]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	in := bytes.NewBufferString(strings.Join([]string{
		`{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}`,
		`{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}`,
	}, "\n") + "\n")
	var out bytes.Buffer
	s := New(c, "t", in, &out)
	s.Version = "9.9.9-test"
	if err := s.Run(context.Background()); err != nil {
		t.Fatal(err)
	}
	lines := strings.Split(strings.TrimSpace(out.String()), "\n")
	if len(lines) != 2 {
		t.Fatalf("lines=%d out=%s", len(lines), out.String())
	}
	var init map[string]any
	if err := json.Unmarshal([]byte(lines[0]), &init); err != nil {
		t.Fatal(err)
	}
	info := init["result"].(map[string]any)["serverInfo"].(map[string]any)
	if info["version"] != "9.9.9-test" {
		t.Fatalf("serverInfo.version=%v", info["version"])
	}
	var list map[string]any
	if err := json.Unmarshal([]byte(lines[1]), &list); err != nil {
		t.Fatal(err)
	}
	tool := list["result"].(map[string]any)["tools"].([]any)[0].(map[string]any)
	schema := tool["inputSchema"].(map[string]any)
	props, ok := schema["properties"].(map[string]any)
	if !ok {
		t.Fatalf("properties not object: %T %v", schema["properties"], schema["properties"])
	}
	if len(props) != 0 {
		t.Fatalf("expected empty properties object, got %v", props)
	}
	if schema["type"] != "object" {
		t.Fatalf("type=%v want object", schema["type"])
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
