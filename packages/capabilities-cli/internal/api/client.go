package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"
	"unicode"
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
	BaseURL   string
	Token     string
	HTTP      *http.Client
	Accept    string
	Timeout   time.Duration
	UserAgent string
	// ExtraHeaders are optional; must never be used to claim caller authority.
	ExtraHeaders map[string]string
}

// NewClient builds a client with sensible defaults.
func NewClient(baseURL, token string) *Client {
	return &Client{
		BaseURL:   strings.TrimRight(baseURL, "/"),
		Token:     token,
		HTTP:      &http.Client{Timeout: 30 * time.Second, CheckRedirect: noRedirect},
		Accept:    AcceptJSON,
		Timeout:   30 * time.Second,
		UserAgent: "capabilities-cli/0.2",
	}
}

// httpClient returns the transport client with redirects disabled. The
// capability API has no legitimate redirects, and following one would replay
// a POST invoke as a GET (the describe route): a false success. The policy is
// applied to injected clients too, so no caller can opt out by accident.
func (c *Client) httpClient() *http.Client {
	if c.HTTP != nil {
		hc := *c.HTTP
		hc.CheckRedirect = noRedirect
		return &hc
	}
	timeout := c.Timeout
	if timeout == 0 {
		timeout = 30 * time.Second
	}
	return &http.Client{Timeout: timeout, CheckRedirect: noRedirect}
}

func noRedirect(*http.Request, []*http.Request) error { return http.ErrUseLastResponse }

