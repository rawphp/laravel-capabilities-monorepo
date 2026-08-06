package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// AcceptJSON is the default Accept header.
const AcceptJSON = "application/json"

// AcceptCLI is the optional CLI-oriented presentation media type (D-009).
// It only affects presentation; it does not change server-derived caller.
const AcceptCLI = "application/vnd.capabilities.cli+json"

// Paths for the single capability HTTP API (D-009) — not a second controller tree.
const (
	PathCapabilities = "/capabilities"
	PathAuthToken    = "/capabilities/auth/token"
	PathAuthDevice   = "/capabilities/auth/device"
	PathHealth       = "/capabilities/health"
	PathApprovals    = "/capabilities/approvals"
)

// Client is a pure HTTP client for the capability API.
// It never embeds domain run() logic.
type Client struct {
	BaseURL    string
	Token      string
	HTTP       *http.Client
	Accept     string
	Timeout    time.Duration
	UserAgent  string
	// ExtraHeaders are optional; must never be used to claim caller authority.
	ExtraHeaders map[string]string
}

// NewClient builds a client with sensible defaults.
func NewClient(baseURL, token string) *Client {
	return &Client{
		BaseURL: strings.TrimRight(baseURL, "/"),
		Token:   token,
		HTTP:    &http.Client{Timeout: 30 * time.Second},
		Accept:  AcceptJSON,
		Timeout: 30 * time.Second,
		UserAgent: "capabilities-cli/0.2",
	}
}

func (c *Client) httpClient() *http.Client {
	if c.HTTP != nil {
		return c.HTTP
	}
	timeout := c.Timeout
	if timeout == 0 {
		timeout = 30 * time.Second
	}
	return &http.Client{Timeout: timeout}
}

func (c *Client) accept() string {
	if c.Accept != "" {
		return c.Accept
	}
	return AcceptJSON
}

// Response is a raw HTTP response with decoded envelope when possible.
type Response struct {
	StatusCode int
	Header     http.Header
	Body       []byte
	Envelope   ErrorEnvelope
	Err        *StructuredError
}

// do performs an HTTP request with auth + Accept headers.
// Intentionally does NOT send X-Capabilities-Caller as authority (D-022).
func (c *Client) do(ctx context.Context, method, path string, body []byte, extra map[string]string) (*Response, error) {
	url := c.BaseURL + path
	var rdr io.Reader
	if body != nil {
		rdr = bytes.NewReader(body)
	}
	req, err := http.NewRequestWithContext(ctx, method, url, rdr)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", c.accept())
	req.Header.Set("User-Agent", c.UserAgent)
	if c.Token != "" {
		req.Header.Set("Authorization", "Bearer "+c.Token)
	}
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	for k, v := range c.ExtraHeaders {
		// Refuse to treat client-claimed caller as authority: strip spoof headers.
		if strings.EqualFold(k, "X-Capabilities-Caller") || strings.EqualFold(k, "X-Caller") {
			continue
		}
		req.Header.Set(k, v)
	}
	for k, v := range extra {
		if strings.EqualFold(k, "X-Capabilities-Caller") || strings.EqualFold(k, "X-Caller") {
			continue
		}
		req.Header.Set(k, v)
	}

	res, err := c.httpClient().Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()
	raw, err := io.ReadAll(res.Body)
	if err != nil {
		return nil, err
	}
	out := &Response{StatusCode: res.StatusCode, Header: res.Header, Body: raw}
	_ = json.Unmarshal(raw, &out.Envelope)
	if !out.Envelope.OK && out.Envelope.Error != nil {
		out.Err = ParseErrorEnvelope(out.Envelope, res.StatusCode, raw)
	} else if res.StatusCode >= 400 && out.Envelope.Error == nil {
		// Non-envelope HTTP error → internal-ish mapping by status.
		// Never surface raw HTML/document bodies as the user-facing message.
		code := codeFromHTTP(res.StatusCode)
		out.Err = &StructuredError{
			Code:       code,
			Message:    humanizeHTTPErrorBody(raw, res.StatusCode),
			HTTPStatus: res.StatusCode,
			ExitCode:   ExitCode(code),
			Body:       raw,
		}
	}
	return out, nil
}

