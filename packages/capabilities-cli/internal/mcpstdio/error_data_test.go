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

func TestToolsCallErrorDataUsesWireKeys(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodPost {
			w.WriteHeader(http.StatusUnprocessableEntity)
			w.Write([]byte(`{"ok":false,"error":{"code":"validation_failed","message":"JSON Schema validation failed.","violations":[{"field":"date","message":"required property missing"}],"approval_id":null,"retryable":false}}`))
			return
		}
		w.Write([]byte(`{"ok":true,"data":{"capabilities":[]}}`))
	}))
	t.Cleanup(srv.Close)
	c := api.NewClient(srv.URL, "t")
	c.HTTP = srv.Client()
	params, _ := json.Marshal(map[string]any{"name": "get_meals_by_date", "arguments": map[string]any{}})
	line, _ := json.Marshal(map[string]any{"jsonrpc": "2.0", "id": 1, "method": "tools/call", "params": json.RawMessage(params)})
	in := bytes.NewBuffer(append(line, '\n'))
	var out bytes.Buffer
	_ = New(c, "t", in, &out).Run(context.Background())
	s := out.String()
	if strings.Contains(s, `"Code"`) || strings.Contains(s, `"HTTPStatus"`) {
		t.Fatalf("Go field names leaked: %s", s)
	}
	if !strings.Contains(s, `"code":"validation_failed"`) && !strings.Contains(s, `"code": "validation_failed"`) {
		// compact marshal has no spaces
		if !strings.Contains(s, "validation_failed") {
			t.Fatal(s)
		}
	}
	if !strings.Contains(s, "violations") || !strings.Contains(s, "date") {
		t.Fatal(s)
	}
}
