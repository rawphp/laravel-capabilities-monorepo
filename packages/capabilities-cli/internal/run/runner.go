package run

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"time"

	"github.com/google/uuid"
	"github.com/rawphp/capabilities-cli/internal/api"
	"github.com/rawphp/capabilities-cli/internal/auth"
	"github.com/rawphp/capabilities-cli/internal/catalog"
)

// Options configures a single capabilities run.
type Options struct {
	Profile        string
	BaseURL        string // optional override
	Capability     string
	InputJSON      []byte
	InputFile      string
	IdempotencyKey string
	RetryLast      bool
	NoCache        bool
	JSON           bool // legacy: stdout is always machine envelope; kept for call-site compat
	Human          bool // human summary on stderr only; never replaces stdout envelope
	Store          *auth.Store
	Client         *api.Client
	Catalog        *catalog.Service
	// LastRunPath overrides store path for tests.
	LastRunPath string
}

// Result is the outcome of Run.
type Result struct {
	ExitCode       int
	Envelope       []byte
	Stdout         string
	Stderr         string
	IdempotencyKey string
	HTTPCalled     bool
	Deprecation    string
}

// LastRun records the previous invoke for --retry-last.
type LastRun struct {
	Capability     string `json:"capability"`
	IdempotencyKey string `json:"idempotency_key"`
	InputJSON      string `json:"input_json,omitempty"`
}

// EnsureIdempotencyKey returns manual key or a new UUID.
func EnsureIdempotencyKey(manual string) string {
	manual = strings.TrimSpace(manual)
	if manual != "" {
		return manual
	}
	return uuid.NewString()
}