// humanizeHTTPErrorBody turns non-JSON error payloads (HTML login pages, etc.)
// into a short, actionable message instead of dumping the whole document.
func humanizeHTTPErrorBody(raw []byte, status int) string {
	trim := strings.TrimSpace(string(raw))
	if trim == "" {
		return fmt.Sprintf("HTTP %d from capability API", status)
	}
	// Prefer a compact JSON message field when present.
	var probe map[string]any
	if json.Unmarshal(raw, &probe) == nil {
		if m, ok := probe["message"].(string); ok && strings.TrimSpace(m) != "" {
			return strings.TrimSpace(m)
		}
		if errObj, ok := probe["error"].(map[string]any); ok {
			if m, ok := errObj["message"].(string); ok && strings.TrimSpace(m) != "" {
				return strings.TrimSpace(m)
			}
		}
	}
	lower := strings.ToLower(trim)
	if strings.HasPrefix(trim, "<!doctype") || strings.HasPrefix(trim, "<html") ||
		strings.Contains(lower, "<html") || strings.Contains(lower, "<!doctype") {
		return fmt.Sprintf("HTTP %d: non-JSON response from server (HTML page). Check --base-url points at the capability API host, not a marketing site.", status)
	}
	// Cap length so we never dump multi-KB garbage into the terminal.
	const max = 200
	if len(trim) > max {
		return trim[:max] + "…"
	}
	return trim
}

func codeFromHTTP(status int) string {
	switch status {
	case 401:
		return CodeUnauthenticated
	case 403:
		return CodeForbidden
	case 404:
		return CodeNotFound
	case 409:
		return CodeConflict
	case 422:
		return CodeValidationFailed
	case 429:
		return CodeRateLimited
	default:
		return CodeInternal
	}
}

// ListCapabilities GET /capabilities (compact catalog rows; may omit schemas).
func (c *Client) ListCapabilities(ctx context.Context) (*Response, error) {
	return c.do(ctx, http.MethodGet, PathCapabilities, nil, nil)
}

// ListCapabilitiesWithSchemas GET /capabilities?include_schemas=1
// Used by catalog --include-schemas and agent one-shot discovery.
func (c *Client) ListCapabilitiesWithSchemas(ctx context.Context) (*Response, error) {
	return c.do(ctx, http.MethodGet, PathCapabilities+"?include_schemas=1", nil, nil)
}

// DescribeCapability GET /capabilities/{name}
func (c *Client) DescribeCapability(ctx context.Context, name string) (*Response, error) {
	return c.do(ctx, http.MethodGet, PathCapabilities+"/"+name, nil, nil)
}

// InvokeCapability POST /capabilities/{name} with Idempotency-Key.
// key must be non-empty for mutating runs (CLI always sends — D-005).
func (c *Client) InvokeCapability(ctx context.Context, name string, input json.RawMessage, idempotencyKey string) (*Response, error) {
	if idempotencyKey == "" {
		return nil, fmt.Errorf("idempotency key required on invoke")
	}
	// Body is the input object only (server derives caller).
	body := input
	if body == nil {
		body = json.RawMessage(`{}`)
	}
	return c.do(ctx, http.MethodPost, PathCapabilities+"/"+name, body, map[string]string{
		"Idempotency-Key": idempotencyKey,
	})
}

// AcceptApproval POST /capabilities/approvals/{id}/accept
func (c *Client) AcceptApproval(ctx context.Context, id string) (*Response, error) {
	return c.do(ctx, http.MethodPost, PathApprovals+"/"+id+"/accept", []byte(`{}`), nil)
}

// RejectApproval POST /capabilities/approvals/{id}/reject
func (c *Client) RejectApproval(ctx context.Context, id string) (*Response, error) {
	return c.do(ctx, http.MethodPost, PathApprovals+"/"+id+"/reject", []byte(`{}`), nil)
}

// Health GET /capabilities/health
func (c *Client) Health(ctx context.Context) (*Response, error) {
	return c.do(ctx, http.MethodGet, PathHealth, nil, nil)
}

// LoginDevice POST /capabilities/auth/device
func (c *Client) LoginDevice(ctx context.Context, payload map[string]any) (*Response, error) {
	b, _ := json.Marshal(payload)
	return c.do(ctx, http.MethodPost, PathAuthDevice, b, nil)
}

// LoginToken POST /capabilities/auth/token
func (c *Client) LoginToken(ctx context.Context, payload map[string]any) (*Response, error) {
	b, _ := json.Marshal(payload)
	return c.do(ctx, http.MethodPost, PathAuthToken, b, nil)
}

// IsHTTPOnlyClient documents that this package is an HTTP client only.
func IsHTTPOnlyClient() bool { return true }

// SpoofCallerHeader is never sent as authority; returns empty.
func SpoofCallerHeader() string { return "" }
