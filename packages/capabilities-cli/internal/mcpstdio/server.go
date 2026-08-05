// Package mcpstdio is an optional MCP stdio bridge that proxies to the remote
// capability HTTP API with the stored CLI token. No local domain run().
package mcpstdio

import (
	"bufio"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"strings"

	"github.com/google/uuid"
	"github.com/rawphp/capabilities-cli/internal/api"
)

// Server speaks a minimal MCP-ish JSON-RPC over stdio and proxies tools to HTTP.
type Server struct {
	Client *api.Client
	// Token is the stored CLI token (same auth as capabilities run).
	Token string
	In    io.Reader
	Out   io.Writer
	// RejectHostActor when true (default) strips host-injected actor fields.
	RejectHostActor bool
}

// New constructs a server with safe defaults.
func New(client *api.Client, token string, in io.Reader, out io.Writer) *Server {
	return &Server{
		Client:          client,
		Token:           token,
		In:              in,
		Out:             out,
		RejectHostActor: true,
	}
}

// HasLocalRun always false — domain run is server-only.
func HasLocalRun() bool { return false }

// HasLocalAuthorize always false — authorization is server-only.
func HasLocalAuthorize() bool { return false }

// UsesStoredTokenOnly documents auth source.
func UsesStoredTokenOnly() bool { return true }

// rpcRequest is a JSON-RPC 2.0 request.
type rpcRequest struct {
	JSONRPC string          `json:"jsonrpc"`
	ID      any             `json:"id"`
	Method  string          `json:"method"`
	Params  json.RawMessage `json:"params"`
}

type rpcResponse struct {
	JSONRPC string `json:"jsonrpc"`
	ID      any    `json:"id"`
	Result  any    `json:"result,omitempty"`
	Error   *struct {
		Code    int    `json:"code"`
		Message string `json:"message"`
		Data    any    `json:"data,omitempty"`
	} `json:"error,omitempty"`
}

// Run serves until stdin EOF.
func (s *Server) Run(ctx context.Context) error {
	if s.Client != nil && s.Token != "" {
		s.Client.Token = s.Token
	}
	sc := bufio.NewScanner(s.In)
	// Allow large tool payloads.
	buf := make([]byte, 0, 64*1024)
	sc.Buffer(buf, 10*1024*1024)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" {
			continue
		}
		var req rpcRequest
		if err := json.Unmarshal([]byte(line), &req); err != nil {
			_ = s.write(rpcResponse{
				JSONRPC: "2.0",
				ID:      nil,
				Error: &struct {
					Code    int    `json:"code"`
					Message string `json:"message"`
					Data    any    `json:"data,omitempty"`
				}{Code: -32700, Message: "parse error"},
			})
			continue
		}
		// MCP notifications (methods under notifications/*) must not get a
		// JSON-RPC error response — clients send initialized without expecting a reply.
		if strings.HasPrefix(req.Method, "notifications/") {
			continue
		}
		resp := s.handle(ctx, req)
		if err := s.write(resp); err != nil {
			return err
		}
	}
	return sc.Err()
}

func (s *Server) write(resp rpcResponse) error {
	b, err := json.Marshal(resp)
	if err != nil {
		return err
	}
	_, err = fmt.Fprintln(s.Out, string(b))
	return err
}

func (s *Server) handle(ctx context.Context, req rpcRequest) rpcResponse {
	resp := rpcResponse{JSONRPC: "2.0", ID: req.ID}
	switch req.Method {
	case "initialize":
		resp.Result = map[string]any{
			"protocolVersion": "2024-11-05",
			"capabilities":    map[string]any{"tools": map[string]any{}},
			"serverInfo":      map[string]any{"name": "capabilities", "version": "0.2.0"},
		}
	case "tools/list":
		tools, err := s.listTools(ctx)
		if err != nil {
			resp.Error = rpcErr(err)
		} else {
			resp.Result = map[string]any{"tools": tools}
		}
	case "tools/call":
		result, err := s.callTool(ctx, req.Params)
		if err != nil {
			resp.Error = rpcErr(err)
			// Propagate structured server errors / approval_required in data
			// using wire-shaped keys (not Go field names like "Code").
			if se, ok := err.(*api.StructuredError); ok {
				resp.Error.Data = se.PublicData()
			}
		} else {
			resp.Result = result
		}
	case "ping":
		resp.Result = map[string]any{}
	default:
		resp.Error = &struct {
			Code    int    `json:"code"`
			Message string `json:"message"`
			Data    any    `json:"data,omitempty"`
		}{Code: -32601, Message: "method not found: " + req.Method}
	}
	return resp
}

func rpcErr(err error) *struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
	Data    any    `json:"data,omitempty"`
} {
	return &struct {
		Code    int    `json:"code"`
		Message string `json:"message"`
		Data    any    `json:"data,omitempty"`
	}{Code: -32000, Message: err.Error()}
}

func (s *Server) listTools(ctx context.Context) ([]map[string]any, error) {
	// Prefer include_schemas=1 so agents get JSON Schema on tools/list.
	// Compact list responses omit input_schema and leave agents guessing.
	res, err := s.Client.ListCapabilitiesWithSchemas(ctx)
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	// Tools come from the server catalog — never a local domain registry.
	var tools []map[string]any
	var payload map[string]any
	_ = json.Unmarshal(res.Body, &payload)
	// Best-effort parse of data.capabilities[]
	if data, ok := payload["data"].(map[string]any); ok {
		if caps, ok := data["capabilities"].([]any); ok {
			for _, c := range caps {
				if m, ok := c.(map[string]any); ok {
					name, _ := m["name"].(string)
					desc, _ := m["description"].(string)
					schema := m["input_schema"]
					// MCP clients treat null inputSchema as "no parameters known".
					// Always emit an object schema (empty when server omitted it).
					if schema == nil {
						schema = map[string]any{"type": "object", "properties": map[string]any{}}
					}
					tools = append(tools, map[string]any{
						"name":        name,
						"description": desc,
						"inputSchema": schema,
					})
				}
			}
		}
	}
	return tools, nil
}

func (s *Server) callTool(ctx context.Context, params json.RawMessage) (any, error) {
	var p struct {
		Name      string         `json:"name"`
		Arguments map[string]any `json:"arguments"`
	}
	if err := json.Unmarshal(params, &p); err != nil {
		return nil, err
	}
	// Strip host-injected actor/caller — never authoritative on client.
	if s.RejectHostActor && p.Arguments != nil {
		for _, k := range []string{"caller", "actor", "user_id", "actor_id", "auth_profile", "client_id"} {
			delete(p.Arguments, k)
		}
	}
	body, _ := json.Marshal(p.Arguments)
	key := uuid.NewString()
	res, err := s.Client.InvokeCapability(ctx, p.Name, body, key)
	if err != nil {
		return nil, err
	}
	if res.Err != nil {
		return nil, res.Err
	}
	return map[string]any{
		"content": []map[string]any{
			{"type": "text", "text": string(res.Body)},
		},
		"isError": false,
	}, nil
}

// Principles documents security posture for tests.
const Principles = `MCP stdio proxies to remote HTTP with stored token.
No local authorize, no local domain run.
Does not accept host-injected actor as authority.
Does not bypass server profile / authorization.
Propagates server errors including approval_required.
Forwards Idempotency-Key on every tools/call.
`