// Run executes: load input → local schema validate → ensure key → API version probe → POST invoke.
// No domain logic. Does not skip server re-validation.
func Run(ctx context.Context, opts Options) *Result {
	res := &Result{ExitCode: ExitInternal}
	if strings.TrimSpace(opts.Capability) == "" {
		res.ExitCode = ExitValidation
		res.Stderr = "capability name required"
		res.Envelope = localFailEnvelope(api.CodeValidationFailed, res.Stderr, nil)
		return res
	}

	// --retry-last replays the previous invoke: same key and, unless new input
	// is given, the same body (a new body under an old key is not a retry).
	key := opts.IdempotencyKey
	if opts.RetryLast {
		last, lerr := loadLastRun(opts)
		if lerr != nil || last == nil || last.IdempotencyKey == "" {
			res.ExitCode = ExitValidation
			res.Stderr = "no previous run to retry"
			res.Envelope = localFailEnvelope(api.CodeValidationFailed, res.Stderr, nil)
			return res
		}
		key = last.IdempotencyKey
		if opts.InputFile == "" && len(opts.InputJSON) == 0 && last.Capability == opts.Capability {
			opts.InputJSON = []byte(last.InputJSON)
		}
	}

	input, err := loadInput(opts)
	if err != nil {
		res.ExitCode = ExitValidation
		res.Stderr = err.Error()
		res.Envelope = localFailEnvelope(api.CodeValidationFailed, res.Stderr, []api.Violation{{Message: err.Error()}})
		return res
	}
	opts.InputJSON = input

	// Auth guard
	profile := opts.Profile
	if profile == "" {
		profile = "default"
	}
	if opts.Store != nil {
		if _, err := opts.Store.RequireToken(profile); err != nil {
			res.ExitCode = ExitAuth
			res.Stderr = err.Error()
			res.Envelope = localFailEnvelope(api.CodeUnauthenticated, res.Stderr, nil)
			return res
		}
	}

	// Schema: cache or fetch
	var schema []byte
	if opts.Catalog != nil {
		opts.Catalog.NoCache = opts.NoCache
		entry, _, derr := opts.Catalog.Describe(ctx, opts.Capability)
		if derr == nil && entry != nil {
			schema = entry.InputSchema
			if w := catalog.DeprecationWarning(entry, time.Now()); w != "" {
				res.Deprecation = w
				res.Stderr = w + "\n"
			}
			// Alias resolution is cosmetic; invoke still uses the name the user passed
			// (server accepts alias or canonical per D-012).
		}
	}

	// Local structural validation — fail closed before network.
	if err := ValidateLocal(schema, opts.InputJSON); err != nil {
		res.ExitCode = ExitValidation
		res.HTTPCalled = false
		if ve, ok := err.(*ValidationError); ok {
			res.Stderr = ve.Error()
			res.Envelope = localFailEnvelope(api.CodeValidationFailed, ve.Message, ve.Violations)
		} else {
			res.Stderr = err.Error()
			res.Envelope = localFailEnvelope(api.CodeValidationFailed, err.Error(), nil)
		}
		return res
	}

	key = EnsureIdempotencyKey(key)
	res.IdempotencyKey = key

	if opts.Client == nil {
		res.ExitCode = ExitInternal
		res.Stderr = "HTTP client not configured"
		return res
	}

	// Body is the capability input only. We do not send X-Capabilities-Caller or a
	// tenant claim: the server derives caller and scope from the Bearer token (D-022, D-003).
	body := opts.InputJSON

	// Wire-version preflight: refuse before POST when the server speaks another API version.
	if verr := opts.Client.CheckAPIVersion(ctx); verr != nil {
		res.ExitCode = ExitInternal
		res.Stderr = verr.Error()
		res.Envelope = localFailEnvelope(api.CodeInternal, verr.Error(), nil)
		return res
	}

	res.HTTPCalled = true
	apiRes, err := opts.Client.InvokeCapability(ctx, opts.Capability, body, key)
	if err != nil {
		res.ExitCode = ExitInternal
		appendStderr(res, err.Error())
		res.Envelope = localFailEnvelope(api.CodeInternal, err.Error(), nil)
		// Persist key so --retry-last reuses it after network failure.
		_ = saveLastRun(opts, opts.Capability, key, opts.InputJSON)
		return res
	}
	_ = saveLastRun(opts, opts.Capability, key, opts.InputJSON)

	res.Envelope = apiRes.Body
	if apiRes.Err != nil {
		res.ExitCode = apiRes.Err.ExitCode
		appendStderr(res, apiRes.Err.Error())
		// Machine envelope on stdout for structured server errors.
		if len(apiRes.Body) > 0 {
			res.Stdout = string(apiRes.Body)
		}
		if apiRes.Err.Code == api.CodeRateLimited && apiRes.Err.RetryAfter > 0 {
			// Give scripted callers a concrete backoff on stdout, not just exit 6.
			res.Stdout = string(withRetryAfter(apiRes.Body, apiRes.Err))
			res.Stderr += fmt.Sprintf(" (retry after %ds)", apiRes.Err.RetryAfter)
		}
		return res
	}
	if apiRes.StatusCode >= 400 {
		// Only reachable when the body claims ok:true yet carries an error object
		// (the API client builds a StructuredError for every other >=400 shape).
		// Its self-declared code is not trusted: always CodeInternal.
		res.ExitCode = ExitCodeFor(api.CodeInternal)
		res.Stderr = string(apiRes.Body)
		return res
	}

	res.ExitCode = ExitOK
	// Agent-first: stdout is always the machine envelope (design CLI I/O).
	res.Stdout = string(apiRes.Body)
	if opts.Human {
		// Short human summary on stderr only — never dumps full payload (that is stdout).
		appendStderr(res, humanSuccessSummary(opts.Capability, apiRes.Body)+"\n")
	}
	return res
}

// appendStderr adds msg after any earlier stderr (the D-012 deprecation warning) instead of replacing it.
func appendStderr(res *Result, msg string) {
	if res.Stderr != "" && !strings.HasSuffix(res.Stderr, "\n") {
		res.Stderr += "\n"
	}
	res.Stderr += msg
}

