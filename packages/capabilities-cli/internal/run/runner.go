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
	JSON           bool
	TenantHint     string // hint only — not authoritative scope (D-003)
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

// Run executes: load input → local schema validate → ensure key → POST invoke.
// No domain logic. Does not skip server re-validation.
func Run(ctx context.Context, opts Options) *Result {
	res := &Result{ExitCode: ExitInternal}
	if strings.TrimSpace(opts.Capability) == "" {
		res.ExitCode = ExitValidation
		res.Stderr = "capability name required"
		res.Envelope = localFailEnvelope(api.CodeValidationFailed, res.Stderr, nil)
		return res
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

	// Idempotency key
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
		if opts.Capability == "" {
			opts.Capability = last.Capability
		}
	}
	key = EnsureIdempotencyKey(key)
	res.IdempotencyKey = key

	if opts.Client == nil {
		res.ExitCode = ExitInternal
		res.Stderr = "HTTP client not configured"
		return res
	}

	// Tenant hint is optional body metadata only when explicitly set — never authority.
	// We do not send X-Capabilities-Caller. Server derives caller from Bearer token.
	body := opts.InputJSON
	if opts.TenantHint != "" {
		// Attach as non-authoritative request field only if body is object.
		var m map[string]any
		if json.Unmarshal(body, &m) == nil {
			// Hint lives under a namespaced key so it cannot forge scope.
			m["_tenant_hint"] = opts.TenantHint
			body, _ = json.Marshal(m)
		}
	}

	res.HTTPCalled = true
	apiRes, err := opts.Client.InvokeCapability(ctx, opts.Capability, body, key)
	if err != nil {
		res.ExitCode = ExitInternal
		res.Stderr = err.Error()
		res.Envelope = localFailEnvelope(api.CodeInternal, err.Error(), nil)
		// Persist key so --retry-last reuses it after network failure.
		_ = saveLastRun(opts, opts.Capability, key, opts.InputJSON)
		return res
	}
	_ = saveLastRun(opts, opts.Capability, key, opts.InputJSON)

	res.Envelope = apiRes.Body
	if apiRes.Err != nil {
		res.ExitCode = apiRes.Err.ExitCode
		res.Stderr = apiRes.Err.Error()
		if opts.JSON {
			res.Stdout = string(apiRes.Body)
		}
		return res
	}
	if apiRes.StatusCode >= 400 {
		code := api.CodeInternal
		if apiRes.Err != nil {
			code = apiRes.Err.Code
		}
		res.ExitCode = ExitCodeFor(code)
		res.Stderr = string(apiRes.Body)
		return res
	}

	res.ExitCode = ExitOK
	if opts.JSON {
		res.Stdout = string(apiRes.Body)
	} else if apiRes.Envelope.Data != nil {
		b, _ := json.MarshalIndent(apiRes.Envelope.Data, "", "  ")
		res.Stdout = string(b)
	} else {
		res.Stdout = string(apiRes.Body)
	}
	return res
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
		return nil, fmt.Errorf("missing input: pass --input or --input-file")
	}
	if !json.Valid(opts.InputJSON) {
		return nil, fmt.Errorf("invalid JSON input")
	}
	return opts.InputJSON, nil
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