// redirectMessage names the redirect target so the user can fix --base-url.
func redirectMessage(res *http.Response) string {
	if loc, err := res.Location(); err == nil {
		return fmt.Sprintf("HTTP %d: capability API redirected to %s; redirects are not followed. Re-run auth login with --base-url set to that scheme and host", res.StatusCode, loc)
	}
	return fmt.Sprintf("HTTP %d: unexpected redirect from capability API; check --base-url", res.StatusCode)
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
	if res.StatusCode >= 300 && res.StatusCode < 400 {
		out.Err = &StructuredError{
			Code:       CodeInternal,
			Message:    redirectMessage(res),
			HTTPStatus: res.StatusCode,
			ExitCode:   ExitCode(CodeInternal),
			Body:       raw,
		}
	} else if !out.Envelope.OK && out.Envelope.Error != nil {
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
	if out.Err != nil && res.StatusCode == http.StatusTooManyRequests {
		// Header wins over any envelope retry_after: it is the transport's own signal.
		if secs := ParseRetryAfter(res.Header.Get("Retry-After"), time.Now()); secs > 0 {
			out.Err.RetryAfter = secs
		}
	}
	return out, nil
}

// ParseRetryAfter reads an RFC 9110 Retry-After value (delta-seconds or
// HTTP-date) as whole seconds from now, rounded up. Returns 0 when absent,
// malformed, or already past.
func ParseRetryAfter(value string, now time.Time) int {
	value = strings.TrimSpace(value)
	if value == "" {
		return 0
	}
	if n, err := strconv.Atoi(value); err == nil {
		return max(n, 0)
	}
	at, err := http.ParseTime(value)
	if err != nil {
		return 0
	}
	wait := at.Sub(now)
	if wait <= 0 {
		return 0
	}
	return int((wait + time.Second - 1) / time.Second)
}

// humanizeHTTPErrorBody turns non-JSON error payloads (HTML login pages, etc.)
// into a short, actionable message instead of dumping the whole document.
func humanizeHTTPErrorBody(raw []byte, status int) string {
	trim := strings.TrimSpace(string(raw))
	if trim == "" {
		return fmt.Sprintf("HTTP %d from capability API", status)
	}
	// Prefer a compact JSON message field when present. (A body with an
	// "error" object never gets here: do parses it as an envelope error.)
	var probe map[string]any
	if json.Unmarshal(raw, &probe) == nil {
		if m, ok := probe["message"].(string); ok && strings.TrimSpace(m) != "" {
			return strings.TrimSpace(m)
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
	case 410:
		return CodeGone
	case 422:
		return CodeValidationFailed
	case 429:
		return CodeRateLimited
	default:
		return CodeInternal
	}
}

// CheckPathSegment refuses a user- or catalog-supplied value that is not one
// safe URL path segment (C-401). Joined unchecked, `approvals/<id>/accept` as a
// capability name reaches the approval accept route. Percent is refused too:
// the server decodes the path before matching, so %2F is still a separator.
// Returns a validation_failed error (exit 2), or nil when the value is safe.
func CheckPathSegment(kind, s string) *StructuredError {
	bad := s == "" || s == "." || s == ".."
	for _, r := range s {
		if strings.ContainsRune(`/\?#%`, r) || unicode.IsSpace(r) || unicode.IsControl(r) {
			bad = true
			break
		}
	}
	if !bad {
		return nil
	}
	return &StructuredError{
		Code:     CodeValidationFailed,
		Message:  fmt.Sprintf("invalid %s %q: must be a single path segment (no '/', '\\', '%%', '?', '#', whitespace or control characters, and not '.' or '..')", kind, s),
		ExitCode: ExitValidation,
	}
}

// segmentPath joins prefix + "/" + seg + suffix after CheckPathSegment, so no
// caller can put an unchecked value into a request path.
func segmentPath(prefix, kind, seg, suffix string) (string, error) {
	if se := CheckPathSegment(kind, seg); se != nil {
		return "", se
	}
	return prefix + "/" + seg + suffix, nil
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
	path, err := segmentPath(PathCapabilities, "capability name", name, "")
	if err != nil {
		return nil, err
	}
	return c.do(ctx, http.MethodGet, path, nil, nil)
}

// InvokeCapability POST /capabilities/{name} with Idempotency-Key.
// key must be non-empty for mutating runs (CLI always sends — D-005).
func (c *Client) InvokeCapability(ctx context.Context, name string, input json.RawMessage, idempotencyKey string) (*Response, error) {
	path, err := segmentPath(PathCapabilities, "capability name", name, "")
	if err != nil {
		return nil, err
	}
	if idempotencyKey == "" {
		return nil, fmt.Errorf("idempotency key required on invoke")
	}
	// Body is the input object only (server derives caller).
	body := input
	if body == nil {
		body = json.RawMessage(`{}`)
	}
	res, err := c.do(ctx, http.MethodPost, path, body, map[string]string{
		"Idempotency-Key": idempotencyKey,
	})
	if err != nil {
		return nil, err
	}
	// Fail closed: an invoke the server did not reject must carry a D-018
	// success envelope. Anything else (HTML, empty, ok:false without error)
	// is not proof the capability ran.
	if res.Err == nil && !res.Envelope.OK {
		res.Err = &StructuredError{
			Code:       CodeInternal,
			Message:    fmt.Sprintf("HTTP %d: malformed response from capability API (expected {\"ok\":true,…} envelope)", res.StatusCode),
			HTTPStatus: res.StatusCode,
			ExitCode:   ExitCode(CodeInternal),
			Body:       res.Body,
		}
	}
	return res, nil
}

// AcceptApproval POST /capabilities/approvals/{id}/accept
func (c *Client) AcceptApproval(ctx context.Context, id string) (*Response, error) {
	path, err := segmentPath(PathApprovals, "approval id", id, "/accept")
	if err != nil {
		return nil, err
	}
	return c.do(ctx, http.MethodPost, path, []byte(`{}`), nil)
}

// RejectApproval POST /capabilities/approvals/{id}/reject
func (c *Client) RejectApproval(ctx context.Context, id string) (*Response, error) {
	path, err := segmentPath(PathApprovals, "approval id", id, "/reject")
	if err != nil {
		return nil, err
	}
	return c.do(ctx, http.MethodPost, path, []byte(`{}`), nil)
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