// humanSuccessSummary is a one-line stderr cue for --human (stdout keeps the envelope).
func humanSuccessSummary(capability string, body []byte) string {
	cap := strings.TrimSpace(capability)
	if cap == "" {
		cap = "?"
	}
	// Prefer a tiny high-signal peek when data is a small map of scalars.
	var env struct {
		OK   bool           `json:"ok"`
		Data map[string]any `json:"data"`
	}
	if json.Unmarshal(body, &env) == nil && env.Data != nil {
		// Common coach payload shape: data.payload.date
		if payload, ok := env.Data["payload"].(map[string]any); ok {
			if d, ok := payload["date"].(string); ok && d != "" {
				return fmt.Sprintf("ok %s date=%s", cap, d)
			}
		}
		if id, ok := env.Data["id"].(string); ok && id != "" {
			return fmt.Sprintf("ok %s id=%s", cap, id)
		}
		if msg, ok := env.Data["message"].(string); ok && msg != "" {
			return fmt.Sprintf("ok %s %s", cap, msg)
		}
	}
	return fmt.Sprintf("ok %s", cap)
}

func loadInput(opts Options) ([]byte, error) {
	if opts.InputFile != "" {
		b, err := os.ReadFile(opts.InputFile)
		if err != nil {
			return nil, fmt.Errorf("read input file: %w", err)
		}
		if !json.Valid(b) {
			return nil, fmt.Errorf("input file is not valid JSON")
		}
		return b, nil
	}
	if len(opts.InputJSON) == 0 {
		// Empty invoke → {} so all-optional schemas can POST; required fields fail ValidateLocal (exit 2).
		return []byte("{}"), nil
	}
	if !json.Valid(opts.InputJSON) {
		return nil, fmt.Errorf("invalid JSON input")
	}
	return opts.InputJSON, nil
}

// withRetryAfter sets error.retry_after on a D-018 envelope, keeping every server
// field as sent. A non-envelope body (e.g. proxy throttle page) is replaced by a
// D-018 envelope built from the mapped error so stdout stays machine-readable.
func withRetryAfter(body []byte, se *api.StructuredError) []byte {
	var env map[string]json.RawMessage
	var errObj map[string]json.RawMessage
	if json.Unmarshal(body, &env) == nil && json.Unmarshal(env["error"], &errObj) == nil && errObj != nil {
		errObj["retry_after"], _ = json.Marshal(se.RetryAfter)
		env["error"], _ = json.Marshal(errObj)
		b, _ := json.Marshal(env)
		return b
	}
	b, _ := json.Marshal(api.ErrorEnvelope{
		OK: false,
		Error: &api.ErrorBody{
			Code:       se.Code,
			Message:    se.Message,
			Violations: se.Violations,
			ApprovalID: se.ApprovalID,
			RequestID:  se.RequestID,
			Retryable:  se.Retryable,
			RetryAfter: se.RetryAfter,
		},
	})
	return b
}

func localFailEnvelope(code, message string, viol []api.Violation) []byte {
	env := api.ErrorEnvelope{
		OK: false,
		Error: &api.ErrorBody{
			Code:       code,
			Message:    message,
			Violations: viol,
			Retryable:  false,
		},
	}
	b, _ := json.Marshal(env)
	return b
}

func lastRunPath(opts Options) string {
	if opts.LastRunPath != "" {
		return opts.LastRunPath
	}
	if opts.Store != nil {
		profile := opts.Profile
		if profile == "" {
			profile = "default"
		}
		return opts.Store.LastRunPath(profile)
	}
	return ""
}

func saveLastRun(opts Options, capability, key string, input []byte) error {
	path := lastRunPath(opts)
	if path == "" {
		return nil
	}
	lr := LastRun{Capability: capability, IdempotencyKey: key, InputJSON: string(input)}
	b, _ := json.Marshal(lr)
	return os.WriteFile(path, b, 0o600)
}

func loadLastRun(opts Options) (*LastRun, error) {
	path := lastRunPath(opts)
	if path == "" {
		return nil, fmt.Errorf("no last run path")
	}
	b, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var lr LastRun
	if err := json.Unmarshal(b, &lr); err != nil {
		return nil, err
	}
	return &lr, nil
}
